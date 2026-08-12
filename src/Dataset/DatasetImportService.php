<?php

declare(strict_types=1);

namespace App\Dataset;

use App\Persistence\DatasetRepository;
use InvalidArgumentException;

/**
 * HTTP-independent application service for validating and importing datasets.
 */
final class DatasetImportService
{
    /** @var list<string> */
    private const ALLOWED_FORMATS = ['basket_csv', 'basket_txt', 'mushroom'];

    public const MAX_UPLOAD_BYTES = DatasetImportLimits::MAX_UPLOAD_BYTES;
    public const MAX_TRANSACTIONS = DatasetImportLimits::MAX_TRANSACTIONS;
    public const MAX_UNIQUE_ITEMS_PER_TRANSACTION = DatasetImportLimits::MAX_UNIQUE_ITEMS_PER_TRANSACTION;
    public const MAX_TRANSACTION_ITEM_ROWS = DatasetImportLimits::MAX_TRANSACTION_ITEM_ROWS;

    private readonly DatasetImportLimits $limits;

    public function __construct(
        private readonly ParserRegistry $parsers,
        private readonly DatasetRepository $datasets,
        ?DatasetImportLimits $limits = null
    ) {
        $this->limits = $limits ?? new DatasetImportLimits();
    }

    public function import(
        string $uploadedContent,
        string $sourceFilename,
        string $declaredFormat,
        ?string $datasetDisplayName = null
    ): DatasetImportResult {
        if (!in_array($declaredFormat, self::ALLOWED_FORMATS, true) || !$this->parsers->hasParser($declaredFormat)) {
            throw self::validationFailure(
                'UNSUPPORTED_FORMAT',
                'The declared dataset format is not supported.'
            );
        }

        $safeSourceFilename = self::sanitizeSourceFilename($sourceFilename);
        $byteSize = strlen($uploadedContent);
        $maxUploadBytes = $this->limits->getMaxUploadBytes();
        if ($byteSize > $maxUploadBytes) {
            throw self::validationFailure(
                'UPLOAD_TOO_LARGE',
                "Uploaded content exceeds the maximum of {$maxUploadBytes} bytes."
            );
        }

        // Extension/profile validation is intentionally separate from format selection.
        $this->parsers->validateExtension($declaredFormat, $safeSourceFilename);
        $parseResult = $this->parsers->getParser($declaredFormat)->parse(
            $uploadedContent,
            $safeSourceFilename
        );

        $transactions = $parseResult->getTransactions();
        $statistics = $this->validateGlobalLimitsAndCalculateStatistics($transactions);
        $datasetName = self::resolveDatasetName($safeSourceFilename, $datasetDisplayName);
        $sha256 = hash('sha256', $uploadedContent);

        $dataset = $this->datasets->createCompleted(
            $datasetName,
            $safeSourceFilename,
            $declaredFormat,
            $sha256,
            $byteSize,
            $statistics['unique_item_count'],
            $transactions
        );

        return new DatasetImportResult(
            $dataset,
            $parseResult->getWarnings(),
            $parseResult->getTotalWarningCount()
        );
    }

    private static function sanitizeSourceFilename(string $sourceFilename): string
    {
        return basename(str_replace('\\', '/', $sourceFilename));
    }

    private static function resolveDatasetName(string $safeSourceFilename, ?string $datasetDisplayName): string
    {
        if ($datasetDisplayName !== null) {
            return self::validateDatasetName(trim($datasetDisplayName));
        }

        return self::validateDatasetName(pathinfo($safeSourceFilename, PATHINFO_FILENAME));
    }

    private static function validateDatasetName(string $name): string
    {
        if ($name === '' || preg_match('//u', $name) !== 1) {
            throw self::validationFailure(
                'INVALID_DATASET_NAME',
                'Dataset name must be non-empty valid UTF-8 text.'
            );
        }

        $characterCount = preg_match_all('/./us', $name);
        if ($characterCount === false || $characterCount < 1 || $characterCount > 120) {
            throw self::validationFailure(
                'INVALID_DATASET_NAME',
                'Dataset name must contain between 1 and 120 UTF-8 characters.'
            );
        }

        return $name;
    }

    /**
     * @param list<CanonicalTransaction> $transactions
     * @return array{transaction_count: int, unique_item_count: int, transaction_item_rows: int}
     */
    private function validateGlobalLimitsAndCalculateStatistics(array $transactions): array
    {
        $transactionCount = 0;
        $transactionItemRows = 0;
        $uniqueItems = [];
        $uniqueItemCount = 0;

        foreach ($transactions as $transaction) {
            if (!$transaction instanceof CanonicalTransaction) {
                throw new InvalidArgumentException('Parser result must contain only CanonicalTransaction values.');
            }

            $transactionCount++;
            $maxTransactions = $this->limits->getMaxTransactions();
            if ($transactionCount > $maxTransactions) {
                throw self::validationFailure(
                    'TRANSACTION_LIMIT_EXCEEDED',
                    "Dataset exceeds the maximum of {$maxTransactions} accepted transactions."
                );
            }

            $itemCount = $transaction->getItemCount();
            $maxUniqueItemsPerTransaction = $this->limits->getMaxUniqueItemsPerTransaction();
            if ($itemCount > $maxUniqueItemsPerTransaction) {
                throw self::validationFailure(
                    'ITEMS_PER_TRANSACTION_LIMIT_EXCEEDED',
                    "A transaction exceeds the maximum of {$maxUniqueItemsPerTransaction} unique items."
                );
            }

            $transactionItemRows += $itemCount;
            $maxTransactionItemRows = $this->limits->getMaxTransactionItemRows();
            if ($transactionItemRows > $maxTransactionItemRows) {
                throw self::validationFailure(
                    'TRANSACTION_ITEM_ROW_LIMIT_EXCEEDED',
                    "Dataset exceeds the maximum of {$maxTransactionItemRows} transaction-item rows."
                );
            }

            foreach ($transaction->getItems() as $item) {
                $identity = CanonicalItemIndexKey::encode($item);
                if (!isset($uniqueItems[$identity])) {
                    $uniqueItems[$identity] = true;
                    $uniqueItemCount++;
                }
            }
        }

        return [
            'transaction_count' => $transactionCount,
            'unique_item_count' => $uniqueItemCount,
            'transaction_item_rows' => $transactionItemRows,
        ];
    }

    private static function validationFailure(string $code, string $message): DatasetValidationException
    {
        return new DatasetValidationException([new ParserIssue($code, 0, $message)], 1);
    }
}
