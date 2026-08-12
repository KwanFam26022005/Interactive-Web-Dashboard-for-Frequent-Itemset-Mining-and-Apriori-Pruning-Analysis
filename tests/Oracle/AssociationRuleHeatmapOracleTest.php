<?php

declare(strict_types=1);

namespace App\Tests\Oracle;

use App\Dataset\BasketCsvParser;
use App\Mining\AprioriEngine;
use App\Mining\AssociationRuleGenerator;
use App\Mining\HeatmapBuilder;

class AssociationRuleHeatmapOracleTest
{
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $msg = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
            } else {
                $failed++;
                $results[] = "[FAIL] {$name}: {$msg}";
            }
        };

        // Load tiny.csv fixture
        $fixturePath = dirname(__DIR__, 2) . '/tests/fixtures/tiny.csv';
        $content = file_get_contents($fixturePath);
        $parser = new BasketCsvParser();
        $parseResult = $parser->parse($content, 'tiny.csv');
        $transactions = $parseResult->getTransactions();

        // --------------------------------------------------
        // 1. Exact Tiny Rule Oracle (min_support = 0.50, min_confidence = 0.75)
        // --------------------------------------------------
        $aprioriEngine = new AprioriEngine();
        $aprioriResult = $aprioriEngine->run($transactions, 500000);

        $ruleGen = new AssociationRuleGenerator();
        $ruleResult = $ruleGen->generate($aprioriResult, 4, 750000);

        $assert('Exact tiny oracle rules_count is 2', $ruleResult->getRulesCount() === 2);

        $rules = $ruleResult->getRules();
        $assert('Exact 2 qualifying rules returned', count($rules) === 2);

        // Rule 1: B -> A
        $r1 = $rules[0];
        $assert('Rule #1 antecedent is [B]', $r1->getAntecedent()->getItems() === ['B']);
        $assert('Rule #1 consequent is [A]', $r1->getConsequent()->getItems() === ['A']);
        $assert('Rule #1 support_count is 2', $r1->getSupportCount() === 2);
        $assert('Rule #1 support is 0.5', abs($r1->getSupport() - 0.5) < 1e-9);
        $assert('Rule #1 confidence is 1.0', abs($r1->getConfidence() - 1.0) < 1e-9);
        $assert('Rule #1 lift is 1.0', abs($r1->getLift() - 1.0) < 1e-9);

        // Rule 2: C -> A
        $r2 = $rules[1];
        $assert('Rule #2 antecedent is [C]', $r2->getAntecedent()->getItems() === ['C']);
        $assert('Rule #2 consequent is [A]', $r2->getConsequent()->getItems() === ['A']);
        $assert('Rule #2 support_count is 2', $r2->getSupportCount() === 2);
        $assert('Rule #2 support is 0.5', abs($r2->getSupport() - 0.5) < 1e-9);
        $assert('Rule #2 confidence is 1.0', abs($r2->getConfidence() - 1.0) < 1e-9);
        $assert('Rule #2 lift is 1.0', abs($r2->getLift() - 1.0) < 1e-9);

        // Assert stable tie-break order: [B]->[A] precedes [C]->[A]
        $assert('Stable rule tie-break order puts antecedent [B] before [C]',
            $r1->getAntecedent()->getItems() === ['B'] && $r2->getAntecedent()->getItems() === ['C']
        );

        // Assert A -> B and A -> C are excluded
        $hasAtoB = false;
        $hasAtoC = false;
        foreach ($rules as $r) {
            if ($r->getAntecedent()->getItems() === ['A']) {
                if ($r->getConsequent()->getItems() === ['B']) {
                    $hasAtoB = true;
                }
                if ($r->getConsequent()->getItems() === ['C']) {
                    $hasAtoC = true;
                }
            }
        }
        $assert('Rule A -> B (confidence 0.50 < 0.75) is excluded', !$hasAtoB);
        $assert('Rule A -> C (confidence 0.50 < 0.75) is excluded', !$hasAtoC);

        // --------------------------------------------------
        // 2. Exact Tiny Heatmap Oracle
        // --------------------------------------------------
        $heatmapBuilder = new HeatmapBuilder();
        $heatmapResult = $heatmapBuilder->build($transactions, 25);

        $items = $heatmapResult->getItems();
        $assert('Exact tiny heatmap items are [A, B, C]', $items === ['A', 'B', 'C']);

        $matrix = $heatmapResult->getMatrix();
        $expectedMatrix = [
            [4, 2, 2],
            [2, 2, 1],
            [2, 1, 2],
        ];

        $assert('Exact tiny heatmap matrix matches 3x3 oracle', $matrix === $expectedMatrix);
        $assert('Infrequent pair BC appears as co-occurrence count 1 in heatmap matrix', $matrix[1][2] === 1 && $matrix[2][1] === 1);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
