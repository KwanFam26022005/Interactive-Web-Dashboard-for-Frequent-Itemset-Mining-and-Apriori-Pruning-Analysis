<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Experiments\ConfigValidator;
use App\Experiments\LineageHelper;
use App\Experiments\MiningResultProcessor;
use App\Experiments\Phase4EvidenceValidator;
use App\Experiments\VisualizationResultProcessor;
use App\Experiments\WorkloadGenerator;
use RuntimeException;

final class VisualizationBenchmarkTest
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

        $repoRoot = dirname(__DIR__, 2);
        $configDir = $repoRoot . '/experiments/configs';
        $visDir = $repoRoot . '/experiments/visualization';

        // 1. Lineage Hardening: Ensure NO Phase-4C mining hashes are hard-coded in benchmark.js
        $benchmarkJs = (string)file_get_contents($visDir . '/benchmark.js');
        $indexHtml = (string)file_get_contents($visDir . '/index.html');
        $hardcodedGitSha = 'fd318b3ca0d3829c0849ee2a5ef783caaae72fdb';
        $hardcodedCfgSha = '47861199a9fb4297904fcdf425c8deb97b90666c3ea1d5f9d2b966a5b47a2b31';
        $assert('benchmark.js does not contain hard-coded Phase-4C Git SHA', !str_contains($benchmarkJs, $hardcodedGitSha));
        $assert('benchmark.js does not contain hard-coded Phase-4C Config SHA', !str_contains($benchmarkJs, $hardcodedCfgSha));

        // 2. Config & Manifest Validation
        $visConfigPath = $configDir . '/visualization_benchmark_config.json';
        $cfgErrs = ConfigValidator::validateVisualizationBenchmarkConfig($visConfigPath);
        $assert('Visualization benchmark config validates cleanly', $cfgErrs === [], implode('; ', $cfgErrs));

        $visLibPath = $configDir . '/visualization_library_manifest.json';
        $libErrs = ConfigValidator::validateVisualizationLibraryManifest($visLibPath);
        $assert('Visualization library manifest validates cleanly', $libErrs === [], implode('; ', $libErrs));

        $visEnvPath = $configDir . '/visualization_environment_manifest.json';
        $envErrs = ConfigValidator::validateVisualizationEnvironmentManifest($visEnvPath);
        $assert('Visualization environment manifest validates cleanly', $envErrs === [], implode('; ', $envErrs));

        // 3. Visual Contract: 800x500 & 5 Fixed Linear Gridlines
        $visCfgData = json_decode((string)file_get_contents($visConfigPath), true);
        $assert('Visualization config container width is exactly 800 px', ($visCfgData['visual_contract']['container_width'] ?? 0) === 800);
        $assert('Visualization config container height is exactly 500 px', ($visCfgData['visual_contract']['container_height'] ?? 0) === 500);

        $expectedGridlines = [0.0, 0.25, 0.5, 0.75, 1.0];
        $assert('Visualization config specifies exactly 5 gridline positions [0.0, 0.25, 0.5, 0.75, 1.0]', ($visCfgData['visual_contract']['gridline_positions'] ?? []) === $expectedGridlines);
        $assert('Visualization config specifies gridlines_count = 5', ($visCfgData['visual_contract']['gridlines_count'] ?? 0) === 5);
        $assert('Visualization config specifies gridlines_enabled = true', ($visCfgData['visual_contract']['gridlines_enabled'] ?? null) === true);
        $assert('Visualization config specifies marker_radius = 4', ($visCfgData['visual_contract']['marker_radius'] ?? 0) === 4);
        $assert('Visualization config specifies marker_opacity = 0.7', abs((float)($visCfgData['visual_contract']['marker_opacity'] ?? 0) - 0.7) < 0.001);
        $assert('Visualization config specifies animation_enabled = false', ($visCfgData['visual_contract']['animation_enabled'] ?? null) === false);
        $assert('Visualization config specifies transitions_enabled = false', ($visCfgData['visual_contract']['transitions_enabled'] ?? null) === false);

        // 4. PRNG & Seed Separation Verification
        $workloadSeed = $visCfgData['workloads']['seed'] ?? '';
        $scheduleSeed = $visCfgData['run_order']['seed'] ?? 0;
        $assert('Workload generator is Mulberry32', ($visCfgData['workloads']['generator'] ?? '') === 'Mulberry32');
        $assert('Workload seed is 0xDEADBEEF', $workloadSeed === '0xDEADBEEF');
        $assert('WorkloadGenerator::SEED is 0xDEADBEEF (3735928559)', WorkloadGenerator::SEED === 0xDEADBEEF && WorkloadGenerator::SEED === 3735928559);
        $assert('Schedule seed is 42', $scheduleSeed === 42);
        $assert('Workload seed != Schedule seed', $workloadSeed !== (string)$scheduleSeed && ($visCfgData['workloads']['seed_decimal'] ?? 0) !== $scheduleSeed);

        // 5. Mulberry32 Known Vectors & Prefix Property
        $testState = 0xDEADBEEF;
        $knownFloats = [0.941370, 0.267196, 0.772033, 0.358161, 0.475542, 0.838231];
        for ($k = 0; $k < count($knownFloats); $k++) {
            $actFloat = WorkloadGenerator::nextFloat($testState);
            $assert("Mulberry32 known vector output {$k} matches {$knownFloats[$k]}", abs($actFloat - $knownFloats[$k]) < 0.000001, "Got {$actFloat}");
        }

        $w100 = WorkloadGenerator::generateWorkloadForSize(100);
        $w1000 = WorkloadGenerator::generateWorkloadForSize(1000);
        $w5000 = WorkloadGenerator::generateWorkloadForSize(5000);
        $w10000 = WorkloadGenerator::generateWorkloadForSize(10000);

        $assert('N=100 Point 1 is {id:1, x:0.94137, y:0.267196}', $w100['base_points'][0]['id'] === 1 && abs($w100['base_points'][0]['x'] - 0.941370) < 0.000001 && abs($w100['base_points'][0]['y'] - 0.267196) < 0.000001);
        $assert('N=100 Point 2 is {id:2, x:0.772033, y:0.358161}', $w100['base_points'][1]['id'] === 2 && abs($w100['base_points'][1]['x'] - 0.772033) < 0.000001 && abs($w100['base_points'][1]['y'] - 0.358161) < 0.000001);

        $assert('N=100 base points are identical prefix of N=1000', array_slice($w1000['base_points'], 0, 100) === $w100['base_points']);
        $assert('N=1000 base points are identical prefix of N=5000', array_slice($w5000['base_points'], 0, 1000) === $w1000['base_points']);
        $assert('N=5000 base points are identical prefix of N=10000', array_slice($w10000['base_points'], 0, 5000) === $w5000['base_points']);

        // 6. Single Workload Artifact & Legacy File Prohibition
        $canonicalWorkloadFile = $visDir . '/workload_data.json';
        $assert('Single canonical workload_data.json artifact exists', is_file($canonicalWorkloadFile));
        $assert('Formal config does NOT contain legacy workload files map', !isset($visCfgData['workloads']['files']));

        // 7. Workload Invariants: 50% Displacement, Coordinate Bounds, Point Identity
        $bundle = json_decode((string)file_get_contents($canonicalWorkloadFile), true);
        $assert('Canonical bundle has schema_version 1.0.0', ($bundle['schema_version'] ?? '') === '1.0.0');
        $assert('Canonical bundle generator is Mulberry32', ($bundle['generator'] ?? '') === 'Mulberry32');
        $assert('Canonical bundle seed is 0xDEADBEEF', ($bundle['seed'] ?? '') === '0xDEADBEEF');

        $expectedSizes = [100, 1000, 5000, 10000];
        foreach ($expectedSizes as $size) {
            $wData = $bundle['workloads'][(string)$size] ?? null;
            $assert("Workload N={$size} exists in bundle", is_array($wData));
            if (!is_array($wData)) continue;

            $basePoints = $wData['base_points'] ?? [];
            $updatePoints = $wData['update_points'] ?? [];

            $assert("Workload N={$size} contains exactly {$size} base points", count($basePoints) === $size);
            $assert("Workload N={$size} contains exactly {$size} update points", count($updatePoints) === $size);

            $displacedCount = 0;
            $identicalCount = 0;
            $allWithinBounds = true;

            for ($i = 0; $i < $size; $i++) {
                $base = $basePoints[$i];
                $update = $updatePoints[$i];

                // Coordinate bounds [0, 1]
                if ($base['x'] < 0.0 || $base['x'] > 1.0 || $base['y'] < 0.0 || $base['y'] > 1.0 ||
                    $update['x'] < 0.0 || $update['x'] > 1.0 || $update['y'] < 0.0 || $update['y'] > 1.0) {
                    $allWithinBounds = false;
                }

                // ID preservation
                $assert("Point {$i} preserves ID in N={$size}", $base['id'] === $update['id'] && $base['id'] === ($i + 1));

                // Check coordinate difference
                if ($base['x'] === $update['x'] && $base['y'] === $update['y']) {
                    $identicalCount++;
                } else {
                    $displacedCount++;
                    // Invariant: x must remain identical, only y is displaced
                    $assert("Point {$base['id']} preserves x coordinate on update in N={$size}", $base['x'] === $update['x']);
                    // Check displacement formula: y_i <- round(fmod(y_i + 0.1, 1.0), 6)
                    $expectedY = round(fmod($base['y'] + 0.1, 1.0), 6);
                    $assert("Point {$base['id']} correctly displaced via y+0.1 mod 1.0 in N={$size}", abs($update['y'] - $expectedY) < 0.00001);
                }
            }

            $assert("Workload N={$size} all coordinates remain in [0, 1]", $allWithinBounds);
            $assert("Workload N={$size} has exactly 50% (N/2) displaced points ({$displacedCount})", $displacedCount === ($size / 2));
            $assert("Workload N={$size} has exactly 50% (N/2) identical points ({$identicalCount})", $identicalCount === ($size / 2));
        }

        // 8. Physical Vendor Files & Library Manifest Semantic Reconciliation
        $visLibData = json_decode((string)file_get_contents($visLibPath), true);
        $libNotes = implode(' ', array_column($visLibData['libraries'] ?? [], 'notes'));
        $assert('Library manifest notes do NOT contain "splitLine disabled"', !str_contains($libNotes, 'splitLine disabled'));
        $assert('Library manifest notes do NOT contain "explicit 6 tickValues"', !str_contains($libNotes, 'explicit 6 tickValues'));
        $assert('Library manifest notes do NOT contain "grid lines disabled"', !str_contains($libNotes, 'grid lines disabled'));
        $assert('Library manifest notes accurately describe 5 fixed linear gridlines', str_contains($libNotes, '5 fixed linear gridlines') || str_contains($libNotes, '5 fixed tick/gridline positions'));

        $expectedVendors = [
            'ECharts' => ['path' => $visDir . '/vendor/echarts/echarts.min.js', 'sha' => 'bf4a223524e40b77c304bec67e1222cf551f14880cf42c69dc046558e11c07b1'],
            'D3' => ['path' => $visDir . '/vendor/d3/d3.min.js', 'sha' => 'f2094bbf6141b359722c4fe454eb6c4b0f0e42cc10cc7af921fc158fceb86539'],
            'Chart.js' => ['path' => $visDir . '/vendor/chartjs/chart.umd.min.js', 'sha' => 'c40877e88de4df7201532014e14fb707f0f07a196a4ec63e070544b80184fb00'],
        ];
        foreach ($expectedVendors as $vName => $vMeta) {
            $assert("Vendor {$vName} physical file exists", is_file($vMeta['path']));
            $actSha = hash_file('sha256', $vMeta['path']);
            $assert("Vendor {$vName} matches authoritative frozen SHA", $actSha === $vMeta['sha']);
        }

        // 9. Environment Manifest Provenance Refreeze & Cross-Checks
        $visEnvData = json_decode((string)file_get_contents($visEnvPath), true);
        $assert('Environment manifest has single workload_data_sha256', isset($visEnvData['provenance_hashes']['workload_data_sha256']));
        $assert('Environment manifest does NOT contain legacy workload_100_sha256', !isset($visEnvData['provenance_hashes']['workload_100_sha256']));
        $assert('Environment manifest does NOT contain legacy workload_1000_sha256', !isset($visEnvData['provenance_hashes']['workload_1000_sha256']));
        $assert('Environment manifest does NOT contain legacy workload_5000_sha256', !isset($visEnvData['provenance_hashes']['workload_5000_sha256']));
        $assert('Environment manifest does NOT contain legacy workload_10000_sha256', !isset($visEnvData['provenance_hashes']['workload_10000_sha256']));

        $actCfgSha = hash_file('sha256', $visConfigPath);
        $actLibSha = hash_file('sha256', $visLibPath);
        $actWkldSha = hash_file('sha256', $canonicalWorkloadFile);
        $assert('Environment manifest benchmark_config_sha256 matches actual config SHA', ($visEnvData['provenance_hashes']['benchmark_config_sha256'] ?? '') === $actCfgSha);
        $assert('Environment manifest library_manifest_sha256 matches actual library manifest SHA', ($visEnvData['provenance_hashes']['library_manifest_sha256'] ?? '') === $actLibSha);
        $assert('Environment manifest workload_data_sha256 matches actual workload SHA', ($visEnvData['provenance_hashes']['workload_data_sha256'] ?? '') === $actWkldSha);

        // Browser Contract Gating in Manifest
        $assert('Environment manifest specifies browser_name = Edge', ($visEnvData['browser_environment']['browser_name'] ?? '') === 'Edge');
        $assert('Environment manifest specifies browser_version = 151.0.0.0', ($visEnvData['browser_environment']['browser_version'] ?? '') === '151.0.0.0');
        $assert('Environment manifest specifies viewport_width = 1440', ($visEnvData['browser_environment']['viewport_width'] ?? 0) === 1440);
        $assert('Environment manifest specifies viewport_height = 900', ($visEnvData['browser_environment']['viewport_height'] ?? 0) === 900);
        $assert('Environment manifest specifies device_pixel_ratio = 1.0', ($visEnvData['browser_environment']['device_pixel_ratio'] ?? 0) === 1.0);
        $assert('Environment manifest specifies display_scaling_factor = 1.0', ($visEnvData['browser_environment']['display_scaling_factor'] ?? 0) === 1.0);

        // 10. Browser Preflight Gating & Static Method Integration in index.html
        $assert('index.html preflight verifies vendor file byte hashes via Web Crypto', str_contains($indexHtml, 'Vendor byte hash mismatch for'));
        $assert('index.html preflight verifies environment manifest hash & cross-checks', str_contains($indexHtml, 'Environment manifest benchmark_config_sha256') && str_contains($indexHtml, 'Environment manifest library_manifest_sha256'));
        $assert('index.html formal preflight enforces Edge 151.0.0.0 and 1440x900 at DPR 1.0', str_contains($indexHtml, "detected.browser_name !== 'Edge'") && str_contains($indexHtml, "151.0.0.0") && str_contains($indexHtml, "device_pixel_ratio !== 1.0"));

        // Static Method Integration: index.html calls valid methods in benchmark.js
        $assert('index.html does NOT call non-existent detectEnvironment()', !str_contains($indexHtml, 'VisualizationBenchmarkRunner.detectEnvironment()'));
        $assert('index.html calls canonical detectBrowserEnvironment()', str_contains($indexHtml, 'VisualizationBenchmarkRunner.detectBrowserEnvironment()'));
        $assert('index.html calls canonical computeSha256()', str_contains($indexHtml, 'VisualizationBenchmarkRunner.computeSha256('));
        $assert('benchmark.js implements static detectBrowserEnvironment', str_contains($benchmarkJs, 'static detectBrowserEnvironment()'));
        $assert('benchmark.js implements static computeSha256', str_contains($benchmarkJs, 'static async computeSha256('));
        $assert('benchmark.js implements static settle', str_contains($benchmarkJs, 'static settle('));
        $assert('benchmark.js implements static measureLatency', str_contains($benchmarkJs, 'static measureLatency('));

        // 11. Settle Contract, GC Policy & Timing Boundary in Harness
        $assert('benchmark.js specifies 100 ms inter-trial settle delay', str_contains($benchmarkJs, 'settle(100)') || str_contains($benchmarkJs, 'settle(ms = 100)'));
        $hasSettleBetweenRenderAndUpdate = (bool)preg_match('/obsRecord\.render_ms\s*=\s*renderMs;[\s\S]*?await\s+VisualizationBenchmarkRunner\.settle[\s\S]*?obsRecord\.update_ms/i', $benchmarkJs);
        $assert('benchmark.js does NOT insert settle(100) between render and update measurements', !$hasSettleBetweenRenderAndUpdate);

        $assert('benchmark.js contains NO forced GC calls (window.gc)', !str_contains($benchmarkJs, 'window.gc') && !str_contains($benchmarkJs, 'global.gc'));
        $assert('benchmark.js uses performance.now() and double-rAF boundary', str_contains($benchmarkJs, 'performance.now()') && str_contains($benchmarkJs, 'requestAnimationFrame'));
        $assert('Warmup iterations = 2 in config', ($visCfgData['warmup_iterations'] ?? 0) === 2);
        $assert('Formal repetitions = 10 in config', ($visCfgData['formal_repetitions'] ?? 0) === 10);

        // 12. Evidence Validator Classification & Replacement Acceptance Tests
        $miningErrs = Phase4EvidenceValidator::validateMiningEvidence($repoRoot);
        $assert('Phase4EvidenceValidator reports 0 errors on canonical mining evidence', $miningErrs === [], implode('; ', $miningErrs));

        $diagErrs = Phase4EvidenceValidator::validateDiagnosticArchive($repoRoot);
        $assert('Phase4EvidenceValidator reports 0 errors on historical diagnostic archive', $diagErrs === [], implode('; ', $diagErrs));

        $rq3ReplacementErrs = Phase4EvidenceValidator::validateReplacementRq3Evidence($repoRoot);
        $assert('Phase4EvidenceValidator reports 0 errors on accepted replacement RQ3 evidence', $rq3ReplacementErrs === [], implode('; ', $rq3ReplacementErrs));

        $rq3Status = Phase4EvidenceValidator::checkCanonicalRq3Status($repoRoot);
        $assert('Phase4EvidenceValidator classifies canonical RQ3 status as ACCEPTED_CANONICAL', ($rq3Status['status'] ?? '') === 'ACCEPTED_CANONICAL');

        $derivStatus = Phase4EvidenceValidator::checkDerivativeStatus($repoRoot);
        $assert('Phase4EvidenceValidator classifies derivative status as CURRENT', ($derivStatus['status'] ?? '') === 'CURRENT');

        $derivErrs = Phase4EvidenceValidator::validateDerivatives($repoRoot);
        $assert('Phase4EvidenceValidator reports 0 errors on canonical derivatives', $derivErrs === [], implode('; ', $derivErrs));

        $replayed = Phase4EvidenceValidator::replayExecutionSchedule($repoRoot);
        $assert('Replayed execution schedule has exactly 120 slots', count($replayed) === 120);

        // 13. Result Processor: Zero-Valid Group & Contract Hardening
        $tmpDir = sys_get_temp_dir() . '/fim_vis_test_' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0777, true);

        try {
            $mockRunsHeader = VisualizationResultProcessor::EXPECTED_RUNS_HEADER;
            $mockRuns = [
                [
                    'OBS-001', 'rev123', 'cfghash', 'wkldhash', 'ECharts', '5.6.0', 'canvas',
                    '100', '1', '1', '12.345', '4.567', 'Edge', '151.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-002', 'rev123', 'cfghash', 'wkldhash', 'ECharts', '5.6.0', 'canvas',
                    '100', '2', '2', '14.567', '5.678', 'Edge', '151.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-003', 'rev123', 'cfghash', 'wkldhash', 'D3', '7.9.0', 'svg',
                    '100', '1', '3', '25.123', '10.234', 'Edge', '151.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-004', 'rev123', 'cfghash', 'wkldhash', 'D3', '7.9.0', 'svg',
                    '100', '2', '4', '', '', 'Edge', '151.0', '1440', '900', '1', 'FAILED', 'Render timeout'
                ],
                // Chart.js with 100% failed runs (Zero-Valid Group test)
                [
                    'OBS-005', 'rev123', 'cfghash', 'wkldhash', 'Chart.js', '4.4.8', 'canvas',
                    '100', '1', '5', '', '', 'Edge', '151.0', '1440', '900', '1', 'FAILED', 'Canvas error, with "quotes"\nand newline'
                ]
            ];

            $csvLines = [implode(',', $mockRunsHeader)];
            foreach ($mockRuns as $row) {
                // Apply RFC 4180 escaping
                $escaped = array_map(static function($val) {
                    $s = (string)$val;
                    if (str_contains($s, ',') || str_contains($s, '"') || str_contains($s, "\n")) {
                        return '"' . str_replace('"', '""', $s) . '"';
                    }
                    return $s;
                }, $row);
                $csvLines[] = implode(',', $escaped);
            }
            $mockRunsPath = $tmpDir . '/mock_runs.csv';
            file_put_contents($mockRunsPath, implode("\n", $csvLines) . "\n");

            $processor = new VisualizationResultProcessor();
            $procRes = $processor->process($mockRunsPath, $tmpDir, 'test_vis');

            $assert('Visualization processor reports 5 total runs', $procRes['total_runs'] === 5);
            $assert('Visualization processor reports 3 completed runs', $procRes['completed_runs'] === 3);
            $assert('Summary file created', is_file($procRes['summary_file']));

            $summaryRows = MiningResultProcessor::readCsv($procRes['summary_file']);
            $assert('Summary CSV has 3 aggregated groups', count($summaryRows) === 3);

            // Chart.js Zero-Valid Group Check (n_repeats=1, n_valid=0, medians are blank)
            $cjsRow = array_values(array_filter($summaryRows, fn($r) => $r['library'] === 'Chart.js'))[0] ?? [];
            $assert('Chart.js zero-valid group n_repeats is 1', (int)($cjsRow['n_repeats'] ?? 0) === 1);
            $assert('Chart.js zero-valid group n_valid is 0', (int)($cjsRow['n_valid'] ?? 0) === 0);
            $assert('Chart.js zero-valid median_render_ms is blank', ($cjsRow['median_render_ms'] ?? 'non-blank') === '');
            $assert('Chart.js zero-valid iqr_render_ms is blank', ($cjsRow['iqr_render_ms'] ?? 'non-blank') === '');

            // D3 Check (1 valid, 1 failed -> n_repeats=2, n_valid=1, median=25.123, iqr=0.000)
            $d3Row = array_values(array_filter($summaryRows, fn($r) => $r['library'] === 'D3'))[0] ?? [];
            $assert('D3 summary n_repeats is 2', (int)($d3Row['n_repeats'] ?? 0) === 2);
            $assert('D3 summary n_valid is 1', (int)($d3Row['n_valid'] ?? 0) === 1);
            $assert('D3 summary median_render_ms is ~25.123', abs((float)($d3Row['median_render_ms'] ?? 0) - 25.123) < 0.001);

            // Invalid header rejection test
            $invalidHeaderFile = $tmpDir . '/invalid_header.csv';
            file_put_contents($invalidHeaderFile, "bad_col_1,bad_col_2\n1,2\n");
            $rejectedHeader = false;
            try {
                $processor->process($invalidHeaderFile, $tmpDir, 'bad_header');
            } catch (RuntimeException $e) {
                $rejectedHeader = true;
            }
            $assert('Processor rejects CSV with invalid raw header schema', $rejectedHeader);

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
