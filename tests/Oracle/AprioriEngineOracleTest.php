<?php

declare(strict_types=1);

namespace App\Tests\Oracle;

use App\Dataset\BasketCsvParser;
use App\Dataset\CanonicalTransaction;
use App\Mining\AprioriEngine;
use App\Mining\Itemset;
use App\Mining\MiningLimitExceededException;
use InvalidArgumentException;

class AprioriEngineOracleTest
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

        // Parse tiny.csv fixture to canonical transactions
        $fixturePath = dirname(__DIR__, 2) . '/tests/fixtures/tiny.csv';
        $content = file_get_contents($fixturePath);
        $parser = new BasketCsvParser();
        $parseResult = $parser->parse($content, 'tiny.csv');
        $transactions = $parseResult->getTransactions();

        $engine = new AprioriEngine();

        // --------------------------------------------------
        // 1. Exact Tiny Oracle Run (min_support = 0.50, support_units = 500000)
        // --------------------------------------------------
        $res = $engine->run($transactions, 500000);

        $assert('Oracle required_count is 2', $res->getRequiredCount() === 2);
        $assert('Oracle max_k is 2', $res->getMaxK() === 2);

        $levels = $res->getLevels();
        $assert('Oracle reports exactly 3 levels (C1, C2, C3)', count($levels) === 3);

        // Level 1 checks
        $l1 = $levels[0];
        $assert('C1 metrics match oracle (k=1, singleton_scan, gen:3, prun:0, eval:3, freq:3)',
            $l1->getK() === 1 && $l1->getSource() === 'singleton_scan' &&
            $l1->getGenerated() === 3 && $l1->getPruned() === 0 &&
            $l1->getEvaluated() === 3 && $l1->getFrequent() === 3
        );

        // Level 2 checks
        $l2 = $levels[1];
        $assert('C2 metrics match oracle (k=2, join_prune, gen:3, prun:0, eval:3, freq:2)',
            $l2->getK() === 2 && $l2->getSource() === 'join_prune' &&
            $l2->getGenerated() === 3 && $l2->getPruned() === 0 &&
            $l2->getEvaluated() === 3 && $l2->getFrequent() === 2
        );

        // Level 3 checks
        $l3 = $levels[2];
        $assert('C3 metrics match oracle (k=3, join_prune, gen:1, prun:1, eval:0, freq:0)',
            $l3->getK() === 3 && $l3->getSource() === 'join_prune' &&
            $l3->getGenerated() === 1 && $l3->getPruned() === 1 &&
            $l3->getEvaluated() === 0 && $l3->getFrequent() === 0
        );

        // Totals checks
        $assert('Oracle candidates_generated total is 7', $res->getCandidatesGeneratedTotal() === 7);
        $assert('Oracle candidates_pruned total is 1', $res->getCandidatesPrunedTotal() === 1);
        $assert('Oracle candidates_evaluated total is 6', $res->getCandidatesEvaluatedTotal() === 6);
        $assert('Oracle frequent_itemsets total is 5', $res->getFrequentItemsetsTotal() === 5);
        $assert('Oracle pruning ratio is exactly 1/7', abs(($res->getPruningRatio() ?? 0.0) - (1.0 / 7.0)) < 1e-9);

        // Exact Frequent Itemsets List and Stable Order
        $freqItemsets = $res->getFrequentItemsets();
        $assert('Exact 5 frequent itemsets returned in stable order', count($freqItemsets) === 5 &&
            $freqItemsets[0]->getItems() === ['A'] &&
            $freqItemsets[1]->getItems() === ['B'] &&
            $freqItemsets[2]->getItems() === ['C'] &&
            $freqItemsets[3]->getItems() === ['A', 'B'] &&
            $freqItemsets[4]->getItems() === ['A', 'C']
        );

        // Authoritative Support Map checks
        $supportMap = $res->getSupportMap();
        $setA   = Itemset::fromCanonicalItems(['A']);
        $setB   = Itemset::fromCanonicalItems(['B']);
        $setC   = Itemset::fromCanonicalItems(['C']);
        $setAB  = Itemset::fromCanonicalItems(['A', 'B']);
        $setAC  = Itemset::fromCanonicalItems(['A', 'C']);
        $setBC  = Itemset::fromCanonicalItems(['B', 'C']);
        $setABC = Itemset::fromCanonicalItems(['A', 'B', 'C']);

        $assert('Support map contains A:4', isset($supportMap[$setA->getIdentity()]) && $supportMap[$setA->getIdentity()] === 4);
        $assert('Support map contains B:2', isset($supportMap[$setB->getIdentity()]) && $supportMap[$setB->getIdentity()] === 2);
        $assert('Support map contains C:2', isset($supportMap[$setC->getIdentity()]) && $supportMap[$setC->getIdentity()] === 2);
        $assert('Support map contains AB:2', isset($supportMap[$setAB->getIdentity()]) && $supportMap[$setAB->getIdentity()] === 2);
        $assert('Support map contains AC:2', isset($supportMap[$setAC->getIdentity()]) && $supportMap[$setAC->getIdentity()] === 2);
        $assert('Support map contains evaluated BC:1', isset($supportMap[$setBC->getIdentity()]) && $supportMap[$setBC->getIdentity()] === 1);
        $assert('Support map MUST NOT contain pruned ABC', !isset($supportMap[$setABC->getIdentity()]));

        // --------------------------------------------------
        // 2. Terminal-Level Test
        // --------------------------------------------------
        $assert('C3 appears as terminal level even though frequent=0 because generated=1', count($levels) === 3 && $levels[2]->getK() === 3 && $levels[2]->getFrequent() === 0);

        // --------------------------------------------------
        // 3. Join-Empty Test (threshold > 0.5, support_units = 500001)
        // --------------------------------------------------
        $resHigh = $engine->run($transactions, 500001);
        $assert('Join-empty test reports exactly 1 level (C1)', count($resHigh->getLevels()) === 1);
        $assert('Join-empty test max_k is 1', $resHigh->getMaxK() === 1);
        $assert('Join-empty test frequent itemsets has only [A]', count($resHigh->getFrequentItemsets()) === 1 && $resHigh->getFrequentItemsets()[0]->getItems() === ['A']);

        // --------------------------------------------------
        // 4. No-Frequent-Singleton Test
        // --------------------------------------------------
        $w = [];
        $tNoFreq1 = CanonicalTransaction::fromRawItems(1, ['A'], $w, 1);
        $tNoFreq2 = CanonicalTransaction::fromRawItems(2, ['B'], $w, 2);
        $resNoFreq = $engine->run([$tNoFreq1, $tNoFreq2], 1000000); // N=2, units=1000000 => req=2

        $assert('No-frequent-singleton test reports 1 level with frequent=0', count($resNoFreq->getLevels()) === 1 && $resNoFreq->getLevels()[0]->getFrequent() === 0);
        $assert('No-frequent-singleton test max_k is 0', $resNoFreq->getMaxK() === 0);
        $assert('No-frequent-singleton test frequent itemsets is empty', count($resNoFreq->getFrequentItemsets()) === 0);

        // --------------------------------------------------
        // 5. Evaluated-Infrequent Terminal Test
        // --------------------------------------------------
        $tInf1 = CanonicalTransaction::fromRawItems(1, ['A', 'B'], $w, 1);
        $tInf2 = CanonicalTransaction::fromRawItems(2, ['A', 'C'], $w, 2);
        $tInf3 = CanonicalTransaction::fromRawItems(3, ['B', 'C'], $w, 3);
        $resInf = $engine->run([$tInf1, $tInf2, $tInf3], 500000); // N=3, units=500000 => req=2

        $assert('Evaluated-infrequent terminal level reports C2 with eval:3, freq:0', count($resInf->getLevels()) === 2 &&
            $resInf->getLevels()[1]->getK() === 2 &&
            $resInf->getLevels()[1]->getEvaluated() === 3 &&
            $resInf->getLevels()[1]->getFrequent() === 0
        );
        $assert('Evaluated-infrequent terminal test max_k is 1', $resInf->getMaxK() === 1);

        // --------------------------------------------------
        // 6. Guardrail Parameter Validation Tests (Section 5)
        // --------------------------------------------------
        $badCandidateLimits = [0, -1];
        foreach ($badCandidateLimits as $badLimit) {
            $caughtBadLimit = false;
            try {
                $engine->run($transactions, 500000, $badLimit);
            } catch (InvalidArgumentException $e) {
                $caughtBadLimit = true;
            }
            $assert("candidateLimit={$badLimit} rejected with InvalidArgumentException", $caughtBadLimit);
        }

        $badDeadlines = [0.0, -1.0, INF, NAN];
        foreach ($badDeadlines as $idx => $badDeadline) {
            $caughtBadDeadline = false;
            try {
                $engine->run($transactions, 500000, 250000, $badDeadline);
            } catch (InvalidArgumentException $e) {
                $caughtBadDeadline = true;
            }
            $assert("bad deadlineSeconds #{$idx} rejected with InvalidArgumentException", $caughtBadDeadline);
        }

        // Candidate limit regression tests
        $resLimitOk = $engine->run($transactions, 500000, 7);
        $assert('candidateLimit = 7 succeeds for tiny dataset (7 candidates)', $resLimitOk->getCandidatesGeneratedTotal() === 7);

        $caughtLimit = false;
        try {
            $engine->run($transactions, 500000, 6);
        } catch (MiningLimitExceededException $e) {
            $caughtLimit = true;
        }
        $assert('candidateLimit = 6 throws MiningLimitExceededException', $caughtLimit);

        // --------------------------------------------------
        // 7. Strict Deadline Semantics Boundary Tests (Section 6, 15)
        // --------------------------------------------------
        // Test A: elapsed < deadline -> allowed
        $clockLess = static fn(): int => 100;
        $engineLess = new AprioriEngine(null, null, null, null, $clockLess);
        $resLess = $engineLess->run($transactions, 500000, 250000, 10.0);
        $assert('Test A: elapsed < deadline is allowed (succeeds)', $resLess->getCandidatesGeneratedTotal() === 7);

        // Test B: elapsed == deadline -> throws MiningLimitExceededException
        $eqTick = 0;
        $clockEq = static function () use (&$eqTick): int {
            if ($eqTick === 0) {
                $eqTick++;
                return 0;
            }
            return 10_000_000_000; // 10.0 seconds exact
        };
        $engineEq = new AprioriEngine(null, null, null, null, $clockEq);
        $caughtEq = false;
        try {
            $engineEq->run($transactions, 500000, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtEq = true;
        }
        $assert('Test B: elapsed == deadline throws MiningLimitExceededException', $caughtEq);

        // Test C: elapsed > deadline -> throws MiningLimitExceededException
        $gtTick = 0;
        $clockGt = static function () use (&$gtTick): int {
            if ($gtTick === 0) {
                $gtTick++;
                return 0;
            }
            return 10_000_000_001; // 10.000000001 seconds
        };
        $engineGt = new AprioriEngine(null, null, null, null, $clockGt);
        $caughtGt = false;
        try {
            $engineGt->run($transactions, 500000, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtGt = true;
        }
        $assert('Test C: elapsed > deadline throws MiningLimitExceededException', $caughtGt);

        // Test D: C1 terminal overrun -> failure
        $c1Tick = 0;
        $clockC1Over = static function () use (&$c1Tick): int {
            if ($c1Tick < 2) {
                $c1Tick++;
                return 0;
            }
            return 10_000_000_000; // hits deadline after C1
        };
        $engineC1Over = new AprioriEngine(null, null, null, null, $clockC1Over);
        $caughtC1Over = false;
        try {
            $engineC1Over->run([$tNoFreq1, $tNoFreq2], 1000000, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtC1Over = true;
        }
        $assert('Test D: C1 terminal overrun throws MiningLimitExceededException', $caughtC1Over);

        // Test E: join-empty terminal overrun -> failure
        $joinTick = 0;
        $clockJoinOver = static function () use (&$joinTick): int {
            if ($joinTick < 3) {
                $joinTick++;
                return 0;
            }
            return 10_000_000_000; // hits deadline after join returns []
        };
        $engineJoinOver = new AprioriEngine(null, null, null, null, $clockJoinOver);
        $caughtJoinOver = false;
        try {
            $engineJoinOver->run($transactions, 500001, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtJoinOver = true;
        }
        $assert('Test E: join-empty terminal overrun throws MiningLimitExceededException', $caughtJoinOver);

        // Test F: evaluated-infrequent terminal overrun -> failure
        $evalTick = 0;
        $clockEvalOver = static function () use (&$evalTick): int {
            if ($evalTick < 4) {
                $evalTick++;
                return 0;
            }
            return 10_000_000_000; // hits deadline after C2 filter
        };
        $engineEvalOver = new AprioriEngine(null, null, null, null, $clockEvalOver);
        $caughtEvalOver = false;
        try {
            $engineEvalOver->run([$tInf1, $tInf2, $tInf3], 500000, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtEvalOver = true;
        }
        $assert('Test F: evaluated-infrequent terminal overrun throws MiningLimitExceededException', $caughtEvalOver);

        // Test G: all-pruned terminal overrun -> failure
        $pruneTick = 0;
        $clockPruneOver = static function () use (&$pruneTick): int {
            if ($pruneTick < 6) {
                $pruneTick++;
                return 0;
            }
            return 10_000_000_000; // hits deadline after C3 prune
        };
        $enginePruneOver = new AprioriEngine(null, null, null, null, $clockPruneOver);
        $caughtPruneOver = false;
        try {
            $enginePruneOver->run($transactions, 500000, 250000, 10.0);
        } catch (MiningLimitExceededException $e) {
            $caughtPruneOver = true;
        }
        $assert('Test G: all-pruned terminal overrun (C3) throws MiningLimitExceededException', $caughtPruneOver);

        // --------------------------------------------------
        // 8. Determinism Test (Repeated Runs & Raw Permutation)
        // --------------------------------------------------
        $resDet1 = $engine->run($transactions, 500000);
        $resDet2 = $engine->run($transactions, 500000);

        $assert('Repeated runs produce identical totals and max_k', $resDet1->getCandidatesGeneratedTotal() === $resDet2->getCandidatesGeneratedTotal() &&
            $resDet1->getFrequentItemsetsTotal() === $resDet2->getFrequentItemsetsTotal() &&
            $resDet1->getMaxK() === $resDet2->getMaxK()
        );

        // Raw input item permutation
        $tPerm1 = CanonicalTransaction::fromRawItems(1, ['C', 'B', 'A'], $w, 1);
        $tPerm2 = CanonicalTransaction::fromRawItems(2, ['B', 'A'], $w, 2);
        $tPerm3 = CanonicalTransaction::fromRawItems(3, ['C', 'A'], $w, 3);
        $tPerm4 = CanonicalTransaction::fromRawItems(4, ['A'], $w, 4);

        $resPerm = $engine->run([$tPerm1, $tPerm2, $tPerm3, $tPerm4], 500000);
        $assert('Permuted raw transaction items produce identical oracle frequent itemsets', count($resPerm->getFrequentItemsets()) === 5 &&
            $resPerm->getFrequentItemsets()[0]->getItems() === ['A'] &&
            $resPerm->getFrequentItemsets()[4]->getItems() === ['A', 'C']
        );

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
