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
     * and verify exact schema structure using SchemaVerifier.
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

        // Authoritative exact schema verification
        $schemaErrors = SchemaVerifier::verify($pdo);
        if (count($schemaErrors) > 0) {
            throw new RuntimeException("Migration schema verification failed:\n - " . implode("\n - ", $schemaErrors));
        }

        return $executed;
    }
}
