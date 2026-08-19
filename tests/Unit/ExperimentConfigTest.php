<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Experiments\ConfigValidator;

final class ExperimentConfigTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];
        $assert = static function (string $name, bool $condition, string $message = '') use (
            &$passed,
            &$failed,
            &$results
        ): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}" . ($message === '' ? '' : ": {$message}");
        };

        $configDir = dirname(__DIR__, 2) . '/experiments/configs';

        // Test 1: Production configs validate with 0 errors
        $errors = ConfigValidator::validateAll($configDir);
        $assert(
            'Production Phase 4A configuration artifacts validate with 0 errors',
            $errors === [],
            implode('; ', $errors)
        );

        // Test 2: Valid mushroom config passes
        $mushroomConfigPath = $configDir . '/mushroom_experiment_config.json';
        $mushroomErrors = ConfigValidator::validateMushroomConfig($mushroomConfigPath);
        $assert(
            'Mushroom experiment config validates cleanly',
            $mushroomErrors === [],
            implode('; ', $mushroomErrors)
        );

        // Test 3: Invalid support rejected
        $tmpDir = sys_get_temp_dir() . '/fim_config_test_' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0777, true);

        try {
            $baseConfig = json_decode((string)file_get_contents($mushroomConfigPath), true);

            // 3a. Negative support
            $badConfig = $baseConfig;
            $badConfig['min_support'] = [-0.1, 0.1];
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Negative support value is rejected', count($errs) > 0);

            // 3b. Support > 1.0
            $badConfig = $baseConfig;
            $badConfig['min_support'] = [1.5];
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Support value > 1.0 is rejected', count($errs) > 0);

            // 3c. Support overprecision (>6 decimal places)
            $badConfig = $baseConfig;
            $badConfig['min_support'] = [0.1234567];
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Support exceeding 6 decimal millionths precision is rejected', count($errs) > 0);

            // 4. Invalid confidence rejected
            $badConfig = $baseConfig;
            $badConfig['min_confidence'] = 1.5;
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Confidence value > 1.0 is rejected', count($errs) > 0);

            // 5. Guardrail excess rejected
            $badConfig = $baseConfig;
            $badConfig['guards']['max_candidates'] = 300000;
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Candidate guardrail exceeding 250,000 is rejected', count($errs) > 0);

            $badConfig = $baseConfig;
            $badConfig['guards']['max_rules'] = 60000;
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Rule guardrail exceeding 50,000 is rejected', count($errs) > 0);

            $badConfig = $baseConfig;
            $badConfig['guards']['timeout_seconds'] = 45;
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Timeout exceeding 30 seconds is rejected', count($errs) > 0);

            // 6. Repetitions validation
            $badConfig = $baseConfig;
            $badConfig['formal_repetitions'] = 0;
            file_put_contents($tmpDir . '/test_config.json', json_encode($badConfig));
            $errs = ConfigValidator::validateMushroomConfig($tmpDir . '/test_config.json');
            $assert('Zero formal repetitions is rejected', count($errs) > 0);

            // 7. Dataset manifest unverified consistency
            $manifestPath = $configDir . '/dataset_manifest.json';
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            $manifestErrors = ConfigValidator::validateDatasetManifest($manifestPath);
            $assert(
                'Production dataset manifest validates cleanly as UNVERIFIED template',
                $manifestErrors === [],
                implode('; ', $manifestErrors)
            );

            // Fabricated stats on unverified dataset must fail validation
            $badManifest = $manifest;
            $badManifest['datasets'][0]['imported_transaction_count'] = 8124; // Fake unacquired number
            file_put_contents($tmpDir . '/bad_manifest.json', json_encode($badManifest));
            $errs = ConfigValidator::validateDatasetManifest($tmpDir . '/bad_manifest.json');
            $assert(
                'Unverified dataset manifest with fabricated transaction count is rejected',
                count($errs) > 0
            );

        } finally {
            if (is_dir($tmpDir)) {
                array_map('unlink', glob($tmpDir . '/*') ?: []);
                rmdir($tmpDir);
            }
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
