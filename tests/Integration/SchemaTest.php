<?php

declare(strict_types=1);

namespace App\Tests\Integration;

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

        // Safety Guard Verification: MUST be test env and fim_dashboard_test DB
        $appEnv = getenv('APP_ENV') ?: 'test';
        $dbName = getenv('DB_NAME') ?: 'fim_dashboard_test';

        if ($appEnv !== 'test' || $dbName !== 'fim_dashboard_test') {
            throw new RuntimeException("SAFETY VIOLATION: Schema tests require APP_ENV=test and DB_NAME=fim_dashboard_test. Got env='{$appEnv}', db='{$dbName}'.");
        }

        $config = [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => 'fim_dashboard_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ];

        $pdo = ConnectionFactory::create($config);

        // 1. Run Migration on test DB
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $executed = Migrator::run($pdo, $migrationsDir);
        $assert('Migration executed on test database', count($executed) > 0);

        // 2. Rerun Migration (Idempotency Check)
        $rerunExecuted = Migrator::run($pdo, $migrationsDir);
        $assert('Rerun migration is idempotent and safe', count($rerunExecuted) > 0);

        // 3. Verify all 5 tables exist
        $requiredTables = ['datasets', 'transactions', 'transaction_items', 'experiment_runs', 'experiment_run_levels'];

        $tableQuery = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'fim_dashboard_test'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($requiredTables as $reqTable) {
            $assert("Table '{$reqTable}' exists", in_array($reqTable, $tableQuery, true));
        }

        // 4. Verify transaction_items.item_key collation is utf8mb4_bin
        $itemKeyCollation = $pdo->query(
            "SELECT collation_name FROM information_schema.columns WHERE table_schema = 'fim_dashboard_test' AND table_name = 'transaction_items' AND column_name = 'item_key'"
        )->fetchColumn();
        $assert('transaction_items.item_key collation is utf8mb4_bin', $itemKeyCollation === 'utf8mb4_bin');

        // Clean test tables before inserting test rows
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($requiredTables as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // 5. Constraint Behavior — datasets format CHECK rejects invalid format
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `datasets` (`name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES ('test', 'test.csv', 'invalid_fmt', REPEAT('a', 64), 100)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('datasets format CHECK rejects invalid format', $caught);

        // Insert valid dataset for FK tests
        $pdo->exec("INSERT INTO `datasets` (`id`, `name`, `source_filename`, `format`, `sha256`, `byte_size`) VALUES (1, 'Mushroom', 'mushroom.csv', 'mushroom', REPEAT('a', 64), 1000)");

        // 6. Constraint Behavior — transactions FK rejects missing dataset
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (9999, 'T1', 1)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('transactions FK rejects missing dataset_id', $caught);

        // Insert valid transaction
        $pdo->exec("INSERT INTO `transactions` (`id`, `dataset_id`, `transaction_key`, `ordinal`) VALUES (10, 1, 'T1', 1)");

        // 7. Constraint Behavior — transactions unique key (dataset_id, transaction_key)
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `transactions` (`dataset_id`, `transaction_key`, `ordinal`) VALUES (1, 'T1', 2)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('transactions unique key (dataset_id, transaction_key) rejects duplicate', $caught);

        // 8. Constraint Behavior — transaction_items composite PK & case-distinct coexistence (utf8mb4_bin)
        $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'ItemA')");
        $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'itemA')");

        $itemCount = (int)$pdo->query("SELECT COUNT(*) FROM `transaction_items` WHERE `transaction_id` = 10")->fetchColumn();
        $assert('Case-distinct item_keys (ItemA, itemA) coexist due to utf8mb4_bin', $itemCount === 2);

        $caught = false;
        try {
            $pdo->exec("INSERT INTO `transaction_items` (`transaction_id`, `item_key`) VALUES (10, 'ItemA')");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('transaction_items composite PK rejects duplicate (transaction_id, item_key)', $caught);

        // 9. Constraint Behavior — experiment_runs FK rejects missing dataset_id
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `experiment_runs` (`dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (9999, 0.1, 0.5, 10.0, 5.0)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('experiment_runs FK rejects missing dataset_id', $caught);

        // Insert valid experiment_run
        $pdo->exec("INSERT INTO `experiment_runs` (`id`, `dataset_id`, `min_support`, `min_confidence`, `runtime_ms`, `rule_generation_runtime_ms`) VALUES (100, 1, 0.1, 0.5, 10.0, 5.0)");

        // 10. Constraint Behavior — experiment_run_levels CHECK k >= 1
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 0, 'singleton_scan', 10, 0, 10, 5)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('experiment_run_levels CHECK rejects k = 0', $caught);

        // 11. Constraint Behavior — experiment_run_levels CHECK pruned + evaluated = generated
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 5, 3)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('experiment_run_levels CHECK rejects pruned + evaluated != generated', $caught);

        // 12. Constraint Behavior — experiment_run_levels CHECK frequent <= evaluated
        $caught = false;
        try {
            $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 9)");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('experiment_run_levels CHECK rejects frequent > evaluated', $caught);

        // Insert valid level
        $pdo->exec("INSERT INTO `experiment_run_levels` (`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) VALUES (100, 1, 'singleton_scan', 10, 2, 8, 5)");

        // 13. Delete Behavior — datasets -> experiment_runs is RESTRICTED
        $caught = false;
        try {
            $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        } catch (PDOException $e) {
            $caught = true;
        }
        $assert('dataset deletion with referenced experiment_run is RESTRICTED', $caught);

        // 14. Delete Behavior — experiment_runs -> experiment_run_levels CASCADE
        $pdo->exec("DELETE FROM `experiment_runs` WHERE `id` = 100");
        $levelCount = (int)$pdo->query("SELECT COUNT(*) FROM `experiment_run_levels` WHERE `run_id` = 100")->fetchColumn();
        $assert('experiment_runs -> experiment_run_levels CASCADE delete works', $levelCount === 0);

        // 15. Delete Behavior — datasets -> transactions -> transaction_items CASCADE
        $pdo->exec("DELETE FROM `datasets` WHERE `id` = 1");
        $txCount = (int)$pdo->query("SELECT COUNT(*) FROM `transactions` WHERE `dataset_id` = 1")->fetchColumn();
        $itemCount2 = (int)$pdo->query("SELECT COUNT(*) FROM `transaction_items` WHERE `transaction_id` = 10")->fetchColumn();
        $assert('datasets -> transactions -> transaction_items CASCADE delete works', $txCount === 0 && $itemCount2 === 0);

        // Clean up test tables after testing
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($requiredTables as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
