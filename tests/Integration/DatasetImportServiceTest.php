<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dataset\CanonicalTransaction;
use App\Dataset\DatasetImportLimits;
use App\Dataset\DatasetImportResult;
use App\Dataset\DatasetImportService;
use App\Dataset\DatasetParserInterface;
use App\Dataset\DatasetValidationException;
use App\Dataset\ParseResult;
use App\Dataset\ParserRegistry;
use App\Mining\AprioriEngine;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use PDO;
use Throwable;

class DatasetImportServiceTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $message = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}: {$message}";
        };

        SchemaTest::assertTestSafety();
        $pdo = self::createTestConnection();
        Migrator::run($pdo, dirname(__DIR__, 2) . '/database/migrations');

        $schemaErrors = SchemaVerifier::verify($pdo);
        $assert(
            'Dataset import integration starts from frozen valid schema',
            count($schemaErrors) === 0,
            implode('; ', $schemaErrors)
        );

        self::clearTestTables($pdo);

        try {
            $repository = new DatasetRepository($pdo);
            $service = new DatasetImportService(new ParserRegistry(), $repository);

            self::testFrozenDefaultLimits($assert);
            self::testTinyCsvDbToMiningOracle($service, $repository, $assert);
            self::testNumericBasketTextRoundTrip($service, $repository, $assert);
            self::testMushroomImport($service, $repository, $assert);
            self::testNamesSourcesAndWarnings($service, $repository, $assert);
            self::testPrePersistenceRejections($service, $pdo, $assert);
            self::testPersistenceFailureRollsBack($repository, $pdo, $assert);
            self::testUploadByteBoundaries($service, $repository, $pdo, $assert);
            self::testTransactionBoundaries($service, $pdo, $assert);
            self::testItemsPerTransactionBoundaries($service, $pdo, $assert);
            self::testCompactTotalItemRowBoundary($repository, $pdo, $assert);
        } finally {
            self::clearTestTables($pdo);
        }

        $postTestSchemaErrors = SchemaVerifier::verify($pdo);
        $assert(
            'Dataset import integration leaves frozen schema valid',
            count($postTestSchemaErrors) === 0,
            implode('; ', $postTestSchemaErrors)
        );

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testFrozenDefaultLimits(callable $assert): void
    {
        $limits = DatasetImportLimits::frozen();

        $assert(
            'DatasetImportService frozen upload byte limit is exactly 10 MiB',
            DatasetImportService::MAX_UPLOAD_BYTES === 10_485_760
                && $limits->getMaxUploadBytes() === 10_485_760
        );
        $assert(
            'DatasetImportService frozen transaction limit is exactly 100000',
            DatasetImportService::MAX_TRANSACTIONS === 100_000
                && $limits->getMaxTransactions() === 100_000
        );
        $assert(
            'DatasetImportService frozen per-transaction item limit is exactly 1000',
            DatasetImportService::MAX_UNIQUE_ITEMS_PER_TRANSACTION === 1_000
                && $limits->getMaxUniqueItemsPerTransaction() === 1_000
        );
        $assert(
            'DatasetImportService frozen total transaction-item row limit is exactly 5000000',
            DatasetImportService::MAX_TRANSACTION_ITEM_ROWS === 5_000_000
                && $limits->getMaxTransactionItemRows() === 5_000_000
        );
    }

    private static function testTinyCsvDbToMiningOracle(
        DatasetImportService $service,
        DatasetRepository $repository,
        callable $assert
    ): void {
        $content = file_get_contents(dirname(__DIR__) . '/fixtures/tiny.csv');
        if ($content === false) {
            throw new \RuntimeException('Unable to read frozen tiny.csv fixture.');
        }

        $result = $service->import($content, 'C:\\uploads\\tiny.csv', 'basket_csv');
        $dataset = $result->getDataset();

        $assert(
            'Tiny basket_csv import persists exact original-byte metadata and default name',
            $dataset->getName() === 'tiny'
                && $dataset->getSourceFilename() === 'tiny.csv'
                && $dataset->getFormat() === 'basket_csv'
                && $dataset->getByteSize() === 15
                && $dataset->getSha256() === '63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e'
                && $dataset->getTransactionCount() === 4
                && $dataset->getUniqueItemCount() === 3,
            'tiny import metadata differs from literal frozen oracle'
        );
        $assert(
            'Tiny basket_csv import returns no parser warnings',
            $result->getWarnings() === [] && $result->getTotalWarningCount() === 0
        );

        $transactions = $repository->loadTransactions($dataset->getId());
        $actualTransactions = array_map(
            static fn($transaction): array => $transaction->getItems(),
            $transactions
        );
        $assert(
            'Tiny import reloads exact canonical transaction oracle',
            $actualTransactions === [
                ['A', 'B', 'C'],
                ['A', 'B'],
                ['A', 'C'],
                ['A'],
            ],
            'reloaded tiny transactions differ from hand-authored oracle'
        );

        $apriori = (new AprioriEngine())->run($transactions, 500000);
        $assert(
            'Tiny DB-to-canonical-to-Apriori oracle totals remain exact',
            $apriori->getCandidatesGeneratedTotal() === 7
                && $apriori->getCandidatesPrunedTotal() === 1
                && $apriori->getCandidatesEvaluatedTotal() === 6
                && $apriori->getFrequentItemsetsTotal() === 5
                && $apriori->getMaxK() === 2,
            'expected generated=7, pruned=1, evaluated=6, frequent=5, max_k=2'
        );
    }

    private static function testNumericBasketTextRoundTrip(
        DatasetImportService $service,
        DatasetRepository $repository,
        callable $assert
    ): void {
        $content = "1 01 001 1.0 +1\n1 2 10\n0 -1";
        $result = $service->import($content, 'D:\\retail\\numeric.dat', 'basket_txt', ' Numeric Input ');
        $dataset = $result->getDataset();
        $transactions = $repository->loadTransactions($dataset->getId());
        $actual = array_map(static fn($transaction): array => $transaction->getItems(), $transactions);

        $assert(
            'Valid basket_txt numeric import preserves exact metadata and unique-item cardinality',
            $dataset->getName() === 'Numeric Input'
                && $dataset->getSourceFilename() === 'numeric.dat'
                && $dataset->getTransactionCount() === 3
                && $dataset->getUniqueItemCount() === 9
                && $dataset->getByteSize() === strlen($content)
                && $dataset->getSha256() === hash('sha256', $content),
            'numeric import metadata mismatch'
        );
        $assert(
            'Numeric import round-trip keeps each canonical numeric identity distinct',
            $actual === [
                ['+1', '001', '01', '1', '1.0'],
                ['1', '10', '2'],
                ['-1', '0'],
            ],
            'numeric canonical strings merged, converted, or reordered'
        );

        $allItems = array_merge(...$actual);
        $allStrings = array_reduce(
            $allItems,
            static fn(bool $allStrings, mixed $item): bool => $allStrings && is_string($item),
            true
        );
        $assert(
            'Numeric import reloads all values as PHP strings including 0 and -1',
            $allStrings && in_array('0', $allItems, true) && in_array('-1', $allItems, true)
        );

        $numericMining = (new AprioriEngine())->run($transactions, 333334);
        $assert(
            'Numeric import can be mined without TypeError using its exact canonical strings',
            $numericMining->getCandidatesGeneratedTotal() === 9
                && $numericMining->getCandidatesPrunedTotal() === 0
                && $numericMining->getCandidatesEvaluatedTotal() === 9
                && $numericMining->getFrequentItemsetsTotal() === 1
                && $numericMining->getMaxK() === 1,
            'numeric mining oracle totals differ'
        );
    }

    private static function testMushroomImport(
        DatasetImportService $service,
        DatasetRepository $repository,
        callable $assert
    ): void {
        $result = $service->import("e,x,n\np,y,n", '/datasets/mushroom.data', 'mushroom');
        $dataset = $result->getDataset();
        $transactions = $repository->loadTransactions($dataset->getId());

        $assert(
            'Valid mushroom import persists positional profile metadata',
            $dataset->getName() === 'mushroom'
                && $dataset->getSourceFilename() === 'mushroom.data'
                && $dataset->getFormat() === 'mushroom'
                && $dataset->getTransactionCount() === 2
                && $dataset->getUniqueItemCount() === 5
        );
        $assert(
            'Mushroom import reloads positional item mappings unchanged',
            $transactions[0]->getItems() === ['c1=e', 'c2=x', 'c3=n']
                && $transactions[1]->getItems() === ['c1=p', 'c2=y', 'c3=n'],
            'mushroom positional values mismatch'
        );
    }

    private static function testNamesSourcesAndWarnings(
        DatasetImportService $service,
        DatasetRepository $repository,
        callable $assert
    ): void {
        $warningResult = $service->import("A,A,B\n\nC", 'C:\\nested\\warning.csv', 'basket_csv', ' warning dataset ');
        $warningCodes = array_map(static fn($warning): string => $warning->getCode(), $warningResult->getWarnings());

        $assert(
            'Import preserves parser warning objects and total warning count',
            $warningResult->getDataset()->getName() === 'warning dataset'
                && $warningResult->getDataset()->getSourceFilename() === 'warning.csv'
                && $warningCodes === ['DUPLICATE_ITEM', 'BLANK_RECORD_SKIPPED']
                && $warningResult->getTotalWarningCount() === 2,
            'warning payload was discarded, changed, or miscounted'
        );

        $unicodeName = str_repeat('é', 120);
        $unicodeResult = $service->import('A', 'unicode.txt', 'basket_txt', $unicodeName);
        $assert(
            'Display name accepts exactly 120 UTF-8 characters without mbstring',
            $unicodeResult->getDataset()->getName() === $unicodeName
        );

        $reloaded = $repository->findById($warningResult->getDataset()->getId());
        $assert(
            'Imported detail read shape retains sanitized source and explicit display name',
            $reloaded !== null
                && $reloaded->getName() === 'warning dataset'
                && $reloaded->getSourceFilename() === 'warning.csv'
        );
    }

    private static function testPrePersistenceRejections(DatasetImportService $service, PDO $pdo, callable $assert): void
    {
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import('A', 'unsupported.txt', 'json', 'unsupported format marker'),
            'unsupported format marker',
            'Unsupported declared format rejects before persistence',
            $assert
        );
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import('A,B', 'profile.txt', 'basket_csv', 'profile mismatch marker'),
            'profile mismatch marker',
            'Profile and extension mismatch rejects before persistence',
            $assert
        );
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import('A,,B', 'invalid.csv', 'basket_csv', 'invalid parse marker'),
            'invalid parse marker',
            'Invalid parser result rejects before persistence',
            $assert
        );
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import('A', 'blank.txt', 'basket_txt', '   '),
            '',
            'Blank explicit display name rejects before persistence',
            $assert,
            true
        );
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import('A', 'long.txt', 'basket_txt', str_repeat('é', 121)),
            str_repeat('é', 121),
            'Display name over 120 UTF-8 characters rejects before persistence',
            $assert
        );
    }

    private static function testUploadByteBoundaries(
        DatasetImportService $service,
        DatasetRepository $repository,
        PDO $pdo,
        callable $assert
    ): void {
        $maxBytes = DatasetImportService::MAX_UPLOAD_BYTES;
        $token = str_repeat('A', 128);
        $chunk = $token . ' ';
        $repeatCount = intdiv($maxBytes, strlen($chunk));
        $remainder = $maxBytes - ($repeatCount * strlen($chunk));
        $content = str_repeat($chunk, $repeatCount) . str_repeat('B', $remainder);

        $boundaryResult = $service->import($content, 'exact-boundary.dat', 'basket_txt', 'exact byte boundary');
        $reloaded = $repository->loadTransactions($boundaryResult->getDataset()->getId());
        $assert(
            'Exactly 10 MiB valid upload is accepted and persists original byte count',
            strlen($content) === $maxBytes
                && $boundaryResult->getDataset()->getByteSize() === $maxBytes
                && $boundaryResult->getDataset()->getTransactionCount() === 1
                && $boundaryResult->getDataset()->getUniqueItemCount() === 2
                && $reloaded[0]->getItemCount() === 2,
            'exact 10 MiB valid import was not accepted as required'
        );

        unset($content, $boundaryResult, $reloaded);
        gc_collect_cycles();

        $overLimitContent = str_repeat('X', $maxBytes + 1);
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import($overLimitContent, 'over-limit.txt', 'basket_txt', 'over byte marker'),
            'over byte marker',
            '10 MiB plus one byte rejects before persistence',
            $assert
        );
        unset($overLimitContent);
        gc_collect_cycles();
    }

    private static function testPersistenceFailureRollsBack(
        DatasetRepository $repository,
        PDO $pdo,
        callable $assert
    ): void {
        $service = new DatasetImportService(
            new ParserRegistry(['basket_txt' => new DuplicateOrdinalDatasetParser()]),
            $repository
        );
        $datasetName = 'service database rollback marker';
        $caught = false;

        try {
            $service->import('A B', 'controlled-db-failure.txt', 'basket_txt', $datasetName);
        } catch (Throwable) {
            $caught = true;
        }

        $datasetStatement = $pdo->prepare('SELECT COUNT(*) FROM datasets WHERE name = :name');
        $datasetStatement->execute(['name' => $datasetName]);
        $datasetCount = (int)$datasetStatement->fetchColumn();

        $transactionStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM transactions t INNER JOIN datasets d ON d.id = t.dataset_id WHERE d.name = :name'
        );
        $transactionStatement->execute(['name' => $datasetName]);
        $transactionCount = (int)$transactionStatement->fetchColumn();

        $itemStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM transaction_items ti '
            . 'INNER JOIN transactions t ON t.id = ti.transaction_id '
            . 'INNER JOIN datasets d ON d.id = t.dataset_id '
            . 'WHERE d.name = :name'
        );
        $itemStatement->execute(['name' => $datasetName]);
        $itemCount = (int)$itemStatement->fetchColumn();

        $assert(
            'DatasetImportService propagates genuine persistence failure after complete parse and validation',
            $caught,
            'controlled duplicate ordinal failure was not propagated'
        );
        $assert(
            'DatasetImportService leaves no dataset, transaction, or item rows after persistence failure',
            $datasetCount === 0 && $transactionCount === 0 && $itemCount === 0,
            "rows remaining: datasets={$datasetCount}, transactions={$transactionCount}, items={$itemCount}"
        );
    }

    private static function testTransactionBoundaries(DatasetImportService $service, PDO $pdo, callable $assert): void
    {
        $atLimit = self::transactionText(DatasetImportService::MAX_TRANSACTIONS);
        $atLimitResult = $service->import($atLimit, 'transactions.txt', 'basket_txt', 'transaction boundary');
        $assert(
            'Exactly 100000 accepted transactions are permitted',
            $atLimitResult->getDataset()->getTransactionCount() === DatasetImportService::MAX_TRANSACTIONS
        );
        unset($atLimit, $atLimitResult);
        gc_collect_cycles();

        $overLimit = self::transactionText(DatasetImportService::MAX_TRANSACTIONS + 1);
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import($overLimit, 'too-many.txt', 'basket_txt', 'too many transactions marker'),
            'too many transactions marker',
            '100001 transactions reject before persistence',
            $assert
        );
        unset($overLimit);
        gc_collect_cycles();
    }

    private static function testItemsPerTransactionBoundaries(DatasetImportService $service, PDO $pdo, callable $assert): void
    {
        $atLimit = self::uniqueBasketTextItems(DatasetImportService::MAX_UNIQUE_ITEMS_PER_TRANSACTION);
        $atLimitResult = $service->import($atLimit, 'items.txt', 'basket_txt', 'item boundary');
        $assert(
            'Exactly 1000 unique items in one transaction are permitted',
            $atLimitResult->getDataset()->getTransactionCount() === 1
                && $atLimitResult->getDataset()->getUniqueItemCount() === DatasetImportService::MAX_UNIQUE_ITEMS_PER_TRANSACTION
        );

        $overLimit = self::uniqueBasketTextItems(DatasetImportService::MAX_UNIQUE_ITEMS_PER_TRANSACTION + 1);
        self::assertRejectedWithoutDataset(
            $service,
            $pdo,
            static fn(): DatasetImportResult => $service->import($overLimit, 'too-many-items.txt', 'basket_txt', 'too many items marker'),
            'too many items marker',
            '1001 unique items in one transaction reject before persistence',
            $assert
        );
    }

    private static function testCompactTotalItemRowBoundary(DatasetRepository $repository, PDO $pdo, callable $assert): void
    {
        $boundaryService = new DatasetImportService(
            new ParserRegistry(),
            $repository,
            new DatasetImportLimits(1024, 10, 10, 5)
        );
        $boundaryResult = $boundaryService->import("A,B,C\nD,E", 'total.csv', 'basket_csv', 'total row boundary');
        $assert(
            'Total transaction-item row boundary permits exactly its configured maximum',
            $boundaryResult->getDataset()->getTransactionCount() === 2
                && $boundaryResult->getDataset()->getUniqueItemCount() === 5
        );

        $overLimitService = new DatasetImportService(
            new ParserRegistry(),
            $repository,
            new DatasetImportLimits(1024, 10, 10, 4)
        );
        self::assertRejectedWithoutDataset(
            $overLimitService,
            $pdo,
            static fn(): DatasetImportResult => $overLimitService->import("A,B,C\nD,E", 'total-over.csv', 'basket_csv', 'total row over marker'),
            'total row over marker',
            'First total transaction-item row over limit rejects before persistence',
            $assert
        );
    }

    private static function assertRejectedWithoutDataset(
        DatasetImportService $service,
        PDO $pdo,
        callable $operation,
        string $expectedName,
        string $testName,
        callable $assert,
        bool $assertNoRowsAtAll = false
    ): void {
        $beforeTotalStatement = $pdo->query('SELECT COUNT(*) FROM datasets');
        $beforeTotal = (int)$beforeTotalStatement->fetchColumn();
        $caught = false;

        try {
            $operation();
        } catch (DatasetValidationException) {
            $caught = true;
        }

        $datasetCount = 0;
        if ($expectedName !== '') {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM datasets WHERE name = :name');
            $statement->execute(['name' => $expectedName]);
            $datasetCount = (int)$statement->fetchColumn();
        }
        $afterTotalStatement = $pdo->query('SELECT COUNT(*) FROM datasets');
        $afterTotal = (int)$afterTotalStatement->fetchColumn();

        $assert(
            $testName,
            $caught && ($assertNoRowsAtAll ? $afterTotal === $beforeTotal : $datasetCount === 0),
            $caught
                ? "unexpected persisted datasets: named={$datasetCount}, total before={$beforeTotal}, total after={$afterTotal}"
                : 'no DatasetValidationException was thrown'
        );
    }

    private static function transactionText(int $count): string
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('transaction count must be positive.');
        }

        return str_repeat("A\n", $count - 1) . 'A';
    }

    private static function uniqueBasketTextItems(int $count): string
    {
        $items = [];
        for ($index = 1; $index <= $count; $index++) {
            $items[] = 'item' . $index;
        }

        return implode(' ', $items);
    }

    private static function createTestConnection(): PDO
    {
        return ConnectionFactory::create([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => 'fim_dashboard_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ]);
    }

    private static function clearTestTables(PDO $pdo): void
    {
        SchemaTest::assertTestSafety();
        $tables = ['experiment_run_levels', 'experiment_runs', 'transaction_items', 'transactions', 'datasets'];
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}

/**
 * A controlled parser double supplies duplicate accepted ordinals so the
 * unmodified repository must fail after beginning its database write.
 */
final class DuplicateOrdinalDatasetParser implements DatasetParserInterface
{
    public function getFormatToken(): string
    {
        return 'basket_txt';
    }

    public function getAllowedExtensions(): array
    {
        return ['txt'];
    }

    public function parse(string $content, string $sourceFilename): ParseResult
    {
        $warnings = [];

        return new ParseResult([
            CanonicalTransaction::fromRawItems(1, ['A'], $warnings, 1),
            CanonicalTransaction::fromRawItems(1, ['B'], $warnings, 2),
        ]);
    }
}
