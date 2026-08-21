<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\MiningExperimentRunner;
use App\Experiments\MiningResultProcessor;

/**
 * Deterministic Evidence Table Generator for Phase 4E Academic Report.
 * Consumes ONLY canonical processed summaries to produce publication-ready CSV tables.
 */
class EvidenceTableGenerator
{
    private string $processedDir;
    private string $outputDir;

    public function __construct(string $processedDir, string $outputDir)
    {
        $this->processedDir = rtrim($processedDir, '/\\');
        $this->outputDir = rtrim($outputDir, '/\\');
    }

    public function generateAll(): array
    {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }

        $supportSummaryFile = $this->processedDir . '/mushroom_support_summary.csv';
        $pruningSummaryFile = $this->processedDir . '/mushroom_pruning_summary.csv';
        $visSummaryFile = $this->processedDir . '/visualization_summary.csv';

        $supportData = MiningResultProcessor::readCsv($supportSummaryFile);
        $pruningData = MiningResultProcessor::readCsv($pruningSummaryFile);
        $visData = MiningResultProcessor::readCsv($visSummaryFile);

        // Sort support data in ascending order of min_support [0.35, 0.40, 0.45, 0.50, 0.60]
        usort($supportData, fn($a, $b) => (float)$a['min_support'] <=> (float)$b['min_support']);

        $t1 = $this->generateT1($supportData);
        $t2 = $this->generateT2($supportData);
        $t2b = $this->generateT2b($pruningData);
        $t3 = $this->generateT3($visData);

        return [
            'T1' => $t1,
            'T2' => $t2,
            'T2b' => $t2b,
            'T3' => $t3,
        ];
    }

    /**
     * T1: RQ1 Support Threshold Effect Table.
     */
    private function generateT1(array $supportData): string
    {
        $headers = [
            'min_support',
            'required_count',
            'candidates_generated',
            'candidates_pruned',
            'candidates_evaluated',
            'frequent_itemsets',
            'rules_count',
            'max_k',
            'median_runtime_ms',
            'iqr_runtime_ms',
            'pruning_ratio',
        ];

        $rows = [];
        foreach ($supportData as $r) {
            $sup = (float)$r['min_support'];
            $reqCount = (int)ceil($sup * 8124);

            $rows[] = [
                'min_support' => sprintf('%.2f', $sup),
                'required_count' => (string)$reqCount,
                'candidates_generated' => (string)$r['candidates_generated'],
                'candidates_pruned' => (string)$r['candidates_pruned'],
                'candidates_evaluated' => (string)$r['candidates_evaluated'],
                'frequent_itemsets' => (string)$r['frequent_itemsets'],
                'rules_count' => (string)$r['rules_count'],
                'max_k' => (string)$r['max_k'],
                'median_runtime_ms' => sprintf('%.3f', (float)$r['median_runtime_ms']),
                'iqr_runtime_ms' => sprintf('%.3f', (float)$r['iqr_runtime_ms']),
                'pruning_ratio' => sprintf('%.6f', (float)$r['pruning_ratio']),
            ];
        }

        $outFile = $this->outputDir . '/T1_rq1_support_effect.csv';
        MiningExperimentRunner::writeCsv($outFile, $headers, $rows);
        return $outFile;
    }

    /**
     * T2: RQ2 Overall Pruning Summary Table.
     */
    private function generateT2(array $supportData): string
    {
        $headers = [
            'min_support',
            'candidates_generated',
            'candidates_pruned',
            'candidates_evaluated',
            'pruning_ratio',
        ];

        $rows = [];
        foreach ($supportData as $r) {
            $sup = (float)$r['min_support'];
            $rows[] = [
                'min_support' => sprintf('%.2f', $sup),
                'candidates_generated' => (string)$r['candidates_generated'],
                'candidates_pruned' => (string)$r['candidates_pruned'],
                'candidates_evaluated' => (string)$r['candidates_evaluated'],
                'pruning_ratio' => sprintf('%.6f', (float)$r['pruning_ratio']),
            ];
        }

        $outFile = $this->outputDir . '/T2_rq2_overall_pruning.csv';
        MiningExperimentRunner::writeCsv($outFile, $headers, $rows);
        return $outFile;
    }

    /**
     * T2b: RQ2 Per-Level Pruning Dynamics Table.
     */
    private function generateT2b(array $pruningData): string
    {
        $headers = [
            'min_support',
            'k',
            'source',
            'generated',
            'pruned',
            'evaluated',
            'frequent',
            'pruning_ratio',
        ];

        $rows = [];
        foreach ($pruningData as $r) {
            $rows[] = [
                'min_support' => sprintf('%.2f', (float)$r['min_support']),
                'k' => (string)$r['k'],
                'source' => (string)$r['source'],
                'generated' => (string)$r['generated'],
                'pruned' => (string)$r['pruned'],
                'evaluated' => (string)$r['evaluated'],
                'frequent' => (string)$r['frequent'],
                'pruning_ratio' => sprintf('%.6f', (float)$r['pruning_ratio']),
            ];
        }

        $outFile = $this->outputDir . '/T2b_rq2_per_level_pruning.csv';
        MiningExperimentRunner::writeCsv($outFile, $headers, $rows);
        return $outFile;
    }

    /**
     * T3: RQ3 Visualization Benchmark Summary Table.
     */
    private function generateT3(array $visData): string
    {
        $headers = [
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

        // Sort by library, then size
        usort($visData, function (array $a, array $b): int {
            $cmp = strcmp($a['library'], $b['library']);
            if ($cmp !== 0) return $cmp;
            return (int)$a['workload_size'] <=> (int)$b['workload_size'];
        });

        $rows = [];
        foreach ($visData as $r) {
            $rows[] = [
                'library' => (string)$r['library'],
                'library_version' => (string)$r['library_version'],
                'renderer' => (string)$r['renderer'],
                'workload_size' => (string)$r['workload_size'],
                'n_repeats' => (string)$r['n_repeats'],
                'n_valid' => (string)$r['n_valid'],
                'median_render_ms' => sprintf('%.3f', (float)$r['median_render_ms']),
                'iqr_render_ms' => sprintf('%.3f', (float)$r['iqr_render_ms']),
                'median_update_ms' => sprintf('%.3f', (float)$r['median_update_ms']),
                'iqr_update_ms' => sprintf('%.3f', (float)$r['iqr_update_ms']),
            ];
        }

        $outFile = $this->outputDir . '/T3_rq3_visualization_performance.csv';
        MiningExperimentRunner::writeCsv($outFile, $headers, $rows);
        return $outFile;
    }
}

// CLI Execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $repoRoot = dirname(__DIR__, 2);
    $processedDir = $repoRoot . '/experiments/processed';
    $outputDir = $repoRoot . '/experiments/tables';

    echo "========================================\n";
    echo "Phase 4E Deterministic Evidence Table Generator\n";
    echo "========================================\n";
    echo "Processed Dir: {$processedDir}\n";
    echo "Output Dir:    {$outputDir}\n";
    echo "========================================\n";

    $generator = new EvidenceTableGenerator($processedDir, $outputDir);
    $files = $generator->generateAll();

    foreach ($files as $tabId => $filePath) {
        $size = filesize($filePath);
        $sha = hash_file('sha256', $filePath);
        echo "[CREATED] {$tabId} -> {$filePath} ({$size} bytes, SHA: {$sha})\n";
    }

    echo "========================================\n";
    echo "[PASS] All 4 evidence tables generated deterministically.\n";
}
