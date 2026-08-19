<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';

use App\Dataset\ParserRegistry;
use App\Mining\AprioriEngine;
use App\Mining\AssociationRuleGenerator;
use App\Mining\MiningLimitExceededException;

$options = getopt('', [
    'dataset:',
    'supports:',
    'confidence:',
    'profile:',
    'config:',
]);

$datasetPath = $options['dataset'] ?? (dirname(__DIR__, 2) . '/tests/fixtures/tiny.csv');
$profile = $options['profile'] ?? 'basket_csv';
$configPath = $options['config'] ?? (dirname(__DIR__) . '/configs/mushroom_experiment_config.json');
$confidence = isset($options['confidence']) ? (float)$options['confidence'] : 0.75;

if (isset($options['supports'])) {
    $supports = array_map('floatval', explode(',', (string)$options['supports']));
} elseif (is_file((string)$configPath)) {
    $cfg = json_decode((string)file_get_contents((string)$configPath), true);
    $supports = array_map('floatval', $cfg['min_support'] ?? [0.2, 0.15, 0.1, 0.05]);
    if (isset($cfg['min_confidence'])) {
        $confidence = (float)$cfg['min_confidence'];
    }
} else {
    $supports = [0.20, 0.15, 0.10, 0.05];
}

echo "========================================\n";
echo "Apriori Feasibility Matrix Probe\n";
echo "========================================\n";
echo "Dataset:    {$datasetPath}\n";
echo "Profile:    {$profile}\n";
echo "Confidence: " . sprintf('%.2f', $confidence) . "\n";
echo "Supports:   " . implode(', ', $supports) . "\n";
echo "Output:     STRICTLY PROBE / CONSOLE ONLY (never writes to formal raw)\n";
echo "========================================\n";

try {
    $registry = new ParserRegistry();
    $parser = $registry->getParser($profile);
    $content = (string)file_get_contents($datasetPath);
    $parseRes = $parser->parse($content, basename($datasetPath));
    $transactions = $parseRes->getTransactions();
    $n = count($transactions);

    echo "Parsed {$n} transactions.\n\n";

    printf("%-10s | %-12s | %-12s | %-12s | %-10s | %-10s | %-6s | %-12s\n",
        "Support", "Generated", "Pruned", "Evaluated", "Frequent", "Rules", "Max k", "Runtime (ms)");
    echo str_repeat('-', 96) . "\n";

    $engine = new AprioriEngine();
    $ruleGen = new AssociationRuleGenerator();
    $confUnits = (int)round($confidence * 1_000_000);

    foreach ($supports as $sup) {
        $units = (int)round($sup * 1_000_000);
        try {
            $res = $engine->run($transactions, $units, 250000, 30.0);
            $ruleRes = $ruleGen->generate($res, $n, $confUnits, 50000);

            $runtimeMs = round($res->getElapsedNanoseconds() / 1_000_000.0, 3);
            printf("%-10.4f | %-12d | %-12d | %-12d | %-10d | %-10d | %-6d | %-12.3f\n",
                $sup,
                $res->getCandidatesGeneratedTotal(),
                $res->getCandidatesPrunedTotal(),
                $res->getCandidatesEvaluatedTotal(),
                $res->getFrequentItemsetsTotal(),
                $ruleRes->getRulesCount(),
                $res->getMaxK(),
                $runtimeMs
            );
        } catch (MiningLimitExceededException $e) {
            printf("%-10.4f | EXCEEDED: %s\n", $sup, $e->getMessage());
        } catch (\Throwable $e) {
            printf("%-10.4f | ERROR: %s\n", $sup, $e->getMessage());
        }
    }
    echo "========================================\n";
    exit(0);
} catch (\Throwable $e) {
    echo "[FAIL] Probe failed: " . $e->getMessage() . "\n";
    exit(1);
}
