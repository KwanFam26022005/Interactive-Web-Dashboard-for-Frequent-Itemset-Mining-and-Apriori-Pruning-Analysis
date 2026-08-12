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
    $probe = new \App\Tests\Unit\AutoloadProbe();
    assertTest('Test B - Autoload', $probe->getValue() === 'fixture_ok');
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
echo "Phase 2B Configuration & Unit Tests\n";
echo "========================================\n\n";

try {
    $configRes = \App\Tests\Unit\ConfigTest::run();
    foreach ($configRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $configRes['passed'];
    $failed += $configRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ConfigTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $connRes = \App\Tests\Unit\ConnectionFactoryTest::run();
    foreach ($connRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $connRes['passed'];
    $failed += $connRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ConnectionFactoryTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2B Schema Integration Tests\n";
echo "========================================\n\n";

try {
    // Force test environment for schema integration test execution
    putenv('APP_ENV=test');
    putenv('DB_NAME=fim_dashboard_test');

    $schemaRes = \App\Tests\Unit\SchemaTest::run();
    foreach ($schemaRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $schemaRes['passed'];
    $failed += $schemaRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] SchemaTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2C Canonical Dataset & Domain Unit Tests\n";
echo "========================================\n\n";

try {
    $normRes = \App\Tests\Unit\ItemNormalizerTest::run();
    foreach ($normRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $normRes['passed'];
    $failed += $normRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ItemNormalizerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $txRes = \App\Tests\Unit\CanonicalTransactionTest::run();
    foreach ($txRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $txRes['passed'];
    $failed += $txRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] CanonicalTransactionTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $decoderRes = \App\Tests\Unit\CsvRecordDecoderTest::run();
    foreach ($decoderRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $decoderRes['passed'];
    $failed += $decoderRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] CsvRecordDecoderTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2C Parser & Registry Unit Tests\n";
echo "========================================\n\n";

try {
    $regRes = \App\Tests\Parser\ParserRegistryTest::run();
    foreach ($regRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $regRes['passed'];
    $failed += $regRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ParserRegistryTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $csvRes = \App\Tests\Parser\BasketCsvParserTest::run();
    foreach ($csvRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $csvRes['passed'];
    $failed += $csvRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] BasketCsvParserTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $txtRes = \App\Tests\Parser\BasketTextParserTest::run();
    foreach ($txtRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $txtRes['passed'];
    $failed += $txtRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] BasketTextParserTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $mushRes = \App\Tests\Parser\MushroomParserTest::run();
    foreach ($mushRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $mushRes['passed'];
    $failed += $mushRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] MushroomParserTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2C Oracle Fixture & Determinism Tests\n";
echo "========================================\n\n";

try {
    $oracleRes = \App\Tests\Oracle\TinyFixtureTest::run();
    foreach ($oracleRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $oracleRes['passed'];
    $failed += $oracleRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] TinyFixtureTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Summary: {$passed} passed, {$failed} failed.\n";
echo "========================================\n";

exit($failed === 0 ? 0 : 1);
