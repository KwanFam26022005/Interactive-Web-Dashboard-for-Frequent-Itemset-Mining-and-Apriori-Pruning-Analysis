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
$phpBinary = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : '';
$binaryValid = ($phpBinary !== '' && is_file($phpBinary));

if (!$binaryValid) {
    assertTest(
        'Test D - Public smoke entrypoint',
        false,
        "PHP_BINARY is invalid or unusable: '" . $phpBinary . "'"
    );
} else {
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($publicIndex);
    $output = shell_exec($command);
    $bootstrapOk = ($output !== null && (str_contains($output, '<!DOCTYPE html>') || str_contains($output, 'project bootstrap operational')));
    assertTest(
        'Test D - Public smoke entrypoint',
        $bootstrapOk,
        "Command: {$command}, Output: " . trim($output ?? '')
    );
}

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
echo "Phase 2D-1 Apriori Mathematical & Domain Primitives Tests\n";
echo "========================================\n\n";

try {
    $itemsetRes = \App\Tests\Unit\ItemsetTest::run();
    foreach ($itemsetRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $itemsetRes['passed'];
    $failed += $itemsetRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ItemsetTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $joinerRes = \App\Tests\Unit\CandidateJoinerTest::run();
    foreach ($joinerRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $joinerRes['passed'];
    $failed += $joinerRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] CandidateJoinerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $prunerRes = \App\Tests\Unit\CandidatePrunerTest::run();
    foreach ($prunerRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $prunerRes['passed'];
    $failed += $prunerRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] CandidatePrunerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $counterRes = \App\Tests\Unit\SupportCounterTest::run();
    foreach ($counterRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $counterRes['passed'];
    $failed += $counterRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] SupportCounterTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $threshRes = \App\Tests\Unit\SupportThresholdTest::run();
    foreach ($threshRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $threshRes['passed'];
    $failed += $threshRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] SupportThresholdTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $filterRes = \App\Tests\Unit\FrequentFilterTest::run();
    foreach ($filterRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $filterRes['passed'];
    $failed += $filterRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] FrequentFilterTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2D-2 Apriori Engine Orchestration & Exact Oracle Tests\n";
echo "========================================\n\n";

try {
    $levelMetricsRes = \App\Tests\Unit\LevelMetricsTest::run();
    foreach ($levelMetricsRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $levelMetricsRes['passed'];
    $failed += $levelMetricsRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] LevelMetricsTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $engineOracleRes = \App\Tests\Oracle\AprioriEngineOracleTest::run();
    foreach ($engineOracleRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $engineOracleRes['passed'];
    $failed += $engineOracleRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] AprioriEngineOracleTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 2E Association Rules & Heatmap Tests\n";
echo "========================================\n\n";

try {
    $ruleRes = \App\Tests\Unit\AssociationRuleTest::run();
    foreach ($ruleRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $ruleRes['passed'];
    $failed += $ruleRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] AssociationRuleTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $ruleGenRes = \App\Tests\Unit\AssociationRuleGeneratorTest::run();
    foreach ($ruleGenRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $ruleGenRes['passed'];
    $failed += $ruleGenRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] AssociationRuleGeneratorTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $heatmapRes = \App\Tests\Unit\HeatmapBuilderTest::run();
    foreach ($heatmapRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $heatmapRes['passed'];
    $failed += $heatmapRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] HeatmapBuilderTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $ruleHeatmapOracleRes = \App\Tests\Oracle\AssociationRuleHeatmapOracleTest::run();
    foreach ($ruleHeatmapOracleRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $ruleHeatmapOracleRes['passed'];
    $failed += $ruleHeatmapOracleRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] AssociationRuleHeatmapOracleTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3A Persistence Integration Tests\n";
echo "========================================\n\n";

try {
    $persistenceRes = \App\Tests\Integration\PersistenceRepositoryTest::run();
    foreach ($persistenceRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $persistenceRes['passed'];
    $failed += $persistenceRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] PersistenceRepositoryTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3B Dataset Import Service Integration Tests\n";
echo "========================================\n\n";

try {
    $datasetImportRes = \App\Tests\Integration\DatasetImportServiceTest::run();
    foreach ($datasetImportRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $datasetImportRes['passed'];
    $failed += $datasetImportRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] DatasetImportServiceTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3C HTTP Infrastructure & Dataset API Tests\n";
echo "========================================\n\n";

try {
    $httpInfrastructureRes = \App\Tests\Api\HttpInfrastructureTest::run();
    foreach ($httpInfrastructureRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $httpInfrastructureRes['passed'];
    $failed += $httpInfrastructureRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] HttpInfrastructureTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $datasetControllerRes = \App\Tests\Api\DatasetControllerTest::run();
    foreach ($datasetControllerRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $datasetControllerRes['passed'];
    $failed += $datasetControllerRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] DatasetControllerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $datasetHttpRes = \App\Tests\Api\DatasetHttpIntegrationTest::run();
    foreach ($datasetHttpRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $datasetHttpRes['passed'];
    $failed += $datasetHttpRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] DatasetHttpIntegrationTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3D Mining HTTP API Tests\n";
echo "========================================\n\n";

try {
    $miningValidatorRes = \App\Tests\Api\MiningRequestValidatorTest::run();
    foreach ($miningValidatorRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $miningValidatorRes['passed'];
    $failed += $miningValidatorRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] MiningRequestValidatorTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $miningAssemblerRes = \App\Tests\Api\MiningResponseAssemblerTest::run();
    foreach ($miningAssemblerRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $miningAssemblerRes['passed'];
    $failed += $miningAssemblerRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] MiningResponseAssemblerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $miningControllerRes = \App\Tests\Api\MiningControllerTest::run();
    foreach ($miningControllerRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $miningControllerRes['passed'];
    $failed += $miningControllerRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] MiningControllerTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

try {
    $miningHttpRes = \App\Tests\Api\MiningHttpIntegrationTest::run();
    foreach ($miningHttpRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $miningHttpRes['passed'];
    $failed += $miningHttpRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] MiningHttpIntegrationTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3E Dashboard Shell & Offline Asset Tests\n";
echo "========================================\n\n";

try {
    $shellRes = \App\Tests\Frontend\DashboardShellTest::run();
    foreach ($shellRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $shellRes['passed'];
    $failed += $shellRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] DashboardShellTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 3F Dashboard AJAX & ECharts Integration Tests\n";
echo "========================================\n\n";

try {
    $dashboardRes = \App\Tests\Frontend\DashboardIntegrationTest::run();
    foreach ($dashboardRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $dashboardRes['passed'];
    $failed += $dashboardRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] DashboardIntegrationTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 4A Experiment Configuration Tests\n";
echo "========================================\n\n";

try {
    $configRes = \App\Tests\Unit\ExperimentConfigTest::run();
    foreach ($configRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $configRes['passed'];
    $failed += $configRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ExperimentConfigTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 4B Experiment Harness Integration Tests\n";
echo "========================================\n\n";

try {
    $harnessRes = \App\Tests\Integration\ExperimentHarnessTest::run();
    foreach ($harnessRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $harnessRes['passed'];
    $failed += $harnessRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] ExperimentHarnessTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Phase 4D Visualization Benchmark Tests\n";
echo "========================================\n\n";

try {
    $visRes = \App\Tests\Unit\VisualizationBenchmarkTest::run();
    foreach ($visRes['results'] as $resLine) {
        echo "{$resLine}\n";
    }
    $passed += $visRes['passed'];
    $failed += $visRes['failed'];
} catch (\Throwable $e) {
    echo "[FAIL] VisualizationBenchmarkTest execution error: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================\n";
echo "Summary: {$passed} passed, {$failed} failed.\n";
echo "========================================\n";

exit($failed === 0 ? 0 : 1);



