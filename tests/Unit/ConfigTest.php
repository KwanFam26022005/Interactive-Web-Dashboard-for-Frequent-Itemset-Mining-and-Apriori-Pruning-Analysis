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

        // Helper to test single config key failure
        $assertConfigFails = static function (string $key, string $val, string $testName) use ($assert): void {
            $prev = getenv($key);
            putenv("{$key}={$val}");
            $caught = false;
            try {
                require dirname(__DIR__, 2) . '/config/app.php';
            } catch (InvalidArgumentException $e) {
                $caught = true;
                $assert("Password not in error message ({$testName})", !str_contains($e->getMessage(), 'DB_PASSWORD'));
            } finally {
                if ($prev !== false) {
                    putenv("{$key}={$prev}");
                } else {
                    putenv("{$key}");
                }
            }
            $assert($testName, $caught);
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

        // 3. Test Malformed .env Syntax Rejection
        $malformedLines = [
            'BROKEN_LINE_NO_EQUALS',
            '=missing_key',
            'INVALID KEY=spaces_in_key',
        ];

        foreach ($malformedLines as $idx => $badLine) {
            $tmpBad = sys_get_temp_dir() . '/test_bad_' . $idx . '_' . uniqid() . '.env';
            file_put_contents($tmpBad, "VALID_KEY=1\n{$badLine}\n");
            $caughtBad = false;
            try {
                EnvLoader::load($tmpBad);
            } catch (InvalidArgumentException $e) {
                $caughtBad = true;
            } finally {
                @unlink($tmpBad);
            }
            $assert("EnvLoader rejects malformed line '{$badLine}'", $caughtBad);
        }

        // 4. Test Invalid APP_ENV
        $assertConfigFails('APP_ENV', 'production', 'Invalid APP_ENV production throws exception');
        $assertConfigFails('APP_ENV', 'staging', 'Invalid APP_ENV staging throws exception');

        // 5. Test Invalid APP_DEBUG
        $assertConfigFails('APP_DEBUG', 'invalid_bool', 'Invalid APP_DEBUG throws exception');

        // 6. Test DB_PORT Validation
        $assertConfigFails('DB_PORT', '0', 'DB_PORT = 0 throws exception');
        $assertConfigFails('DB_PORT', '70000', 'DB_PORT out of range (70000) throws exception');
        $assertConfigFails('DB_PORT', 'abc', 'DB_PORT malformed non-integer throws exception');

        // 7. Test UPLOAD_MAX_BYTES Validation
        $assertConfigFails('UPLOAD_MAX_BYTES', '0', 'UPLOAD_MAX_BYTES = 0 throws exception');
        $assertConfigFails('UPLOAD_MAX_BYTES', '20000000', 'UPLOAD_MAX_BYTES over max throws exception');
        $assertConfigFails('UPLOAD_MAX_BYTES', 'abc', 'UPLOAD_MAX_BYTES malformed throws exception');

        // 8. Test MINING_TIMEOUT_SECONDS Validation
        $assertConfigFails('MINING_TIMEOUT_SECONDS', '0', 'MINING_TIMEOUT_SECONDS = 0 throws exception');
        $assertConfigFails('MINING_TIMEOUT_SECONDS', '60', 'MINING_TIMEOUT_SECONDS over max throws exception');
        $assertConfigFails('MINING_TIMEOUT_SECONDS', 'abc', 'MINING_TIMEOUT_SECONDS malformed throws exception');

        // 9. Test MINING_MAX_CANDIDATES Validation
        $assertConfigFails('MINING_MAX_CANDIDATES', '0', 'MINING_MAX_CANDIDATES = 0 throws exception');
        $assertConfigFails('MINING_MAX_CANDIDATES', '300000', 'MINING_MAX_CANDIDATES over max throws exception');
        $assertConfigFails('MINING_MAX_CANDIDATES', 'abc', 'MINING_MAX_CANDIDATES malformed throws exception');

        // 10. Test MINING_MAX_RULES Validation
        $assertConfigFails('MINING_MAX_RULES', '0', 'MINING_MAX_RULES = 0 throws exception');
        $assertConfigFails('MINING_MAX_RULES', '60000', 'MINING_MAX_RULES over max throws exception');
        $assertConfigFails('MINING_MAX_RULES', 'abc', 'MINING_MAX_RULES malformed throws exception');

        // Cleanup temporary env vars
        putenv('TEST_CFG_KEY1');
        putenv('TEST_CFG_KEY2');
        putenv('TEST_CFG_OVERRIDE');

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
