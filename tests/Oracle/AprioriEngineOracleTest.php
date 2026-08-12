<?php

declare(strict_types=1);

namespace App\Tests\Oracle;

use App\Dataset\BasketCsvParser;
use App\Dataset\CanonicalTransaction;
use App\Mining\AprioriEngine;
use App\Mining\Itemset;
use App\Mining\MiningLimitExceededException;

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
        // 6. Candidate Guardrail Test
        // --------------------------------------------------
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
        // 7. Deadline Guardrail Test (Deterministic Injected Monotonic Clock)
        // --------------------------------------------------
        $clockTicks = [
            0,                     // startNs
            0,                     // initial check
            1_000_000_000,         // C1 check
            31_000_000_000,        // C2 check (exceeds 30s deadline)
        ];
        $tickIndex = 0;
        $mockClock = static function () use (&$clockTicks, &$tickIndex): int {
            $val = $clockTicks[$tickIndex] ?? 35_000_000_000;
            $tickIndex++;
            return $val;
        };

        $engineDeadline = new AprioriEngine(null, null, null, null, $mockClock);
        $caughtDeadline = false;
        try {
            $engineDeadline->run($transactions, 500000, 250000, 30.0);
        } catch (MiningLimitExceededException $e) {
            $caughtDeadline = true;
        }
        $assert('Deadline guardrail throws MiningLimitExceededException deterministically', $caughtDeadline);

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
