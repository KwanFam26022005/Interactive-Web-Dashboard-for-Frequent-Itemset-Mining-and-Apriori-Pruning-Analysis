<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config\EnvLoader;
use InvalidArgumentException;

class ConfigTest
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

        // 1. Test EnvLoader parsing (comments, blank lines, KEY=VALUE)
        $tmpEnv = sys_get_temp_dir() . '/test_env_' . uniqid() . '.env';
        file_put_contents($tmpEnv, "# Comment line\n\nTEST_CFG_KEY1=val1\nTEST_CFG_KEY2=\"val2\"\n");

        EnvLoader::load($tmpEnv);
        @unlink($tmpEnv);

        $assert('EnvLoader loads key1', getenv('TEST_CFG_KEY1') === 'val1');
        $assert('EnvLoader strips quotes key2', getenv('TEST_CFG_KEY2') === 'val2');

        // 2. Test Process Environment Override (process env > .env)
        putenv('TEST_CFG_OVERRIDE=process_val');
        $tmpEnv2 = sys_get_temp_dir() . '/test_env_' . uniqid() . '.env';
        file_put_contents($tmpEnv2, "TEST_CFG_OVERRIDE=dotenv_val\n");

        EnvLoader::load($tmpEnv2);
        @unlink($tmpEnv2);

        $assert('Process env overrides .env', getenv('TEST_CFG_OVERRIDE') === 'process_val');

        // 3. Test Invalid APP_ENV
        putenv('APP_ENV=production');
        $caught = false;
        try {
            require dirname(__DIR__, 2) . '/config/app.php';
        } catch (InvalidArgumentException $e) {
            $caught = true;
            $assert('Password not in error message', !str_contains($e->getMessage(), 'DB_PASSWORD'));
        } finally {
            putenv('APP_ENV=test');
        }
        $assert('Invalid APP_ENV throws exception', $caught);

        // 4. Test Invalid DB_PORT
        putenv('DB_PORT=99999');
        $caught = false;
        try {
            require dirname(__DIR__, 2) . '/config/app.php';
        } catch (InvalidArgumentException $e) {
            $caught = true;
        } finally {
            putenv('DB_PORT=3306');
        }
        $assert('Invalid DB_PORT throws exception', $caught);

        // 5. Test Mining Guardrails (candidates > 250000)
        putenv('MINING_MAX_CANDIDATES=300000');
        $caught = false;
        try {
            require dirname(__DIR__, 2) . '/config/app.php';
        } catch (InvalidArgumentException $e) {
            $caught = true;
        } finally {
            putenv('MINING_MAX_CANDIDATES=250000');
        }
        $assert('Exceeding MINING_MAX_CANDIDATES throws exception', $caught);

        // Cleanup temporary env vars
        putenv('TEST_CFG_KEY1');
        putenv('TEST_CFG_KEY2');
        putenv('TEST_CFG_OVERRIDE');

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
