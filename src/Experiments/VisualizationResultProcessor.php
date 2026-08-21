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

        $grouped = [];
        $completedRuns = 0;

        foreach ($rawRows as $row) {
            $lib = $row['library'] ?? '';
            $ver = $row['library_version'] ?? '';
            $rend = $row['renderer'] ?? '';
            $size = (int)($row['workload_size'] ?? 0);
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

            if (($row['status'] ?? '') === 'COMPLETED') {
                $completedRuns++;
                if (isset($row['render_ms']) && is_numeric($row['render_ms'])) {
                    $grouped[$key]['valid_renders'][] = (float)$row['render_ms'];
                }
                if (isset($row['update_ms']) && is_numeric($row['update_ms'])) {
                    $grouped[$key]['valid_updates'][] = (float)$row['update_ms'];
                }
            }
        }

        $summaryRows = [];
        foreach ($grouped as $g) {
            $nValid = count($g['valid_renders']);
            $medRender = MiningResultProcessor::calculateMedian($g['valid_renders']);
            $iqrRender = MiningResultProcessor::calculateIqr($g['valid_renders']);
            $medUpdate = MiningResultProcessor::calculateMedian($g['valid_updates']);
            $iqrUpdate = MiningResultProcessor::calculateIqr($g['valid_updates']);

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
