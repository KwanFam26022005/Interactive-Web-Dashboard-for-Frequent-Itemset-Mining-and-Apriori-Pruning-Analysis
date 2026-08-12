<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Persistence\ConnectionFactory;
use PDO;
use RuntimeException;

class ConnectionFactoryTest
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

        // 1. Test Valid Connection
        $config = [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'fim_dashboard_test',
            'user' => 'root',
            'password' => '',
        ];

        try {
            $pdo = ConnectionFactory::create($config);
            $assert('ConnectionFactory creates valid PDO instance', $pdo instanceof PDO);

            // Verify PDO attributes
            $assert(
                'PDO ERRMODE is ERRMODE_EXCEPTION',
                $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION
            );
            $emulatePrepares = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
            $assert(
                'PDO EMULATE_PREPARES is false',
                $emulatePrepares === false
            );
            $assert(
                'PDO DEFAULT_FETCH_MODE is FETCH_ASSOC',
                $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) === PDO::FETCH_ASSOC
            );
            $assert(
                'PDO session time zone is UTC',
                $pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00'
            );
        } catch (\Throwable $e) {
            $assert('Valid ConnectionFactory create', false, $e->getMessage());
        }

        // 2. Test Invalid Connection (invalid host/user/password)
        $invalidSecretPass = 'SecretPassword123!_DO_NOT_LEAK';
        $invalidConfig = [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'non_existent_db_12345',
            'user' => 'invalid_user_99',
            'password' => $invalidSecretPass,
        ];

        $caught = false;
        $hasSecret = false;
        try {
            ConnectionFactory::create($invalidConfig);
        } catch (RuntimeException $e) {
            $caught = true;
            $hasSecret = str_contains($e->getMessage(), $invalidSecretPass);
        } catch (\Throwable $e) {
            $caught = true;
            $hasSecret = str_contains($e->getMessage(), $invalidSecretPass);
        }

        $assert('Invalid connection fails safely', $caught);
        $assert('Exception message redacts password', !$hasSecret);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
