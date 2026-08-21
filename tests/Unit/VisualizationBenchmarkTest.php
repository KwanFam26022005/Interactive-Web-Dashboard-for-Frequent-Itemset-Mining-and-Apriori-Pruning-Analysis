<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Experiments\ConfigValidator;
use App\Experiments\LineageHelper;
use App\Experiments\MiningResultProcessor;
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
        $assert('Schedule seed is 42', $scheduleSeed === 42);
        $assert('Workload seed != Schedule seed', $workloadSeed !== (string)$scheduleSeed && ($visCfgData['workloads']['seed_decimal'] ?? 0) !== $scheduleSeed);

        // 5. Single Workload Artifact & Legacy File Prohibition
        $canonicalWorkloadFile = $visDir . '/workload_data.json';
        $assert('Single canonical workload_data.json artifact exists', is_file($canonicalWorkloadFile));
        $assert('Formal config does NOT contain legacy workload files map', !isset($visCfgData['workloads']['files']));

        // 6. Workload Invariants: 50% Displacement, Coordinate Bounds, Point Identity
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
                    // Check displacement formula: y_i <- round(fmod(y_i + 0.1, 1.0), 6)
                    $expectedY = round(fmod($base['y'] + 0.1, 1.0), 6);
                    $assert("Point {$base['id']} correctly displaced via y+0.1 mod 1.0 in N={$size}", abs($update['y'] - $expectedY) < 0.00001);
                }
            }

            $assert("Workload N={$size} all coordinates remain in [0, 1]", $allWithinBounds);
            $assert("Workload N={$size} has exactly 50% (N/2) displaced points ({$displacedCount})", $displacedCount === ($size / 2));
            $assert("Workload N={$size} has exactly 50% (N/2) identical points ({$identicalCount})", $identicalCount === ($size / 2));
        }

        // 7. Settle Contract, GC Policy & Timing Boundary in Harness
        $assert('benchmark.js specifies 100 ms settle delay', str_contains($benchmarkJs, 'settle(100)') || str_contains($benchmarkJs, 'settle(ms = 100)'));
        $assert('benchmark.js contains NO forced GC calls (window.gc)', !str_contains($benchmarkJs, 'window.gc') && !str_contains($benchmarkJs, 'global.gc'));
        $assert('benchmark.js uses performance.now() and double-rAF boundary', str_contains($benchmarkJs, 'performance.now()') && str_contains($benchmarkJs, 'requestAnimationFrame'));
        $assert('Warmup iterations = 2 in config', ($visCfgData['warmup_iterations'] ?? 0) === 2);
        $assert('Formal repetitions = 10 in config', ($visCfgData['formal_repetitions'] ?? 0) === 10);

        // 8. Result Processor: Zero-Valid Group & Contract Hardening
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
