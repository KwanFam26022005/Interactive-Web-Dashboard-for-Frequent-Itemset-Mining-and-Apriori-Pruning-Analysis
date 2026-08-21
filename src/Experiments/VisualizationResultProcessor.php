<?php

declare(strict_types=1);

namespace App\Experiments;

use RuntimeException;

/**
 * Result Processor for RQ3 Visualization Benchmark Evidence.
 */
class VisualizationResultProcessor
{
    public const EXPECTED_RUNS_HEADER = [
        'observation_id',
        'git_revision',
        'benchmark_config_sha256',
        'workload_sha256',
        'library',
        'library_version',
        'renderer',
        'workload_size',
        'repeat_index',
        'execution_order_index',
        'render_ms',
        'update_ms',
        'browser_name',
        'browser_version',
        'viewport_width',
        'viewport_height',
        'device_pixel_ratio',
        'status',
        'failure_code',
    ];

    public const EXPECTED_SUMMARY_HEADER = [
        'library',
        'library_version',
        'renderer',
        'workload_size',
        'n_repeats',
        'n_valid',
        'median_render_ms',
        'iqr_render_ms',
        'median_update_ms',
        'iqr_update_ms',
    ];

    public const VALID_LIBRARIES = ['ECharts', 'D3', 'Chart.js'];
    public const VALID_WORKLOAD_SIZES = [100, 1000, 5000, 10000];

    /**
     * Aggregates raw visualization runs into summary metrics.
     *
     * @param string $runsCsvPath Path to raw visualization_runs.csv
     * @param string $outputDir Path to write visualization_summary.csv
     * @param string $prefix Filename prefix
     * @return array{summary_file: string, total_runs: int, completed_runs: int}
     */
    public function process(
        string $runsCsvPath,
        string $outputDir,
        string $prefix = 'visualization'
    ): array {
        if (!is_file($runsCsvPath)) {
            throw new RuntimeException("Runs CSV file not found: {$runsCsvPath}");
        }

        $rawRows = MiningResultProcessor::readCsv($runsCsvPath);
        if ($rawRows === []) {
            throw new RuntimeException("Runs CSV is empty: {$runsCsvPath}");
        }

        // Validate header schema from first line
        $firstLine = trim((string)file($runsCsvPath)[0]);
        $expectedHeaderLine = implode(',', self::EXPECTED_RUNS_HEADER);
        if ($firstLine !== $expectedHeaderLine) {
            throw new RuntimeException("Runs CSV header mismatch. Expected: {$expectedHeaderLine}, Got: {$firstLine}");
        }

        $grouped = [];
        $completedRuns = 0;

        foreach ($rawRows as $idx => $row) {
            $rowNum = $idx + 2;
            $lib = $row['library'] ?? '';
            if (!in_array($lib, self::VALID_LIBRARIES, true)) {
                throw new RuntimeException("Invalid library '{$lib}' on line {$rowNum}");
            }

            $size = (int)($row['workload_size'] ?? 0);
            if (!in_array($size, self::VALID_WORKLOAD_SIZES, true)) {
                throw new RuntimeException("Invalid workload size '{$size}' on line {$rowNum}");
            }

            $ver = $row['library_version'] ?? '';
            $rend = $row['renderer'] ?? '';
            $key = "{$lib}|{$ver}|{$rend}|{$size}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'library' => $lib,
                    'library_version' => $ver,
                    'renderer' => $rend,
                    'workload_size' => $size,
                    'n_repeats' => 0,
                    'valid_renders' => [],
                    'valid_updates' => [],
                ];
            }

            $grouped[$key]['n_repeats']++;
            $status = $row['status'] ?? '';

            if ($status === 'COMPLETED') {
                $renderMs = $row['render_ms'] ?? '';
                $updateMs = $row['update_ms'] ?? '';

                if (!is_numeric($renderMs) || (float)$renderMs < 0.0) {
                    throw new RuntimeException("Invalid render_ms '{$renderMs}' for COMPLETED row on line {$rowNum}");
                }
                if (!is_numeric($updateMs) || (float)$updateMs < 0.0) {
                    throw new RuntimeException("Invalid update_ms '{$updateMs}' for COMPLETED row on line {$rowNum}");
                }

                $completedRuns++;
                $grouped[$key]['valid_renders'][] = (float)$renderMs;
                $grouped[$key]['valid_updates'][] = (float)$updateMs;
            }
        }

        $summaryRows = [];
        foreach ($grouped as $g) {
            $nValid = count($g['valid_renders']);
            $medRender = $nValid > 0 ? MiningResultProcessor::calculateMedian($g['valid_renders']) : null;
            $iqrRender = $nValid > 0 ? MiningResultProcessor::calculateIqr($g['valid_renders']) : null;
            $medUpdate = $nValid > 0 ? MiningResultProcessor::calculateMedian($g['valid_updates']) : null;
            $iqrUpdate = $nValid > 0 ? MiningResultProcessor::calculateIqr($g['valid_updates']) : null;

            $summaryRows[] = [
                'library' => $g['library'],
                'library_version' => $g['library_version'],
                'renderer' => $g['renderer'],
                'workload_size' => $g['workload_size'],
                'n_repeats' => $g['n_repeats'],
                'n_valid' => $nValid,
                'median_render_ms' => $medRender !== null ? sprintf('%.3f', $medRender) : '',
                'iqr_render_ms' => $iqrRender !== null ? sprintf('%.3f', $iqrRender) : '',
                'median_update_ms' => $medUpdate !== null ? sprintf('%.3f', $medUpdate) : '',
                'iqr_update_ms' => $iqrUpdate !== null ? sprintf('%.3f', $iqrUpdate) : '',
            ];
        }

        // Sort by library, then size
        usort($summaryRows, static function (array $a, array $b): int {
            $cmp = strcmp($a['library'], $b['library']);
            if ($cmp !== 0) return $cmp;
            return (int)$a['workload_size'] <=> (int)$b['workload_size'];
        });

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $summaryFile = $outputDir . "/{$prefix}_summary.csv";
        MiningExperimentRunner::writeCsv($summaryFile, self::EXPECTED_SUMMARY_HEADER, $summaryRows);

        return [
            'summary_file' => $summaryFile,
            'total_runs' => count($rawRows),
            'completed_runs' => $completedRuns,
        ];
    }
}
