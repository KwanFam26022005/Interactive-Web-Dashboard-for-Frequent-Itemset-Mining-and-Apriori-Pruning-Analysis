<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$failed = 0;
$passed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $failed, $passed;
    if ($condition) {
        echo "[PASS] {$name}\n";
        $passed++;
    } else {
        echo "[FAIL] {$name}" . ($details ? ": {$details}" : "") . "\n";
        $failed++;
    }
}

echo "========================================\n";
echo "Phase 2A Harness Smoke Tests\n";
echo "========================================\n\n";

// Test A — PHP bootstrap
assertTest('Test A - PHP bootstrap', defined('APP_ROOT') && APP_ROOT === dirname(__DIR__));

// Test B — Autoload
try {
    $fixture = new \App\Tests\Fixtures\DummyFixture();
    assertTest('Test B - Autoload', $fixture->getValue() === 'fixture_ok');
} catch (\Throwable $e) {
    assertTest('Test B - Autoload', false, $e->getMessage());
}

// Test C — Environment isolation
$envExampleExists = file_exists(dirname(__DIR__) . '/.env.example');
$gitCheckEnv = shell_exec('git check-ignore .env');
$envIgnored = ($gitCheckEnv !== null && trim($gitCheckEnv) !== '');
$gitCheckEnvExample = shell_exec('git check-ignore .env.example');
$envExampleNotIgnored = ($gitCheckEnvExample === null || trim($gitCheckEnvExample) === '');

assertTest(
    'Test C - Environment isolation',
    $envExampleExists && $envIgnored && $envExampleNotIgnored,
    ".env.example exists: " . ($envExampleExists ? "yes" : "no") .
    ", .env ignored: " . ($envIgnored ? "yes" : "no") .
    ", .env.example tracked: " . ($envExampleNotIgnored ? "yes" : "no")
);

// Test D — Public smoke entrypoint
$publicIndex = dirname(__DIR__) . '/public/index.php';
$output = shell_exec('php ' . escapeshellarg($publicIndex));
$bootstrapOk = ($output !== null && str_contains($output, 'project bootstrap operational'));
assertTest(
    'Test D - Public smoke entrypoint',
    $bootstrapOk,
    "Output: " . trim($output ?? '')
);

echo "\n========================================\n";
echo "Summary: {$passed} passed, {$failed} failed.\n";
echo "========================================\n";

exit($failed === 0 ? 0 : 1);
