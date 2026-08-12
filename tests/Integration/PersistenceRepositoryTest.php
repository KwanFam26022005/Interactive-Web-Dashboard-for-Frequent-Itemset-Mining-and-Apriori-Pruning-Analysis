<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dataset\CanonicalTransaction;
use App\Mining\AprioriResult;
use App\Mining\AssociationRule;
use App\Mining\Itemset;
use App\Mining\LevelMetrics;
use App\Mining\RuleGenerationResult;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\ExperimentRunRepository;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use PDO;
use Throwable;

class PersistenceRepositoryTest
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
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        Migrator::run($pdo, $migrationsDir);

        $schemaErrors = SchemaVerifier::verify($pdo);
        $assert(
            'Persistence integration starts from frozen valid schema',
            count($schemaErrors) === 0,
            implode('; ', $schemaErrors)
        );

        self::clearTestTables($pdo);

        try {
            $datasets = new DatasetRepository($pdo);
            $runs = new ExperimentRunRepository($pdo);

            self::testDatasetWriteAndRead($pdo, $datasets, $assert);
            self::testCanonicalNumericRoundTrip($datasets, $assert);
            self::testDatasetRollback($pdo, $datasets, $assert);
            self::testCompletedRunPersistence($pdo, $datasets, $runs, $assert);
            self::testExperimentRunRollback($pdo, $datasets, $runs, $assert);
        } finally {
            self::clearTestTables($pdo);
        }

        $postTestSchemaErrors = SchemaVerifier::verify($pdo);
        $assert(
            'Persistence integration leaves frozen schema valid',
            count($postTestSchemaErrors) === 0,
            implode('; ', $postTestSchemaErrors)
        );

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testDatasetWriteAndRead(PDO $pdo, DatasetRepository $datasets, callable $assert): void
    {
        $warnings = [];
        $firstTransactions = [
            CanonicalTransaction::fromRawItems(2, ['B', 'A'], $warnings, 2),
            CanonicalTransaction::fromRawItems(1, ['C', 'A'], $warnings, 1),
        ];

        $first = $datasets->createCompleted(
            'first dataset',
            'C:\\uploads\\first.csv',
            'basket_csv',
            str_repeat('a', 64),
            8,
            3,
            $firstTransactions
        );

        $secondWarnings = [];
        $second = $datasets->createCompleted(
            'second dataset',
            '/tmp/second.txt',
            'basket_txt',
            str_repeat('b', 64),
            4,
            1,
            [CanonicalTransaction::fromRawItems(1, ['Z'], $secondWarnings, 1)]
        );

        $assert('DatasetRepository returns generated dataset ID', $first->getId() > 0 && $second->getId() > $first->getId());
        $assert(
            'DatasetRepository persists only source basename',
            $first->getSourceFilename() === 'first.csv' && $second->getSourceFilename() === 'second.txt',
            "got '{$first->getSourceFilename()}' and '{$second->getSourceFilename()}'"
        );
        $assert(
            'DatasetRepository completed metadata contains frozen fields',
            $first->getName() === 'first dataset'
                && $first->getFormat() === 'basket_csv'
                && $first->getSha256() === str_repeat('a', 64)
                && $first->getByteSize() === 8
                && $first->getTransactionCount() === 2
                && $first->getUniqueItemCount() === 3
                && $first->getCreatedAt() !== '',
            'returned metadata did not match literal write values'
        );

        $listed = $datasets->listNewestFirst();
        $assert(
            'DatasetRepository lists datasets newest first with deterministic ID tie-breaker',
            count($listed) === 2 && $listed[0]->getId() === $second->getId() && $listed[1]->getId() === $first->getId(),
            'dataset list did not return second then first'
        );

        $detail = $datasets->findById($first->getId());
        $assert(
            'DatasetRepository finds one persisted dataset by positive ID',
            $detail !== null
                && $detail->getId() === $first->getId()
                && $detail->getSourceFilename() === 'first.csv'
                && $detail->getTransactionCount() === 2,
            'detail record was missing or mismatched'
        );
        $assert('DatasetRepository returns null for missing dataset', $datasets->findById(999999999) === null);

        $loaded = $datasets->loadTransactions($first->getId());
        $assert(
            'DatasetRepository reloads transactions ordered by ordinal',
            count($loaded) === 2 && $loaded[0]->getOrdinal() === 1 && $loaded[1]->getOrdinal() === 2,
            'transaction ordinals were not 1, 2'
        );
        $assert(
            'DatasetRepository reconstructs canonical transactions at the domain boundary',
            $loaded[0]->getItems() === ['A', 'C']
                && $loaded[1]->getItems() === ['A', 'B']
                && $loaded[0]->getTransactionKey() === '1'
                && $loaded[1]->getTransactionKey() === '2',
            'reloaded transaction values were not canonical literal expectations'
        );

        $storedItemCount = (int)$pdo->query('SELECT COUNT(*) FROM transaction_items')->fetchColumn();
        $assert('DatasetRepository inserts every transaction item row', $storedItemCount === 5, "got {$storedItemCount}");
    }

    private static function testCanonicalNumericRoundTrip(DatasetRepository $datasets, callable $assert): void
    {
        $warnings = [];
        $transactions = [
            CanonicalTransaction::fromRawItems(
                1,
                ['1', '01', '001', '1.0', '0', '-1', '+1', 'A', 'a', 'café'],
                $warnings,
                1
            ),
            CanonicalTransaction::fromRawItems(2, ['1', '10', '2'], $warnings, 2),
        ];

        $record = $datasets->createCompleted(
            'numeric and identity round trip',
            'C:\\incoming\\numeric.dat',
            'basket_txt',
            str_repeat('c', 64),
            42,
            12,
            $transactions
        );

        $loaded = $datasets->loadTransactions($record->getId());
        $firstItems = $loaded[0]->getItems();
        $secondItems = $loaded[1]->getItems();

        $assert(
            'Numeric identity round-trip preserves canonical strcmp ordering',
            $firstItems === ['+1', '-1', '0', '001', '01', '1', '1.0', 'A', 'a', 'café']
                && $secondItems === ['1', '10', '2'],
            'numeric-like strings were reordered, merged, or converted'
        );

        $allItems = array_merge($firstItems, $secondItems);
        $allStrings = array_reduce(
            $allItems,
            static fn(bool $carry, mixed $item): bool => $carry && is_string($item),
            true
        );
        $assert('Numeric identity round-trip reloads every item as PHP string', $allStrings);
        $assert(
            'Numeric identity round-trip keeps exact distinct strings distinct',
            count(array_filter($firstItems, static fn(string $item): bool => in_array($item, ['1', '01', '001', '1.0', '0', '-1', '+1'], true))) === 7,
            'one or more required numeric canonical strings were lost'
        );
        $assert(
            'Binary collation preserves case-distinct and UTF-8 item identities',
            in_array('A', $firstItems, true)
                && in_array('a', $firstItems, true)
                && in_array('café', $firstItems, true)
                && count(array_filter($firstItems, static fn(string $item): bool => $item === 'A' || $item === 'a')) === 2,
            'A/a or UTF-8 item did not survive exact round-trip'
        );
    }

    private static function testDatasetRollback(PDO $pdo, DatasetRepository $datasets, callable $assert): void
    {
        $warnings = [];
        $failed = false;
        $rollbackName = 'rollback dataset marker';

        try {
            $datasets->createCompleted(
                $rollbackName,
                'rollback.csv',
                'basket_csv',
                str_repeat('d', 64),
                3,
                2,
                [
                    CanonicalTransaction::fromRawItems(1, ['A'], $warnings, 1),
                    CanonicalTransaction::fromRawItems(1, ['B'], $warnings, 2),
                ]
            );
        } catch (Throwable) {
            $failed = true;
        }

        $datasetCountStatement = $pdo->prepare('SELECT COUNT(*) FROM datasets WHERE name = :name');
        $datasetCountStatement->execute(['name' => $rollbackName]);
        $datasetCount = (int)$datasetCountStatement->fetchColumn();

        $transactionCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM transactions t INNER JOIN datasets d ON d.id = t.dataset_id WHERE d.name = :name'
        );
        $transactionCountStatement->execute(['name' => $rollbackName]);
        $transactionCount = (int)$transactionCountStatement->fetchColumn();

        $itemCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM transaction_items ti INNER JOIN transactions t ON t.id = ti.transaction_id INNER JOIN datasets d ON d.id = t.dataset_id WHERE d.name = :name'
        );
        $itemCountStatement->execute(['name' => $rollbackName]);
        $itemCount = (int)$itemCountStatement->fetchColumn();

        $assert('DatasetRepository propagates controlled duplicate ordinal database failure', $failed);
        $assert(
            'DatasetRepository rolls back dataset metadata, transactions, and items together',
            $datasetCount === 0 && $transactionCount === 0 && $itemCount === 0,
            "rows remaining: datasets={$datasetCount}, transactions={$transactionCount}, items={$itemCount}"
        );
    }

    private static function testCompletedRunPersistence(
        PDO $pdo,
        DatasetRepository $datasets,
        ExperimentRunRepository $runs,
        callable $assert
    ): void {
        $dataset = self::createRunDataset($datasets, 'completed run parent', 'e');
        $result = self::createThreeLevelResult();
        $ruleResult = new RuleGenerationResult(
            [
                AssociationRule::createFromCounts(
                    Itemset::fromCanonicalItems(['B']),
                    Itemset::fromCanonicalItems(['A']),
                    2,
                    2,
                    4,
                    4
                ),
                AssociationRule::createFromCounts(
                    Itemset::fromCanonicalItems(['C']),
                    Itemset::fromCanonicalItems(['A']),
                    2,
                    2,
                    4,
                    4
                ),
            ],
            2,
            45678
        );

        $runId = $runs->saveCompleted($dataset->getId(), 500000, 750000, $result, $ruleResult);
        $assert('ExperimentRunRepository returns generated run ID', $runId > 0);

        $summaryStatement = $pdo->prepare(
            'SELECT dataset_id, min_support, min_confidence, runtime_ms, rule_generation_runtime_ms, candidates_generated, candidates_pruned, candidates_evaluated, frequent_itemsets, rules_count, max_k FROM experiment_runs WHERE id = :id'
        );
        $summaryStatement->execute(['id' => $runId]);
        $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC);

        $assert(
            'ExperimentRunRepository persists exact completed summary fields',
            is_array($summary)
                && (int)$summary['dataset_id'] === $dataset->getId()
                && (string)$summary['min_support'] === '0.500000'
                && (string)$summary['min_confidence'] === '0.750000'
                && (string)$summary['runtime_ms'] === '1.235'
                && (string)$summary['rule_generation_runtime_ms'] === '0.046'
                && (int)$summary['candidates_generated'] === 7
                && (int)$summary['candidates_pruned'] === 1
                && (int)$summary['candidates_evaluated'] === 6
                && (int)$summary['frequent_itemsets'] === 5
                && (int)$summary['rules_count'] === 2
                && (int)$summary['max_k'] === 2,
            'stored experiment run summary did not match literal oracle values'
        );

        $levelsStatement = $pdo->prepare(
            'SELECT `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent` '
            . 'FROM `experiment_run_levels` WHERE `run_id` = :id ORDER BY `k` ASC'
        );
        $levelsStatement->execute(['id' => $runId]);
        $levels = $levelsStatement->fetchAll(PDO::FETCH_ASSOC);
        $expectedLevels = [
            ['k' => 1, 'source' => 'singleton_scan', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 3],
            ['k' => 2, 'source' => 'join_prune', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 2],
            ['k' => 3, 'source' => 'join_prune', 'generated' => 1, 'pruned' => 1, 'evaluated' => 0, 'frequent' => 0],
        ];

        $actualLevels = array_map(
            static fn(array $level): array => [
                'k' => (int)$level['k'],
                'source' => (string)$level['source'],
                'generated' => (int)$level['generated'],
                'pruned' => (int)$level['pruned'],
                'evaluated' => (int)$level['evaluated'],
                'frequent' => (int)$level['frequent'],
            ],
            $levels
        );
        $assert(
            'ExperimentRunRepository inserts every completed level row in frozen order',
            $actualLevels === $expectedLevels,
            'level rows did not match literal oracle'
        );
    }

    private static function testExperimentRunRollback(
        PDO $pdo,
        DatasetRepository $datasets,
        ExperimentRunRepository $runs,
        callable $assert
    ): void {
        $dataset = self::createRunDataset($datasets, 'rollback run parent', 'f');
        $one = Itemset::fromCanonicalItems(['A']);
        $two = Itemset::fromCanonicalItems(['B']);
        $duplicateKResult = new AprioriResult(
            1,
            [$one, $two],
            [],
            [
                new LevelMetrics(1, 'singleton_scan', 1, 0, 1, 1),
                new LevelMetrics(1, 'singleton_scan', 1, 0, 1, 1),
            ],
            1,
            1
        );

        $failed = false;
        try {
            $runs->saveCompleted($dataset->getId(), 1000000, 0, $duplicateKResult, new RuleGenerationResult([], 0, 1));
        } catch (Throwable) {
            $failed = true;
        }

        $runCountStatement = $pdo->prepare('SELECT COUNT(*) FROM experiment_runs WHERE dataset_id = :dataset_id');
        $runCountStatement->execute(['dataset_id' => $dataset->getId()]);
        $runCount = (int)$runCountStatement->fetchColumn();

        $levelCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM experiment_run_levels l INNER JOIN experiment_runs r ON r.id = l.run_id WHERE r.dataset_id = :dataset_id'
        );
        $levelCountStatement->execute(['dataset_id' => $dataset->getId()]);
        $levelCount = (int)$levelCountStatement->fetchColumn();

        $assert('ExperimentRunRepository propagates controlled duplicate level key database failure', $failed);
        $assert(
            'ExperimentRunRepository rolls back run summary and all level rows together',
            $runCount === 0 && $levelCount === 0,
            "rows remaining: runs={$runCount}, levels={$levelCount}"
        );
    }

    private static function createRunDataset(DatasetRepository $datasets, string $name, string $checksumCharacter): object
    {
        $warnings = [];

        return $datasets->createCompleted(
            $name,
            $name . '.csv',
            'basket_csv',
            str_repeat($checksumCharacter, 64),
            1,
            1,
            [CanonicalTransaction::fromRawItems(1, ['A'], $warnings, 1)]
        );
    }

    private static function createThreeLevelResult(): AprioriResult
    {
        $frequentItemsets = [
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['B']),
            Itemset::fromCanonicalItems(['C']),
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
        ];

        return new AprioriResult(
            2,
            $frequentItemsets,
            [],
            [
                new LevelMetrics(1, 'singleton_scan', 3, 0, 3, 3),
                new LevelMetrics(2, 'join_prune', 3, 0, 3, 2),
                new LevelMetrics(3, 'join_prune', 1, 1, 0, 0),
            ],
            2,
            1234567
        );
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
