<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Dataset\CanonicalTransaction;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PDO persistence boundary for immutable normalized datasets.
 */
final class DatasetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Atomically persists completed metadata and canonical transactions.
     *
     * @param list<CanonicalTransaction> $transactions
     */
    public function createCompleted(
        string $name,
        string $sourceFilename,
        string $format,
        string $sha256,
        int $byteSize,
        int $uniqueItemCount,
        array $transactions
    ): DatasetRecord {
        foreach ($transactions as $transaction) {
            if (!$transaction instanceof CanonicalTransaction) {
                throw new InvalidArgumentException('transactions must contain only CanonicalTransaction values.');
            }
        }

        $safeSourceFilename = basename(str_replace('\\', '/', $sourceFilename));
        $transactionCount = count($transactions);
        $transactionStarted = false;

        try {
            $this->pdo->beginTransaction();
            $transactionStarted = true;

            $datasetStatement = $this->pdo->prepare(
                'INSERT INTO datasets '
                . '(name, source_filename, format, sha256, byte_size, transaction_count, unique_item_count) '
                . 'VALUES (:name, :source_filename, :format, :sha256, :byte_size, :transaction_count, :unique_item_count)'
            );
            $datasetStatement->bindValue(':name', $name, PDO::PARAM_STR);
            $datasetStatement->bindValue(':source_filename', $safeSourceFilename, PDO::PARAM_STR);
            $datasetStatement->bindValue(':format', $format, PDO::PARAM_STR);
            $datasetStatement->bindValue(':sha256', $sha256, PDO::PARAM_STR);
            $datasetStatement->bindValue(':byte_size', $byteSize, PDO::PARAM_INT);
            $datasetStatement->bindValue(':transaction_count', $transactionCount, PDO::PARAM_INT);
            $datasetStatement->bindValue(':unique_item_count', $uniqueItemCount, PDO::PARAM_INT);
            $datasetStatement->execute();

            $datasetId = $this->lastInsertId('dataset');

            $transactionStatement = $this->pdo->prepare(
                'INSERT INTO transactions (dataset_id, transaction_key, ordinal) '
                . 'VALUES (:dataset_id, :transaction_key, :ordinal)'
            );
            $itemStatement = $this->pdo->prepare(
                'INSERT INTO transaction_items (transaction_id, item_key) '
                . 'VALUES (:transaction_id, :item_key)'
            );

            foreach ($transactions as $transaction) {
                $transactionStatement->bindValue(':dataset_id', $datasetId, PDO::PARAM_INT);
                $transactionStatement->bindValue(':transaction_key', $transaction->getTransactionKey(), PDO::PARAM_STR);
                $transactionStatement->bindValue(':ordinal', $transaction->getOrdinal(), PDO::PARAM_INT);
                $transactionStatement->execute();

                $transactionId = $this->lastInsertId('transaction');

                foreach ($transaction->getItems() as $item) {
                    // Bind as a string so numeric-looking canonical values remain exact strings.
                    $itemStatement->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
                    $itemStatement->bindValue(':item_key', $item, PDO::PARAM_STR);
                    $itemStatement->execute();
                }
            }

            $recordStatement = $this->pdo->prepare(
                'SELECT id, name, format, source_filename, sha256, byte_size, transaction_count, unique_item_count, created_at '
                . 'FROM datasets WHERE id = :id'
            );
            $recordStatement->bindValue(':id', $datasetId, PDO::PARAM_INT);
            $recordStatement->execute();
            $row = $recordStatement->fetch();
            if ($row === false) {
                throw new RuntimeException('Inserted dataset metadata could not be reloaded.');
            }

            $record = $this->recordFromRow($row);
            $this->pdo->commit();

            return $record;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                $this->rollbackIfActive();
            }
            throw $throwable;
        }
    }

    /**
     * @return list<DatasetRecord>
     */
    public function listNewestFirst(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, format, source_filename, sha256, byte_size, transaction_count, unique_item_count, created_at '
            . 'FROM datasets ORDER BY created_at DESC, id DESC'
        );
        $statement->execute();

        $records = [];
        while (($row = $statement->fetch()) !== false) {
            $records[] = $this->recordFromRow($row);
        }

        return $records;
    }

    public function findById(int $id): ?DatasetRecord
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, name, format, source_filename, sha256, byte_size, transaction_count, unique_item_count, created_at '
            . 'FROM datasets WHERE id = :id'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch();
        return $row === false ? null : $this->recordFromRow($row);
    }

    /**
     * @return list<CanonicalTransaction>
     */
    public function loadTransactions(int $datasetId): array
    {
        if ($datasetId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT t.ordinal, ti.item_key '
            . 'FROM transactions AS t '
            . 'INNER JOIN transaction_items AS ti ON ti.transaction_id = t.id '
            . 'WHERE t.dataset_id = :dataset_id '
            . 'ORDER BY t.ordinal ASC, ti.item_key ASC'
        );
        $statement->bindValue(':dataset_id', $datasetId, PDO::PARAM_INT);
        $statement->execute();

        $transactions = [];
        $currentOrdinal = null;
        $currentItems = [];

        while (($row = $statement->fetch()) !== false) {
            $ordinal = (int)$row['ordinal'];

            if ($currentOrdinal !== null && $ordinal !== $currentOrdinal) {
                $transactions[] = CanonicalTransaction::fromRawItems($currentOrdinal, $currentItems);
                $currentItems = [];
            }

            if ($currentOrdinal === null || $ordinal !== $currentOrdinal) {
                $currentOrdinal = $ordinal;
            }

            // Values stay array values, never PHP array keys, to preserve numeric-string identity.
            $currentItems[] = (string)$row['item_key'];
        }

        if ($currentOrdinal !== null) {
            $transactions[] = CanonicalTransaction::fromRawItems($currentOrdinal, $currentItems);
        }

        return $transactions;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordFromRow(array $row): DatasetRecord
    {
        return new DatasetRecord(
            (int)$row['id'],
            (string)$row['name'],
            (string)$row['format'],
            (string)$row['source_filename'],
            (string)$row['sha256'],
            (int)$row['byte_size'],
            (int)$row['transaction_count'],
            (int)$row['unique_item_count'],
            (string)$row['created_at']
        );
    }

    private function lastInsertId(string $entity): int
    {
        $value = $this->pdo->lastInsertId();
        if ($value === '' || !ctype_digit($value)) {
            throw new RuntimeException("Unable to determine inserted {$entity} ID.");
        }

        $id = (int)$value;
        if ($id <= 0) {
            throw new RuntimeException("Inserted {$entity} ID must be positive.");
        }

        return $id;
    }

    private function rollbackIfActive(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }

        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Preserve the original write failure for callers.
        }
    }
}
