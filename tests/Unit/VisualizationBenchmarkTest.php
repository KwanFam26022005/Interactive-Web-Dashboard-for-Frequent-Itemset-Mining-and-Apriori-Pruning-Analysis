<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Experiments\ConfigValidator;
use App\Experiments\MiningResultProcessor;
use App\Experiments\VisualizationResultProcessor;
use App\Experiments\WorkloadGenerator;

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

        // 1. Config & Manifest Validation
        $visConfigPath = $configDir . '/visualization_benchmark_config.json';
        $cfgErrs = ConfigValidator::validateVisualizationBenchmarkConfig($visConfigPath);
        $assert('Visualization benchmark config validates cleanly', $cfgErrs === [], implode('; ', $cfgErrs));

        $visLibPath = $configDir . '/visualization_library_manifest.json';
        $libErrs = ConfigValidator::validateVisualizationLibraryManifest($visLibPath);
        $assert('Visualization library manifest validates cleanly', $libErrs === [], implode('; ', $libErrs));

        // 2. Workload Determinism & Point Count
        $expectedSizes = [100, 1000, 5000, 10000];
        foreach ($expectedSizes as $size) {
            $wData = WorkloadGenerator::generate($size);
            $assert("Workload N={$size} contains {$size} base points", count($wData['base_points']) === $size);
            $assert("Workload N={$size} contains {$size} update points", count($wData['update_points']) === $size);
            $assert("Workload N={$size} domain is [0, 1]", $wData['domain']['x'] === [0, 1] && $wData['domain']['y'] === [0, 1]);

            // Range validation
            $inRange = true;
            foreach ($wData['base_points'] as $pt) {
                if ($pt['x'] < 0.0 || $pt['x'] > 1.0 || $pt['y'] < 0.0 || $pt['y'] > 1.0) {
                    $inRange = false;
                    break;
                }
            }
            $assert("Workload N={$size} base points lie in [0, 1]", $inRange);

            // Re-run determinism
            $wData2 = WorkloadGenerator::generate($size);
            $assert("Workload N={$size} generation is 100% deterministic", json_encode($wData) === json_encode($wData2));

            // On-disk file SHA match
            $diskFile = $workloadDir . "/workload_{$size}.json";
            if (is_file($diskFile)) {
                $diskJson = json_decode((string)file_get_contents($diskFile), true);
                $assert("Disk workload N={$size} matches generated data", json_encode($diskJson) === json_encode($wData));
            }
        }

        // 3. Result Processor CSV Transformation Test
        $tmpDir = sys_get_temp_dir() . '/fim_vis_test_' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0777, true);

        try {
            $mockRunsHeader = VisualizationResultProcessor::EXPECTED_RUNS_HEADER;
            $mockRuns = [
                [
                    'OBS-001', 'rev123', 'cfghash', 'wkldhash', 'ECharts', '5.6.0', 'canvas',
                    '100', '1', '1', '12.345', '4.567', 'Chrome', '120.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-002', 'rev123', 'cfghash', 'wkldhash', 'ECharts', '5.6.0', 'canvas',
                    '100', '2', '2', '14.567', '5.678', 'Chrome', '120.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-003', 'rev123', 'cfghash', 'wkldhash', 'D3', '7.9.0', 'svg',
                    '100', '1', '3', '25.123', '10.234', 'Chrome', '120.0', '1440', '900', '1', 'COMPLETED', ''
                ],
                [
                    'OBS-004', 'rev123', 'cfghash', 'wkldhash', 'D3', '7.9.0', 'svg',
                    '100', '2', '4', '27.456', '12.456', 'Chrome', '120.0', '1440', '900', '1', 'COMPLETED', ''
                ]
            ];

            $csvLines = [implode(',', $mockRunsHeader)];
            foreach ($mockRuns as $row) {
                $csvLines[] = implode(',', $row);
            }
            $mockRunsPath = $tmpDir . '/mock_runs.csv';
            file_put_contents($mockRunsPath, implode("\n", $csvLines) . "\n");

            $processor = new VisualizationResultProcessor();
            $procRes = $processor->process($mockRunsPath, $tmpDir, 'test_vis');

            $assert('Visualization processor reports 4 total runs', $procRes['total_runs'] === 4);
            $assert('Visualization processor reports 4 completed runs', $procRes['completed_runs'] === 4);
            $assert('Summary file created', is_file($procRes['summary_file']));

            $summaryRows = MiningResultProcessor::readCsv($procRes['summary_file']);
            $assert('Summary CSV has 2 aggregated rows', count($summaryRows) === 2);

            // D3 row check (median of 25.123 and 27.456 = 26.290)
            $d3Row = array_values(array_filter($summaryRows, fn($r) => $r['library'] === 'D3'))[0] ?? [];
            $assert('D3 summary n_repeats is 2', (int)($d3Row['n_repeats'] ?? 0) === 2);
            $assert('D3 summary n_valid is 2', (int)($d3Row['n_valid'] ?? 0) === 2);
            $assert('D3 summary median_render_ms is ~26.290', abs((float)($d3Row['median_render_ms'] ?? 0) - 26.290) < 0.001);

            // ECharts row check (median of 12.345 and 14.567 = 13.456)
            $ecRow = array_values(array_filter($summaryRows, fn($r) => $r['library'] === 'ECharts'))[0] ?? [];
            $assert('ECharts summary median_render_ms is ~13.456', abs((float)($ecRow['median_render_ms'] ?? 0) - 13.456) < 0.001);

            // Exact header check
            $rawSumHeader = trim((string)explode("\n", (string)file_get_contents($procRes['summary_file']))[0]);
            $expectedSumHeader = implode(',', VisualizationResultProcessor::EXPECTED_SUMMARY_HEADER);
            $assert('Summary CSV exact header matches schema', $rawSumHeader === $expectedSumHeader);

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
