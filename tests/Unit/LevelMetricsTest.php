<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\AprioriResult;
use App\Mining\Itemset;
use App\Mining\LevelMetrics;
use InvalidArgumentException;

class LevelMetricsTest
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

        // 1. Valid LevelMetrics
        $m1 = new LevelMetrics(1, 'singleton_scan', 3, 0, 3, 3);
        $assert('Valid LevelMetrics k=1', $m1->getK() === 1 && $m1->getSource() === 'singleton_scan' && $m1->getGenerated() === 3 && $m1->getPruned() === 0 && $m1->getEvaluated() === 3 && $m1->getFrequent() === 3);

        $m2 = new LevelMetrics(2, 'join_prune', 3, 0, 3, 2);
        $assert('Valid LevelMetrics k=2', $m2->getK() === 2 && $m2->getSource() === 'join_prune');

        // 2. Negative Tests for LevelMetrics
        $caughtKZero = false;
        try {
            new LevelMetrics(0, 'singleton_scan', 1, 0, 1, 1);
        } catch (InvalidArgumentException $e) {
            $caughtKZero = true;
        }
        $assert('k = 0 rejected', $caughtKZero);

        $caughtBadSource = false;
        try {
            new LevelMetrics(1, 'unknown_source', 1, 0, 1, 1);
        } catch (InvalidArgumentException $e) {
            $caughtBadSource = true;
        }
        $assert('unknown source rejected', $caughtBadSource);

        $caughtK1JoinPrune = false;
        try {
            new LevelMetrics(1, 'join_prune', 1, 0, 1, 1);
        } catch (InvalidArgumentException $e) {
            $caughtK1JoinPrune = true;
        }
        $assert('k=1 with join_prune rejected', $caughtK1JoinPrune);

        $caughtK2SingletonScan = false;
        try {
            new LevelMetrics(2, 'singleton_scan', 1, 0, 1, 1);
        } catch (InvalidArgumentException $e) {
            $caughtK2SingletonScan = true;
        }
        $assert('k=2 with singleton_scan rejected', $caughtK2SingletonScan);

        $caughtInvariantSum = false;
        try {
            new LevelMetrics(2, 'join_prune', 3, 1, 1, 1); // 3 != 1 + 1
        } catch (InvalidArgumentException $e) {
            $caughtInvariantSum = true;
        }
        $assert('generated != pruned + evaluated rejected', $caughtInvariantSum);

        $caughtFreqOverEval = false;
        try {
            new LevelMetrics(2, 'join_prune', 3, 0, 3, 4); // 4 > 3
        } catch (InvalidArgumentException $e) {
            $caughtFreqOverEval = true;
        }
        $assert('frequent > evaluated rejected', $caughtFreqOverEval);

        $caughtNegative = false;
        try {
            new LevelMetrics(2, 'join_prune', -1, 0, -1, 0);
        } catch (InvalidArgumentException $e) {
            $caughtNegative = true;
        }
        $assert('negative count rejected', $caughtNegative);

        // 3. AprioriResult Invariant Tests
        $setA = Itemset::fromCanonicalItems(['A']);
        $setB = Itemset::fromCanonicalItems(['B']);
        $setC = Itemset::fromCanonicalItems(['C']);
        $res = new AprioriResult(
            2,
            [$setA, $setB, $setC],
            [$setA->getIdentity() => 4, $setB->getIdentity() => 2, $setC->getIdentity() => 2],
            [$m1],
            1,
            1000000
        );

        $assert('AprioriResult run totals match level sums', $res->getCandidatesGeneratedTotal() === 3 &&
            $res->getCandidatesPrunedTotal() === 0 &&
            $res->getCandidatesEvaluatedTotal() === 3 &&
            $res->getFrequentItemsetsTotal() === 3 &&
            $res->getMaxK() === 1 &&
            $res->getElapsedNanoseconds() === 1000000);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
