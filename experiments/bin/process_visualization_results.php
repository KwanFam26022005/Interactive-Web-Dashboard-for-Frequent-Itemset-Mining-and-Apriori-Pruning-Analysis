<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\VisualizationResultProcessor;

$options = getopt('', [
    'runs:',
    'output-dir:',
    'prefix:',
]);

$runsPath = $options['runs'] ?? (dirname(__DIR__) . '/raw/visualization_runs.csv');
$outputDir = $options['output-dir'] ?? (dirname(__DIR__) . '/processed');
$prefix = $options['prefix'] ?? 'visualization';

echo "========================================\n";
echo "Visualization Benchmark Result Processor\n";
echo "========================================\n";
echo "Runs CSV:   {$runsPath}\n";
echo "Output Dir: {$outputDir}\n";
echo "Prefix:     {$prefix}\n";
echo "========================================\n";

try {
    $processor = new VisualizationResultProcessor();
    $result = $processor->process($runsPath, $outputDir, $prefix);

    echo "[PASS] Visualization processing completed successfully!\n";
    echo "Total runs processed:      {$result['total_runs']}\n";
    echo "Completed runs aggregated: {$result['completed_runs']}\n";
    echo "Summary CSV written:       {$result['summary_file']}\n";
    echo "========================================\n";
    exit(0);
} catch (Throwable $e) {
    echo "[FAIL] Processing failed: " . $e->getMessage() . "\n";
    exit(1);
}
