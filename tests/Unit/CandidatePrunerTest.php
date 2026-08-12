<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\CandidatePruner;
use App\Mining\Itemset;

class CandidatePrunerTest
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

        $pruner = new CandidatePruner();

        // 1. Literal Oracle Pruning Case (Section 16): L2 = {AB, AC}, Candidate C3 = {ABC}
        // Subsets: AB (present), AC (present), BC (ABSENT) -> ABC MUST BE PRUNED
        $l2Oracle = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
        ];
        $c3Candidate = [Itemset::fromCanonicalItems(['A', 'B', 'C'])];

        $partitionOracle = $pruner->prune($c3Candidate, $l2Oracle);

        $assert('Oracle Case: ABC is pruned because subset BC is absent from L2', count($partitionOracle->getPruned()) === 1 && $partitionOracle->getPruned()[0]->getItems() === ['A', 'B', 'C']);
        $assert('Oracle Case: evaluated list is empty', count($partitionOracle->getEvaluated()) === 0);
        $assert('Invariant generated = pruned + evaluated holds (1 = 1 + 0)', $partitionOracle->getGeneratedCount() === 1 && $partitionOracle->getGeneratedCount() === ($partitionOracle->getPrunedCount() + $partitionOracle->getEvaluatedCount()));

        // 2. All immediate subsets present -> Retain / Evaluate
        $l2Complete = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
            Itemset::fromCanonicalItems(['B', 'C']),
        ];
        $partitionComplete = $pruner->prune($c3Candidate, $l2Complete);

        $assert('All immediate subsets present -> Candidate ABC evaluated', count($partitionComplete->getEvaluated()) === 1 && $partitionComplete->getEvaluated()[0]->getItems() === ['A', 'B', 'C']);
        $assert('Pruned list is empty when all subsets present', count($partitionComplete->getPruned()) === 0);
        $assert('Invariant generated = pruned + evaluated holds (1 = 0 + 1)', $partitionComplete->getGeneratedCount() === 1 && $partitionComplete->getGeneratedCount() === ($partitionComplete->getPrunedCount() + $partitionComplete->getEvaluatedCount()));

        // 3. Multiple candidates partition
        $c3Multi = [
            Itemset::fromCanonicalItems(['A', 'B', 'C']), // subsets AB, AC, BC (BC missing -> prune)
            Itemset::fromCanonicalItems(['A', 'B', 'D']), // subsets AB, AD, BD (all present -> evaluate)
        ];
        $l2Multi = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
            Itemset::fromCanonicalItems(['A', 'D']),
            Itemset::fromCanonicalItems(['B', 'D']),
        ];
        $partitionMulti = $pruner->prune($c3Multi, $l2Multi);

        $assert('Multiple candidates exact partition', count($partitionMulti->getPruned()) === 1 &&
            $partitionMulti->getPruned()[0]->getItems() === ['A', 'B', 'C'] &&
            count($partitionMulti->getEvaluated()) === 1 &&
            $partitionMulti->getEvaluated()[0]->getItems() === ['A', 'B', 'D']);
        $assert('Partition invariant holds for multiple candidates (2 = 1 + 1)', $partitionMulti->getGeneratedCount() === 2 && $partitionMulti->getGeneratedCount() === ($partitionMulti->getPrunedCount() + $partitionMulti->getEvaluatedCount()));

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
