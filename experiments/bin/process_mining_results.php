<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\MiningResultProcessor;

$options = getopt('', [
    'runs:',
    'levels:',
    'output-dir:',
    'prefix:',
]);

$runsPath = $options['runs'] ?? null;
$levelsPath = $options['levels'] ?? null;
$outputDir = $options['output-dir'] ?? null;
$prefix = $options['prefix'] ?? 'mushroom';

if (!$runsPath || !$levelsPath) {
    echo "Usage: php experiments/bin/process_mining_results.php --runs <path> --levels <path> [--output-dir <dir>] [--prefix <prefix>]\n";
    exit(1);
}

if (!$outputDir) {
    $outputDir = dirname((string)$runsPath);
}

echo "========================================\n";
echo "Mining Experiment Result Processor\n";
echo "========================================\n";
echo "Runs CSV:   {$runsPath}\n";
echo "Levels CSV: {$levelsPath}\n";
echo "Output Dir: {$outputDir}\n";
echo "========================================\n";

try {
    $processor = new MiningResultProcessor();
    $result = $processor->process(
        (string)$runsPath,
        (string)$levelsPath,
        (string)$outputDir,
        (string)$prefix
    );

    echo "[PASS] Result processing & invariant verification completed successfully!\n";
    echo "Total runs processed:      " . $result['total_runs'] . "\n";
    echo "Completed runs aggregated: " . $result['completed_runs'] . "\n";
    echo "Distinct support levels:   " . implode(', ', $result['distinct_supports']) . "\n";
    echo "Support summary written:   " . $result['support_summary_file'] . "\n";
    echo "Pruning summary written:   " . $result['pruning_summary_file'] . "\n";
    echo "========================================\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[FAIL] Processing failed: " . $e->getMessage() . "\n";
    exit(1);
}
