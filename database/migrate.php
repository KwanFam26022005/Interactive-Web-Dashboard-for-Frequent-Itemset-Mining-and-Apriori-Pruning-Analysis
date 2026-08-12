<?php

declare(strict_types=1);

/**
 * CLI Migration Script for Phase 2B
 * Usage:
 *   php database/migrate.php [development|test]
 */

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\Persistence\ConnectionFactory;
use App\Persistence\Migrator;

$targetEnv = $argv[1] ?? 'development';

if (!in_array($targetEnv, ['development', 'test'], true)) {
    fwrite(STDERR, "Error: Invalid target environment '{$targetEnv}'. Use 'development' or 'test'.\n");
    exit(1);
}

// Set process environment variable for configuration loading
putenv("APP_ENV={$targetEnv}");
if ($targetEnv === 'test') {
    putenv("DB_NAME=fim_dashboard_test");
}

try {
    $config = require dirname(__DIR__) . '/config/app.php';
    $pdo = ConnectionFactory::create($config['db']);
    $executed = Migrator::run($pdo, __DIR__ . '/migrations');

    echo "[MIGRATE] Target database: {$config['db']['name']} ({$config['app_env']})\n";
    foreach ($executed as $file) {
        echo " - Executed: {$file}\n";
    }
    echo "[MIGRATE] Migration completed successfully.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[MIGRATE FAIL] " . $e->getMessage() . "\n");
    exit(1);
}
