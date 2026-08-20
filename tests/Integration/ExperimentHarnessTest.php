<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Experiments\DatasetInspector;
use App\Experiments\EnvironmentCollector;
use App\Experiments\LineageHelper;
use App\Experiments\MiningExperimentRunner;
use App\Experiments\MiningResultProcessor;
use RuntimeException;

class ExperimentHarnessTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
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

        $repoRoot = dirname(__DIR__, 2);
        $baseTemp = is_dir('D:/tmp') ? 'D:/tmp' : sys_get_temp_dir();
        $tempDir = $baseTemp . '/fim_experiment_test_' . uniqid('', true);
        mkdir($tempDir, 0777, true);

        try {
            // 1. LineageHelper Tests
            $gitSha = LineageHelper::getGitHeadSha($repoRoot);
            $assert(
                "LineageHelper returns 40-character git commit SHA",
                $gitSha !== null && strlen($gitSha) === 40,
                "Got: " . ($gitSha ?? 'null')
            );

            $tinyFile = $repoRoot . '/tests/fixtures/tiny.csv';
            $tinySha = LineageHelper::hashFile($tinyFile);
            $assert(
                "LineageHelper matches exact SHA-256 for tiny.csv",
                $tinySha === '63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e'
            );

            // 2. DatasetInspector Tests
            $inspector = new DatasetInspector();
            $stats = $inspector->inspect($tinyFile, 'basket_csv');
            $assert("DatasetInspector reports transaction_count = 4", $stats['transaction_count'] === 4);
            $assert("DatasetInspector reports unique_item_count = 3", $stats['unique_item_count'] === 3);
            $assert("DatasetInspector reports raw_byte_size = 15", $stats['raw_byte_size'] === 15);
            $assert("DatasetInspector reports data_lines = 4", $stats['data_lines'] === 4);
            $assert("DatasetInspector reports blank_lines = 0", $stats['blank_lines'] === 0);

            // 3. EnvironmentCollector Tests
            $env = EnvironmentCollector::collect($repoRoot . '/experiments/configs/mushroom_experiment_config.json', $tinyFile);
            $assert("EnvironmentCollector status is 'MEASURED'", $env['status'] === 'MEASURED');
            $assert("EnvironmentCollector captures os_name", !empty($env['system']['os_name']));
            $assert("EnvironmentCollector captures php_version", !empty($env['runtime']['php_version']));
            $assert("EnvironmentCollector computes dataset SHA", $env['provenance_hashes']['dataset_sha256'] === $tinySha);
            $assert("EnvironmentCollector leaves browser metrics null", $env['visualization_environment']['browser_name'] === null);

            // 4. Output Safety Gates
            $runner = new MiningExperimentRunner();
            $safetyViolated = false;
            try {
                $runner->execute([
                    'mode' => 'smoke',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'output_dir' => $repoRoot . '/experiments/raw',
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'SAFETY VIOLATION')) {
                    $safetyViolated = true;
                }
            }
            $assert("Smoke mode is prevented from outputting to experiments/raw", $safetyViolated);

            $formalBlocked = false;
            try {
                $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $repoRoot . '/experiments/configs',
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'FORMAL GATE FAILURE')) {
                    $formalBlocked = true;
                }
            }
            $assert("Formal mode rejects unverified template dataset manifest", $formalBlocked);

            // 4a. Formal Environment Lineage Gates
            $manifestMockDir = $tempDir . '/mock_manifests';
            mkdir($manifestMockDir, 0777, true);

            // Valid dataset manifest for lineage test
            $validDsManifest = [
                'schema_version' => '1.0.0',
                'datasets' => [
                    [
                        'canonical_name' => 'Mushroom',
                        'raw_sha256' => $tinySha,
                        'status' => 'VERIFIED_FROZEN',
                        'ingestion_profile' => 'basket_csv',
                    ]
                ]
            ];
            file_put_contents($manifestMockDir . '/dataset_manifest.json', json_encode($validDsManifest));

            $baseEnv = [
                'schema_version' => '1.0.0',
                'status' => 'MEASURED',
                'timestamp_utc' => '2026-08-20T09:00:00Z',
                'system' => ['os_name' => 'Windows', 'architecture' => 'x86_64'],
                'runtime' => ['php_version' => '8.3.0', 'php_sapi' => 'cli', 'memory_limit' => '512M'],
                'visualization_environment' => ['browser_name' => null],
                'provenance_hashes' => [
                    'experiment_config_sha256' => LineageHelper::hashFile($repoRoot . '/experiments/configs/mushroom_experiment_config.json'),
                    'dataset_sha256' => $tinySha,
                ]
            ];

            // Case A: placeholder config SHA
            $envA = $baseEnv;
            $envA['provenance_hashes']['experiment_config_sha256'] = 'TO_BE_COMPUTED';
            file_put_contents($manifestMockDir . '/environment_manifest.json', json_encode($envA));
            $rejA = false;
            try {
                $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $manifestMockDir,
                    'profile' => 'basket_csv',
                    'dry_run' => true,
                    'skip_worktree_check' => true,
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'experiment_config_sha256 is invalid or placeholder')) {
                    $rejA = true;
                }
            }
            $assert("Formal gate rejects MEASURED environment manifest with placeholder config SHA", $rejA);

            // Case B: placeholder dataset SHA
            $envB = $baseEnv;
            $envB['provenance_hashes']['dataset_sha256'] = 'TO_BE_COMPUTED';
            file_put_contents($manifestMockDir . '/environment_manifest.json', json_encode($envB));
            $rejB = false;
            try {
                $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $manifestMockDir,
                    'profile' => 'basket_csv',
                    'dry_run' => true,
                    'skip_worktree_check' => true,
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'dataset_sha256 is invalid or placeholder')) {
                    $rejB = true;
                }
            }
            $assert("Formal gate rejects MEASURED environment manifest with placeholder dataset SHA", $rejB);

            // Case C: mismatched config SHA
            $envC = $baseEnv;
            $envC['provenance_hashes']['experiment_config_sha256'] = str_repeat('c', 64);
            file_put_contents($manifestMockDir . '/environment_manifest.json', json_encode($envC));
            $rejC = false;
            try {
                $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $manifestMockDir,
                    'profile' => 'basket_csv',
                    'dry_run' => true,
                    'skip_worktree_check' => true,
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'config SHA mismatch')) {
                    $rejC = true;
                }
            }
            $assert("Formal gate rejects MEASURED environment manifest with mismatched config SHA", $rejC);

            // Case D: mismatched dataset SHA
            $envD = $baseEnv;
            $envD['provenance_hashes']['dataset_sha256'] = str_repeat('d', 64);
            file_put_contents($manifestMockDir . '/environment_manifest.json', json_encode($envD));
            $rejD = false;
            try {
                $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $manifestMockDir,
                    'profile' => 'basket_csv',
                    'dry_run' => true,
                    'skip_worktree_check' => true,
                ]);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'dataset SHA mismatch')) {
                    $rejD = true;
                }
            }
            $assert("Formal gate rejects MEASURED environment manifest with mismatched dataset SHA", $rejD);

            // Case E: matching hashes passes lineage check in dry-run
            $envE = $baseEnv;
            file_put_contents($manifestMockDir . '/environment_manifest.json', json_encode($envE));
            $passE = false;
            try {
                $resE = $runner->execute([
                    'mode' => 'formal',
                    'dataset_path' => $tinyFile,
                    'config_path' => $repoRoot . '/experiments/configs/mushroom_experiment_config.json',
                    'manifest_dir' => $manifestMockDir,
                    'profile' => 'basket_csv',
                    'dry_run' => true,
                    'skip_worktree_check' => true,
                ]);
                $passE = ($resE['summary_stats']['dry_run'] === true);
            } catch (\Throwable $e) {
                $passE = false;
            }
            $assert("Formal gate passes when environment manifest provenance hashes match exactly", $passE);

            // 5. Deterministic Smoke Run with Tiny Fixture (Oracle Verification)
            // Tiny oracle config: min_support = 0.50 (reqCount = 2 on N=4), min_confidence = 0.50
            $smokeConfigPath = $tempDir . '/tiny_test_config.json';
            file_put_contents($smokeConfigPath, json_encode([
                'schema_version' => '1.0.0',
                'experiment_id' => 'EXP-TINY-TEST-V1',
                'dataset' => 'Tiny',
                'ingestion_profile' => 'basket_csv',
                'min_support' => [0.50],
                'min_confidence' => 0.75,
                'warmup_iterations' => 1,
                'formal_repetitions' => 3,
                'timing_summary' => [
                    'primary' => 'median',
                    'dispersion' => 'IQR',
                ],
                'run_order' => [
                    'strategy' => 'deterministic_shuffle',
                    'seed' => 42,
                ],
                'guards' => [
                    'timeout_seconds' => 5,
                    'max_candidates' => 10000,
                    'max_rules' => 1000,
                ],
            ], JSON_PRETTY_PRINT));

            $smokeOutDir = $tempDir . '/smoke_out';
            $execResult = $runner->execute([
                'mode' => 'smoke',
                'dataset_path' => $tinyFile,
                'config_path' => $smokeConfigPath,
                'output_dir' => $smokeOutDir,
                'prefix' => 'tiny_test',
            ]);

            $assert("Tiny test runs count equals 3", $execResult['runs_count'] === 3);
            $assert("Tiny test levels count equals 9 (3 repeats * 3 levels)", $execResult['levels_count'] === 9);

            // Check Raw CSV content for exact oracle values
            $runsCsv = MiningResultProcessor::readCsv($execResult['runs_file']);
            $assert("Runs CSV contains 3 rows", count($runsCsv) === 3);
            $firstRun = $runsCsv[0];
            $assert("Mining status is COMPLETED", $firstRun['mining_status'] === 'COMPLETED');
            $assert("Rule status is COMPLETED", $firstRun['rule_status'] === 'COMPLETED');
            $assert("Tiny oracle frequent itemsets equals 5", (int)$firstRun['frequent_itemsets'] === 5);
            $assert("Tiny oracle rules count equals 2", (int)$firstRun['rules_count'] === 2);
            $assert("Tiny oracle candidates generated equals 7", (int)$firstRun['candidates_generated'] === 7);
            $assert("Tiny oracle candidates pruned equals 1", (int)$firstRun['candidates_pruned'] === 1);
            $assert("Tiny oracle candidates evaluated equals 6", (int)$firstRun['candidates_evaluated'] === 6);
            $assert("Tiny oracle max_k equals 2", (int)$firstRun['max_k'] === 2);

            $levelsCsv = MiningResultProcessor::readCsv($execResult['levels_file']);
            $assert("Levels CSV contains 9 rows", count($levelsCsv) === 9);

            // Level 1: generated 3, pruned 0, evaluated 3, frequent 3
            $assert("Tiny Level 1: 3 generated, 3 frequent", (int)$levelsCsv[0]['k'] === 1 && (int)$levelsCsv[0]['generated'] === 3 && (int)$levelsCsv[0]['frequent'] === 3);
            // Level 2: generated 3, pruned 0, evaluated 3, frequent 2
            $assert("Tiny Level 2: 3 generated, 2 frequent", (int)$levelsCsv[1]['k'] === 2 && (int)$levelsCsv[1]['generated'] === 3 && (int)$levelsCsv[1]['frequent'] === 2);
            // Level 3: generated 1, pruned 1, evaluated 0, frequent 0
            $assert("Tiny Level 3: 1 generated, 1 pruned, 0 frequent", (int)$levelsCsv[2]['k'] === 3 && (int)$levelsCsv[2]['generated'] === 1 && (int)$levelsCsv[2]['pruned'] === 1 && (int)$levelsCsv[2]['frequent'] === 0);

            // 6. Result Processor Integration on Smoke Output
            $processor = new MiningResultProcessor();
            $procResult = $processor->process(
                $execResult['runs_file'],
                $execResult['levels_file'],
                $smokeOutDir,
                'tiny_test',
                'Tiny'
            );

            $assert("Processor completed_runs equals 3", $procResult['completed_runs'] === 3);
            $assert("Support summary CSV file created", is_file($procResult['support_summary_file']));
            $assert("Pruning summary CSV file created", is_file($procResult['pruning_summary_file']));

            $rawSupHeader = trim((string)explode("\n", (string)file_get_contents($procResult['support_summary_file']))[0]);
            $expectedSupHeader = 'dataset_name,min_support,min_confidence,n_repeats,n_valid,median_runtime_ms,iqr_runtime_ms,median_rule_runtime_ms,iqr_rule_runtime_ms,candidates_generated,candidates_pruned,candidates_evaluated,frequent_itemsets,rules_count,max_k,pruning_ratio';
            $assert("Support summary CSV matches exact schema header", $rawSupHeader === $expectedSupHeader, "Got: {$rawSupHeader}");

            $rawPruneHeader = trim((string)explode("\n", (string)file_get_contents($procResult['pruning_summary_file']))[0]);
            $expectedPruneHeader = 'dataset_name,min_support,k,source,generated,pruned,evaluated,frequent,pruning_ratio';
            $assert("Pruning summary CSV matches exact schema header", $rawPruneHeader === $expectedPruneHeader, "Got: {$rawPruneHeader}");

            $supSummary = MiningResultProcessor::readCsv($procResult['support_summary_file']);
            $assert("Support summary has 1 row", count($supSummary) === 1);
            $assert("Support summary dataset_name equals 'Tiny'", $supSummary[0]['dataset_name'] === 'Tiny');
            $assert("Support summary min_confidence equals 0.75", (float)$supSummary[0]['min_confidence'] === 0.75);
            $assert("Support summary n_repeats equals 3", (int)$supSummary[0]['n_repeats'] === 3);
            $assert("Support summary n_valid equals 3", (int)$supSummary[0]['n_valid'] === 3);
            $assert("Support summary frequent_itemsets equals 5", (int)$supSummary[0]['frequent_itemsets'] === 5);
            $assert("Support summary rules_count equals 2", (int)$supSummary[0]['rules_count'] === 2);
            $assert("Support summary candidates_generated equals 7", (int)$supSummary[0]['candidates_generated'] === 7);
            $assert("Support summary candidates_pruned equals 1", (int)$supSummary[0]['candidates_pruned'] === 1);
            $assert("Support summary candidates_evaluated equals 6", (int)$supSummary[0]['candidates_evaluated'] === 6);
            $assert("Support summary max_k equals 2", (int)$supSummary[0]['max_k'] === 2);
            $assert("Support summary pruning_ratio is ~0.142857", abs((float)$supSummary[0]['pruning_ratio'] - 0.142857) < 0.0001);

            // 7. Median & IQR Mathematics
            $assert("Median of [10, 20, 30] is 20.0", MiningResultProcessor::calculateMedian([10.0, 20.0, 30.0]) === 20.0);
            $assert("Median of [10, 20, 30, 40] is 25.0", MiningResultProcessor::calculateMedian([10.0, 20.0, 30.0, 40.0]) === 25.0);
            $assert("IQR of 8 items [1..8] is 4.0", MiningResultProcessor::calculateIqr([1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0]) === 4.0);

            // 8. Result Processor Invariant Rejections
            // Test corrupted level sum
            $corruptRuns = $runsCsv;
            $corruptRuns[0]['candidates_generated'] = '999';
            $corruptRunsPath = $tempDir . '/corrupt_runs.csv';
            MiningExperimentRunner::writeCsv($corruptRunsPath, array_keys($corruptRuns[0]), $corruptRuns);

            $corruptRejected = false;
            try {
                $processor->process($corruptRunsPath, $execResult['levels_file'], $tempDir, 'corrupt');
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'Reconciliation failure')) {
                    $corruptRejected = true;
                }
            }
            $assert("Processor rejects runs with mismatched candidate totals", $corruptRejected);

            // Test non-deterministic count across repeats
            $nondetRuns = $runsCsv;
            $nondetRuns[1]['frequent_itemsets'] = '6';
            $nondetRunsPath = $tempDir . '/nondet_runs.csv';
            MiningExperimentRunner::writeCsv($nondetRunsPath, array_keys($nondetRuns[0]), $nondetRuns);

            $nondetLevels = $levelsCsv;
            // Modify OBS-00002 level k=2 frequent from 2 to 3 (so level sum = 3 + 3 + 0 = 6)
            $nondetLevels[4]['frequent'] = '3';
            $nondetLevelsPath = $tempDir . '/nondet_levels.csv';
            MiningExperimentRunner::writeCsv($nondetLevelsPath, array_keys($nondetLevels[0]), $nondetLevels);

            $nondetRejected = false;
            try {
                $processor->process($nondetRunsPath, $nondetLevelsPath, $tempDir, 'nondet');
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'Non-deterministic mining counts')) {
                    $nondetRejected = true;
                }
            }
            $assert("Processor rejects non-deterministic frequent itemset counts across repeats", $nondetRejected);

        } finally {
            // Clean up temporary test files
            self::recursiveRemoveDir($tempDir);
        }

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function recursiveRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::recursiveRemoveDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
