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
        $workloadDir = $repoRoot . '/experiments/visualization/workloads';
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

        // 3. Visual Contract: Axis Tick Contract
        $visCfgData = json_decode((string)file_get_contents($visConfigPath), true);
        $expectedTicks = [0.0, 0.2, 0.4, 0.6, 0.8, 1.0];
        $assert('Visualization config defines exact 6 axis tick values [0.0..1.0]', ($visCfgData['visual_contract']['axis_tick_values'] ?? []) === $expectedTicks);
        $assert('Visualization config specifies gridlines_enabled = false', ($visCfgData['visual_contract']['gridlines_enabled'] ?? null) === false);
        $assert('Visualization config specifies Arial 12 font for axes', ($visCfgData['visual_contract']['axis_font_family'] ?? '') === 'Arial' && ($visCfgData['visual_contract']['axis_font_size'] ?? 0) === 12);

        // 4. Workload Determinism & Materialized Artifact Verification
        $expectedSizes = [100, 1000, 5000, 10000];
        foreach ($expectedSizes as $size) {
            $wData = WorkloadGenerator::generate($size);
            $assert("Workload N={$size} contains {$size} base points", count($wData['base_points']) === $size);
            $assert("Workload N={$size} contains {$size} update points", count($wData['update_points']) === $size);
            $assert("Workload N={$size} domain is [0, 1]", $wData['domain']['x'] === [0, 1] && $wData['domain']['y'] === [0, 1]);

            // Re-run determinism
            $wData2 = WorkloadGenerator::generate($size);
            $assert("Workload N={$size} generation is 100% deterministic", json_encode($wData) === json_encode($wData2));

            // On-disk file SHA match
            $diskFile = $workloadDir . "/workload_{$size}.json";
            if (is_file($diskFile)) {
                $diskBytes = (string)file_get_contents($diskFile);
                $diskSha = hash('sha256', $diskBytes);
                $cfgExpectedSha = $visCfgData['workloads']['files'][(string)$size]['sha256'] ?? '';
                $assert("Disk workload N={$size} matches config SHA-256", $diskSha === $cfgExpectedSha);
            }
        }

        // 5. Result Processor: Zero-Valid Group & Contract Hardening
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
