<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Experiments\EnvironmentCollector;

$options = getopt('', ['config:', 'dataset:', 'output:', 'save']);
$configPath = $options['config'] ?? (dirname(__DIR__) . '/configs/mushroom_experiment_config.json');
$datasetPath = $options['dataset'] ?? null;
$outputPath = $options['output'] ?? null;
$saveToConfig = isset($options['save']);

$manifest = EnvironmentCollector::collect(
    is_file((string)$configPath) ? (string)$configPath : null,
    $datasetPath && is_file((string)$datasetPath) ? (string)$datasetPath : null
);

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($outputPath) {
    file_put_contents((string)$outputPath, $json);
    echo "Saved environment manifest to: {$outputPath}\n";
} elseif ($saveToConfig) {
    $target = dirname(__DIR__) . '/configs/environment_manifest.json';
    file_put_contents($target, $json);
    echo "Updated environment manifest in: {$target}\n";
} else {
    echo $json;
}
