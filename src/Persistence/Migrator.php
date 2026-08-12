<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;
use PDOException;
use RuntimeException;

class Migrator
{
    /**
     * Run all SQL migration files in the given directory against the PDO connection,
     * and verify schema completeness.
     *
     * @return list<string> Names of migration files executed / verified
     */
    public static function run(PDO $pdo, string $migrationsDir): array
    {
        if (!is_dir($migrationsDir)) {
            throw new RuntimeException("Migrations directory not found: {$migrationsDir}");
        }

        $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql');
        if ($files === false || count($files) === 0) {
            return [];
        }

        sort($files);
        $executed = [];

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Failed to read migration file: {$file}");
            }

            try {
                $pdo->exec($sql);
                $executed[] = basename($file) . ' (verified)';
            } catch (PDOException $e) {
                throw new RuntimeException(
                    "Migration failed in file '" . basename($file) . "': " . $e->getMessage(),
                    (int)$e->getCode()
                );
            }
        }

        // Post-execution schema verification: ensure all 5 required tables exist
        self::verifySchemaStructure($pdo);

        return $executed;
    }

    /**
     * Verify that the database schema contains all five required Phase 1 tables.
     */

    public static function verifySchemaStructure(PDO $pdo): void
    {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if (!$dbName) {
            throw new RuntimeException("No database selected for migration verification.");
        }

        $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ?");
        $stmt->execute([$dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $required = ['datasets', 'transactions', 'transaction_items', 'experiment_runs', 'experiment_run_levels'];
        foreach ($required as $tbl) {
            if (!in_array($tbl, $tables, true)) {
                throw new RuntimeException("Migration verification failed: Table '{$tbl}' missing in database '{$dbName}'.");
            }
        }
    }
}
