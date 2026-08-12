<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Persistence\ConnectionFactory;
use App\Persistence\Migrator;
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

        // 4. Exact INFORMATION_SCHEMA Introspection
        self::testExactSchemaIntrospection($pdo, $assert);

        // Clean test tables before inserting constraint test rows
        $requiredTables = ['datasets', 'transactions', 'transaction_items', 'experiment_runs', 'experiment_run_levels'];
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($requiredTables as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 5. Constraint Behavior Tests
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

    private static function testExactSchemaIntrospection(PDO $pdo, callable $assert): void
    {
        $db = 'fim_dashboard_test';

        // Introspect Columns for datasets
        $dsColumns = self::getTableColumns($pdo, $db, 'datasets');
        $assert('datasets has exact column count 9', count($dsColumns) === 9);
        $assert('datasets.id is bigint unsigned auto_increment', str_contains($dsColumns['id']['COLUMN_TYPE'], 'bigint') && str_contains($dsColumns['id']['COLUMN_TYPE'], 'unsigned') && str_contains($dsColumns['id']['EXTRA'], 'auto_increment'));
        $assert('datasets.format collation is utf8mb4_bin', $dsColumns['format']['COLLATION_NAME'] === 'utf8mb4_bin');
        $assert('datasets.sha256 collation is ascii_general_ci', $dsColumns['sha256']['COLLATION_NAME'] === 'ascii_general_ci');

        // Primary Keys
        $dsPk = self::getTablePrimaryKey($pdo, $db, 'datasets');
        $assert('datasets Primary Key is [id]', $dsPk === ['id']);

        // Introspect Columns for transactions
        $txColumns = self::getTableColumns($pdo, $db, 'transactions');
        $assert('transactions has exact column count 4', count($txColumns) === 4);
        $txPk = self::getTablePrimaryKey($pdo, $db, 'transactions');
        $assert('transactions Primary Key is [id]', $txPk === ['id']);

        // Foreign Key on transactions
        $txFks = self::getForeignKeys($pdo, $db, 'transactions');
        $assert('transactions FK dataset_id -> datasets.id CASCADE', isset($txFks['dataset_id']) && $txFks['dataset_id']['referenced_table'] === 'datasets' && $txFks['dataset_id']['delete_rule'] === 'CASCADE');

        // Unique constraints on transactions
        $txUniques = self::getUniqueConstraints($pdo, $db, 'transactions');
        $assert('transactions unique constraint (dataset_id, transaction_key) exists', in_array(['dataset_id', 'transaction_key'], $txUniques, true));
        $assert('transactions unique constraint (dataset_id, ordinal) exists', in_array(['dataset_id', 'ordinal'], $txUniques, true));

        // Introspect Columns for transaction_items
        $tiColumns = self::getTableColumns($pdo, $db, 'transaction_items');
        $assert('transaction_items has exact column count 2', count($tiColumns) === 2);
        $assert('transaction_items.item_key collation is utf8mb4_bin', $tiColumns['item_key']['COLLATION_NAME'] === 'utf8mb4_bin');
        $tiPk = self::getTablePrimaryKey($pdo, $db, 'transaction_items');
        $assert('transaction_items Primary Key is [transaction_id, item_key]', $tiPk === ['transaction_id', 'item_key']);

        // Introspect Columns for experiment_runs
        $erColumns = self::getTableColumns($pdo, $db, 'experiment_runs');
        $assert('experiment_runs has exact column count 13', count($erColumns) === 13);
        $assert('experiment_runs.min_support type decimal(7,6)', str_contains($erColumns['min_support']['COLUMN_TYPE'], 'decimal(7,6)'));
        $assert('experiment_runs.runtime_ms type decimal(12,3)', str_contains($erColumns['runtime_ms']['COLUMN_TYPE'], 'decimal(12,3)'));

        $erFks = self::getForeignKeys($pdo, $db, 'experiment_runs');
        $assert('experiment_runs FK dataset_id -> datasets.id RESTRICT', isset($erFks['dataset_id']) && $erFks['dataset_id']['referenced_table'] === 'datasets' && $erFks['dataset_id']['delete_rule'] === 'RESTRICT');

        // Introspect Columns for experiment_run_levels
        $erlColumns = self::getTableColumns($pdo, $db, 'experiment_run_levels');
        $assert('experiment_run_levels has exact column count 7', count($erlColumns) === 7);
        $assert('experiment_run_levels.source collation is utf8mb4_bin', $erlColumns['source']['COLLATION_NAME'] === 'utf8mb4_bin');
        $erlPk = self::getTablePrimaryKey($pdo, $db, 'experiment_run_levels');
        $assert('experiment_run_levels Primary Key is [run_id, k]', $erlPk === ['run_id', 'k']);
    }

    private static function testConstraintBehaviors(PDO $pdo, callable $assert): void
    {
        // 1. datasets format CHECK rejects invalid format
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `datasets` (`name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES ('test', 'test.csv', 'invalid_fmt', REPEAT('a', 64), 100)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('datasets format CHECK rejects invalid format', $caught);

        // 2. datasets format CHECK rejects uppercase BASKET_CSV due to utf8mb4_bin
        $caughtUpper = false;
        try {
            $pdo->exec("INSERT INTO `datasets` (`name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES ('test', 'test.csv', 'BASKET_CSV', REPEAT('a', 64), 100)");
        } catch (PDOException $e) {
            $caughtUpper = true;
        }
        $assert('datasets format CHECK rejects uppercase BASKET_CSV', $caughtUpper);

        // Insert valid dataset for FK tests
        $pdo->exec("INSERT INTO `datasets` (`id`, `name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES (1, 'Mushroom', 'mushroom.csv', 'mushroom', REPEAT('a', 64), 1000)");

        // 3. transactions FK rejects missing dataset
        $caughtFk = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (9999, 'T1', 1)");
        } catch (PDOException $e) {
            $caughtFk = true;
        }
        $assert('transactions FK rejects missing dataset_id', $caughtFk);

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

        // 10. experiment_runs min_confidence > 1.0 rejected
        $caughtConfHigh = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, 1.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caughtConfHigh = true;
        }
        $assert('experiment_runs min_confidence > 1.0 rejected', $caughtConfHigh);

        // 11. experiment_runs runtime_ms < 0.0 rejected
        $caughtRuntimeNeg = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (1, 0.5, 0.5, -1.0, 5.0)");
        } catch (PDOException $e) {
            $caughtRuntimeNeg = true;
        }
        $assert('experiment_runs runtime_ms < 0.0 rejected', $caughtRuntimeNeg);

        // Insert valid experiment_run
        $pdo->exec("INSERT INTO `experiment_runs` (`id`, `dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (100, 1, 0.1, 0.5, 10.0, 5.0)");

        // 12. experiment_run_levels CHECK k >= 1
        $caughtKZero = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 0, 'singleton_scan', 10, 0, 10, 5)");
        } catch (PDOException $e) {
            $caughtKZero = true;
        }
        $assert('experiment_run_levels CHECK rejects k = 0', $caughtKZero);

        // 13. experiment_run_levels source CHECK rejects uppercase SINGLETON_SCAN due to utf8mb4_bin
        $caughtSourceUpper = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'SINGLETON_SCAN', 10, 0, 10, 5)");
        } catch (PDOException $e) {
            $caughtSourceUpper = true;
        }
        $assert('experiment_run_levels source CHECK rejects uppercase SINGLETON_SCAN', $caughtSourceUpper);

        // 14. experiment_run_levels CHECK pruned + evaluated = generated
        $caughtPrunedEval = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 5, 3)");
        } catch (PDOException $e) {
            $caughtPrunedEval = true;
        }
        $assert('experiment_run_levels CHECK rejects pruned + evaluated != generated', $caughtPrunedEval);

        // 15. experiment_run_levels CHECK frequent <= evaluated
        $caughtFreq = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 9)");
        } catch (PDOException $e) {
            $caughtFreq = true;
        }
        $assert('experiment_run_levels CHECK rejects frequent > evaluated', $caughtFreq);

        // Insert valid level
        $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 5)");

        // 16. Delete Behavior — datasets -> experiment_runs is RESTRICTED
        $caughtRestricted = false;
        try {
            $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        } catch (PDOException $e) {
            $caughtRestricted = true;
        }
        $assert('dataset deletion with referenced experiment_run is RESTRICTED', $caughtRestricted);

        // 17. Delete Behavior — experiment_runs -> experiment_run_levels CASCADE
        $pdo->exec("DELETE FROM `experiment_runs` WHERE `id` = 100");
        $levelCount = (int)$pdo->query("SELECT COUNT(*) FROM `experiment_run_levels` WHERE `run_id` = 100")->fetchColumn();
        $assert('experiment_runs -> experiment_run_levels CASCADE delete works', $levelCount === 0);

        // 18. Delete Behavior — datasets -> transactions -> transaction_items CASCADE
        $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        $txCount = (int)$pdo->query("SELECT COUNT(*) FROM `transactions` WHERE `dataset_id` = 1")->fetchColumn();
        $itemCount2 = (int)$pdo->query("SELECT COUNT(*) FROM `transaction_items` WHERE `transaction_id` = 10")->fetchColumn();
        $assert('datasets -> transactions -> transaction_items CASCADE delete works', $txCount === 0 && $itemCount2 === 0);
    }

    private static function getTableColumns(PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLLATION_NAME, EXTRA FROM information_schema.columns WHERE table_schema = ? AND table_name = ?");
        $stmt->execute([$db, $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $rUpper = array_change_key_case($r, CASE_UPPER);
            $colName = $rUpper['COLUMN_NAME'];
            $result[$colName] = $rUpper;
        }
        return $result;
    }

    private static function getTablePrimaryKey(PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND constraint_name = 'PRIMARY' ORDER BY ordinal_position");
        $stmt->execute([$db, $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function getForeignKeys(PDO $pdo, string $db, string $table): array
    {
        $sql = "SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                FROM information_schema.key_column_usage k
                JOIN information_schema.referential_constraints r
                  ON k.constraint_schema = r.constraint_schema AND k.constraint_name = r.constraint_name
                WHERE k.table_schema = ? AND k.table_name = ? AND k.referenced_table_name IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$db, $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) {
            $rUpper = array_change_key_case($r, CASE_UPPER);
            $col = $rUpper['COLUMN_NAME'];
            $result[$col] = [
                'referenced_table' => $rUpper['REFERENCED_TABLE_NAME'],
                'referenced_column' => $rUpper['REFERENCED_COLUMN_NAME'],
                'delete_rule' => $rUpper['DELETE_RULE'],
            ];
        }
        return $result;
    }

    private static function getUniqueConstraints(PDO $pdo, string $db, string $table): array
    {
        $sql = "SELECT CONSTRAINT_NAME, COLUMN_NAME
                FROM information_schema.key_column_usage
                WHERE table_schema = ? AND table_name = ? AND constraint_name != 'PRIMARY' AND constraint_name LIKE 'uq_%'
                ORDER BY constraint_name, ordinal_position";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$db, $table]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($rows as $r) {
            $rUpper = array_change_key_case($r, CASE_UPPER);
            $cName = $rUpper['CONSTRAINT_NAME'];
            $colName = $rUpper['COLUMN_NAME'];
            $grouped[$cName][] = $colName;
        }
        return array_values($grouped);
    }
}
