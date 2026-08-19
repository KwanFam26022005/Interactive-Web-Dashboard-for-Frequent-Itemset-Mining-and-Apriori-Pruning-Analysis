<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\MiningExperimentRunner;

$options = getopt('', [
    'mode:',
    'fixture:',
    'dataset:',
    'config:',
    'profile:',
    'output-dir:',
    'prefix:',
    'dry-run',
]);

$mode = $options['mode'] ?? 'smoke';
$datasetPath = $options['dataset'] ?? ($options['fixture'] ?? (dirname(__DIR__, 2) . '/tests/fixtures/tiny.csv'));
$configPath = $options['config'] ?? (dirname(__DIR__) . '/configs/mushroom_experiment_config.json');
$profile = $options['profile'] ?? null;
$outputDir = $options['output-dir'] ?? null;
$prefix = $options['prefix'] ?? null;
$dryRun = isset($options['dry-run']);

echo "========================================\n";
echo "Apriori Mining Experiment Runner\n";
echo "========================================\n";
echo "Mode:       " . strtoupper((string)$mode) . "\n";
echo "Dataset:    {$datasetPath}\n";
echo "Config:     {$configPath}\n";
if ($dryRun) {
    echo "Execution:  DRY-RUN (planning & validation only)\n";
}
echo "========================================\n";

try {
    $runner = new MiningExperimentRunner();
    $result = $runner->execute([
        'mode' => (string)$mode,
        'dataset_path' => (string)$datasetPath,
        'config_path' => (string)$configPath,
        'profile' => $profile !== null ? (string)$profile : ($mode === 'smoke' ? 'basket_csv' : null),
        'output_dir' => $outputDir !== null ? (string)$outputDir : null,
        'prefix' => $prefix !== null ? (string)$prefix : null,
        'dry_run' => $dryRun,
    ]);

    if ($dryRun) {
        echo "[PASS] Dry run validation successful!\n";
        echo "Transactions detected: " . $result['summary_stats']['transactions_count'] . "\n";
        echo "Git Revision:          " . ($result['summary_stats']['git_sha'] ?? 'N/A') . "\n";
        echo "Config SHA-256:        " . $result['summary_stats']['config_sha'] . "\n";
        echo "Dataset SHA-256:       " . $result['summary_stats']['dataset_sha'] . "\n";
        echo "Supports configured:   " . $result['summary_stats']['support_count'] . "\n";
        echo "Formal repetitions:    " . $result['summary_stats']['formal_repetitions'] . "\n";
        exit(0);
    }

    echo "[PASS] Experiment run completed successfully!\n";
    echo "Total runs recorded:   " . $result['runs_count'] . "\n";
    echo "Total levels recorded: " . $result['levels_count'] . "\n";
    echo "Runs CSV:              " . $result['runs_file'] . "\n";
    echo "Levels CSV:            " . $result['levels_file'] . "\n";
    echo "========================================\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[FAIL] Experiment execution aborted: " . $e->getMessage() . "\n";
    exit(1);
}
