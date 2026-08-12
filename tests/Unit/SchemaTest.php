<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Persistence\ConnectionFactory;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use PDO;
use PDOException;
use RuntimeException;

class SchemaTest
{
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $msg = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
            } else {
                $failed++;
                $results[] = "[FAIL] {$name}: {$msg}";
            }
        };

        // 1. Negative Safety Guard Test — Must fail closed if APP_ENV != test or DB_NAME != fim_dashboard_test
        self::testSafetyGuardFailsClosed($assert);

        // 2. Enforce strict safety assertion before connecting / truncating
        self::assertTestSafety();

        $config = [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => 'fim_dashboard_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ];

        $pdo = ConnectionFactory::create($config);

        // 3. Migration Execution and Rerun Verification
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $executed = Migrator::run($pdo, $migrationsDir);
        $assert('Migration executed on test database', count($executed) > 0);

        $rerunExecuted = Migrator::run($pdo, $migrationsDir);
        $assert('Rerun migration is idempotent and verified', count($rerunExecuted) > 0);

        // 4. Authoritative Schema Introspection using SchemaVerifier
        $schemaErrors = SchemaVerifier::verify($pdo);
        $assert('Authoritative SchemaVerifier finds 0 errors on clean test DB', count($schemaErrors) === 0, implode('; ', $schemaErrors));

        // 5. Controlled Structural Index Drift Detection Test
        self::testSchemaDriftDetection($pdo, $assert);

        // 6. Controlled Column Default Drift Detection Test
        self::testColumnDefaultDriftDetection($pdo, $assert);

        // 7. Controlled Same-Name CHECK Semantic Drift Detection Test
        self::testSameNameCheckSemanticDriftDetection($pdo, $assert);

        // Clean test tables before inserting constraint test rows
        $requiredTables = ['datasets', 'transactions', 'transaction_items', 'experiment_runs', 'experiment_run_levels'];
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($requiredTables as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 8. Complete Constraint Behavior & Boundary Tests
        self::testConstraintBehaviors($pdo, $assert);

        // Cleanup test tables after testing
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($requiredTables as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    public static function assertTestSafety(): void
    {
        $rawEnv = getenv('APP_ENV');
        $rawDb = getenv('DB_NAME');

        if ($rawEnv === false || $rawEnv !== 'test') {
            throw new RuntimeException("SAFETY GUARD VIOLATION: APP_ENV must be explicitly set to 'test'. Current: " . var_export($rawEnv, true));
        }

        if ($rawDb === false || $rawDb !== 'fim_dashboard_test') {
            throw new RuntimeException("SAFETY GUARD VIOLATION: DB_NAME must be explicitly set to 'fim_dashboard_test'. Current: " . var_export($rawDb, true));
        }
    }

    private static function testSafetyGuardFailsClosed(callable $assert): void
    {
        $origEnv = getenv('APP_ENV');
        $origDb = getenv('DB_NAME');

        // Test bad APP_ENV
        putenv('APP_ENV=development');
        $caughtEnv = false;
        try {
            self::assertTestSafety();
        } catch (RuntimeException $e) {
            $caughtEnv = true;
        } finally {
            if ($origEnv !== false) {
                putenv("APP_ENV={$origEnv}");
            } else {
                putenv('APP_ENV');
            }
        }
        $assert('Safety guard refuses APP_ENV=development', $caughtEnv);

        // Test bad DB_NAME
        putenv('DB_NAME=fim_dashboard');
        $caughtDb = false;
        try {
            self::assertTestSafety();
        } catch (RuntimeException $e) {
            $caughtDb = true;
        } finally {
            if ($origDb !== false) {
                putenv("DB_NAME={$origDb}");
            } else {
                putenv('DB_NAME');
            }
        }
        $assert('Safety guard refuses DB_NAME=fim_dashboard', $caughtDb);
    }

    private static function testSchemaDriftDetection(PDO $pdo, callable $assert): void
    {
        try {
            // Introduce structural drift: drop index idx_datasets_sha256
            $pdo->exec("ALTER TABLE `datasets` DROP INDEX `idx_datasets_sha256`");

            $driftErrors = SchemaVerifier::verify($pdo);
            $detected = (count($driftErrors) > 0 && str_contains(implode(' ', $driftErrors), 'idx_datasets_sha256'));
            $assert('SchemaVerifier detects structural drift (dropped index)', $detected);
        } finally {
            // Restore schema
            $pdo->exec("ALTER TABLE `datasets` ADD INDEX `idx_datasets_sha256` (`sha256`)");
        }

        $restoredErrors = SchemaVerifier::verify($pdo);
        $assert('SchemaVerifier passes after index restoration', count($restoredErrors) === 0, implode('; ', $restoredErrors));
    }

    private static function testColumnDefaultDriftDetection(PDO $pdo, callable $assert): void
    {
        try {
            // Temporarily change default for datasets.transaction_count to 1
            $pdo->exec("ALTER TABLE `datasets` ALTER COLUMN `transaction_count` SET DEFAULT 1");

            $driftErrors = SchemaVerifier::verify($pdo);
            $detected = (count($driftErrors) > 0 && str_contains(implode(' ', $driftErrors), 'transaction_count') && str_contains(implode(' ', $driftErrors), 'default mismatch'));
            $assert('SchemaVerifier detects column default drift (transaction_count DEFAULT 1)', $detected);
        } finally {
            // Restore exact frozen default
            $pdo->exec("ALTER TABLE `datasets` ALTER COLUMN `transaction_count` SET DEFAULT 0");
        }

        $restoredErrors = SchemaVerifier::verify($pdo);
        $assert('SchemaVerifier passes after column default restoration', count($restoredErrors) === 0, implode('; ', $restoredErrors));
    }

    private static function testSameNameCheckSemanticDriftDetection(PDO $pdo, callable $assert): void
    {
        try {
            // Temporarily replace chk_experiment_runs_min_support with wrong definition (min_support >= 0)
            $pdo->exec("ALTER TABLE `experiment_runs` DROP CHECK `chk_experiment_runs_min_support`");
            $pdo->exec("ALTER TABLE `experiment_runs` ADD CONSTRAINT `chk_experiment_runs_min_support` CHECK (`min_support` >= 0)");

            $driftErrors = SchemaVerifier::verify($pdo);
            $detected = (count($driftErrors) > 0 && str_contains(implode(' ', $driftErrors), 'chk_experiment_runs_min_support') && str_contains(implode(' ', $driftErrors), 'semantic mismatch'));
            $assert('SchemaVerifier detects same-name CHECK semantic drift', $detected);
        } finally {
            // Restore exact frozen CHECK definition
            $pdo->exec("ALTER TABLE `experiment_runs` DROP CHECK `chk_experiment_runs_min_support`");
            $pdo->exec("ALTER TABLE `experiment_runs` ADD CONSTRAINT `chk_experiment_runs_min_support` CHECK (`min_support` > 0 AND `min_support` <= 1)");
        }

        $restoredErrors = SchemaVerifier::verify($pdo);
        $assert('SchemaVerifier passes after CHECK semantics restoration', count($restoredErrors) === 0, implode('; ', $restoredErrors));
    }

    private static function testConstraintBehaviors(PDO $pdo, callable $assert): void
    {
        // 1. datasets format CHECK rejects invalid format
        $caughtFmt = false;
        try {
            $pdo->exec("INSERT INTO `datasets` (`name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES ('test', 'test.csv', 'invalid_fmt', REPEAT('a', 64), 100)");
        } catch (PDOException $e) {
            $caughtFmt = true;
        }
        $assert('datasets format CHECK rejects invalid format', $caughtFmt);

        // 2. datasets format CHECK rejects uppercase BASKET_CSV due to utf8mb4_bin
        $caughtUpperFmt = false;
        try {
            $pdo->exec("INSERT INTO `datasets` (`name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES ('test', 'test.csv', 'BASKET_CSV', REPEAT('a', 64), 100)");
        } catch (PDOException $e) {
            $caughtUpperFmt = true;
        }
        $assert('datasets format CHECK rejects uppercase BASKET_CSV', $caughtUpperFmt);

        // Insert valid dataset for FK tests
        $pdo->exec("INSERT INTO `datasets` (`id`, `name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES (1, 'Mushroom', 'mushroom.csv', 'mushroom', REPEAT('a', 64), 1000)");

        // 3. transactions FK rejects missing dataset
        $caughtTxFk = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (9999, 'T1', 1)");
        } catch (PDOException $e) {
            $caughtTxFk = true;
        }
        $assert('transactions FK rejects missing dataset_id', $caughtTxFk);

        // Insert valid transaction
        $pdo->exec("INSERT INTO `transactions` (`id`, `dataset_id`, `transaction_key`, `ordinal`) VALUES (10, 1, 'T1', 1)");

        // 4. transactions unique key (dataset_id, transaction_key)
        $caughtUniqKey = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (1, 'T1', 2)");
        } catch (PDOException $e) {
            $caughtUniqKey = true;
        }
        $assert('transactions unique key (dataset_id, transaction_key) rejects duplicate', $caughtUniqKey);

        // 5. transactions unique key (dataset_id, ordinal)
        $caughtUniqOrd = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (1, 'T2', 1)");
        } catch (PDOException $e) {
            $caughtUniqOrd = true;
        }
        $assert('transactions unique key (dataset_id, ordinal) rejects duplicate', $caughtUniqOrd);

        // 6. transaction_items composite PK & case-distinct coexistence (utf8mb4_bin)
        $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'ItemA')");
        $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'itemA')");

        $itemCount = (int)$pdo->query("SELECT COUNT(*) FROM `transaction_items` WHERE `transaction_id` = 10")->fetchColumn();
        $assert('Case-distinct item_keys (ItemA, itemA) coexist due to utf8mb4_bin', $itemCount === 2);

        $caughtDupItem = false;
        try {
            $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'ItemA')");
        } catch (PDOException $e) {
            $caughtDupItem = true;
        }
        $assert('transaction_items composite PK rejects duplicate (transaction_id, item_key)', $caughtDupItem);

        // 7. experiment_runs FK rejects missing dataset_id
        $caughtErFk = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (9999, 0.1, 0.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtErFk = true;
        }
        $assert('experiment_runs FK rejects missing dataset_id', $caughtErFk);

        // 8. experiment_runs min_support = 0.0 rejected
        $caughtSuppZero = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.0, 0.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtSuppZero = true;
        }
        $assert('experiment_runs min_support = 0.0 rejected', $caughtSuppZero);

        // 9. experiment_runs min_support > 1.0 rejected
        $caughtSuppHigh = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 1.5, 0.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtSuppHigh = true;
        }
        $assert('experiment_runs min_support > 1.0 rejected', $caughtSuppHigh);

        // 10. experiment_runs min_confidence < 0.0 rejected
        $caughtConfNeg = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, -0.1, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtConfNeg = true;
        }
        $assert('experiment_runs min_confidence < 0.0 rejected', $caughtConfNeg);

        // 11. experiment_runs min_confidence > 1.0 rejected
        $caughtConfHigh = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, 1.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtConfHigh = true;
        }
        $assert('experiment_runs min_confidence > 1.0 rejected', $caughtConfHigh);

        // 12. experiment_runs runtime_ms < 0.0 rejected
        $caughtRuntimeNeg = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, 0.5, -1.0, 5.0)");
        } catch (PDOException $e) {
            $caughtRuntimeNeg = true;
        }
        $assert('experiment_runs runtime_ms < 0.0 rejected', $caughtRuntimeNeg);

        // 13. experiment_runs rule_generation_runtime_ms < 0.0 rejected
        $caughtRuleRuntimeNeg = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, 0.5, 10.0, -1.0)");
        } catch (PDOException $e) {
            $caughtRuleRuntimeNeg = true;
        }
        $assert('experiment_runs rule_generation_runtime_ms < 0.0 rejected', $caughtRuleRuntimeNeg);

        // 14. Valid boundaries min_support = 1.000000, min_confidence = 0.000000 succeed
        $pdo->exec("INSERT INTO `experiment_runs` (`id`, `dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (100, 1, 1.000000, 0.000000, 10.0, 5.0)");
        $assert('experiment_runs valid boundary min_support=1.0, min_confidence=0.0 succeeds', true);

        // 15. Valid boundaries min_confidence = 1.000000 succeeds
        $pdo->exec("INSERT INTO `experiment_runs` (`id`, `dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (101, 1, 0.500000, 1.000000, 10.0, 5.0)");
        $assert('experiment_runs valid boundary min_confidence=1.0 succeeds', true);

        // 16. experiment_run_levels CHECK k >= 1
        $caughtKZero = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 0, 'singleton_scan', 10, 0, 10, 5)");
        } catch (PDOException $e) {
            $caughtKZero = true;
        }
        $assert('experiment_run_levels CHECK rejects k = 0', $caughtKZero);

        // 17. experiment_run_levels source CHECK rejects uppercase SINGLETON_SCAN due to utf8mb4_bin
        $caughtSourceUpper = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'SINGLETON_SCAN', 10, 0, 10, 5)");
        } catch (PDOException $e) {
            $caughtSourceUpper = true;
        }
        $assert('experiment_run_levels source CHECK rejects uppercase SINGLETON_SCAN', $caughtSourceUpper);

        // 18. experiment_run_levels CHECK pruned + evaluated = generated
        $caughtPrunedEval = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 5, 3)");
        } catch (PDOException $e) {
            $caughtPrunedEval = true;
        }
        $assert('experiment_run_levels CHECK rejects pruned + evaluated != generated', $caughtPrunedEval);

        // 19. experiment_run_levels CHECK frequent <= evaluated
        $caughtFreq = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 9)");
        } catch (PDOException $e) {
            $caughtFreq = true;
        }
        $assert('experiment_run_levels CHECK rejects frequent > evaluated', $caughtFreq);

        // Insert valid level
        $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 5)");

        // 20. Delete Behavior — datasets -> experiment_runs is RESTRICTED
        $caughtRestricted = false;
        try {
            $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        } catch (PDOException $e) {
            $caughtRestricted = true;
        }
        $assert('dataset deletion with referenced experiment_run is RESTRICTED', $caughtRestricted);

        // 21. Delete Behavior — experiment_runs -> experiment_run_levels CASCADE
        $pdo->exec("DELETE FROM `experiment_runs` WHERE `id` = 100");
        $levelCount = (int)$pdo->query("SELECT COUNT(*) FROM `experiment_run_levels` WHERE `run_id` = 100")->fetchColumn();
        $assert('experiment_runs -> experiment_run_levels CASCADE delete works', $levelCount === 0);

        // 22. Delete Behavior — datasets -> transactions -> transaction_items CASCADE
        $pdo->exec("DELETE FROM `experiment_runs` WHERE `dataset_id` = 1");
        $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        $txCount = (int)$pdo->query("SELECT COUNT(*) FROM `transactions` WHERE `dataset_id` = 1")->fetchColumn();
        $itemCount2 = (int)$pdo->query("SELECT COUNT(*) FROM `transaction_items` WHERE `transaction_id` = 10")->fetchColumn();
        $assert('datasets -> transactions -> transaction_items CASCADE delete works', $txCount === 0 && $itemCount2 === 0);
    }
}
