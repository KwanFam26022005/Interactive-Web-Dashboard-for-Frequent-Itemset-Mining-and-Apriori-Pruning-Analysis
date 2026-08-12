<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\CanonicalTransaction;
use App\Mining\Itemset;
use App\Mining\SupportCounter;

class SupportCounterTest
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

        // Construct tiny canonical transactions:
        // T1: A, B, C
        // T2: A, B
        // T3: A, C
        // T4: A
        $w = [];
        $t1 = CanonicalTransaction::fromRawItems(1, ['A', 'B', 'C'], $w, 1);
        $t2 = CanonicalTransaction::fromRawItems(2, ['A', 'B'], $w, 2);
        $t3 = CanonicalTransaction::fromRawItems(3, ['A', 'C'], $w, 3);
        $t4 = CanonicalTransaction::fromRawItems(4, ['A'], $w, 4);
        $transactions = [$t1, $t2, $t3, $t4];

        $setA   = Itemset::fromCanonicalItems(['A']);
        $setB   = Itemset::fromCanonicalItems(['B']);
        $setC   = Itemset::fromCanonicalItems(['C']);
        $setAB  = Itemset::fromCanonicalItems(['A', 'B']);
        $setAC  = Itemset::fromCanonicalItems(['A', 'C']);
        $setBC  = Itemset::fromCanonicalItems(['B', 'C']);
        $setABC = Itemset::fromCanonicalItems(['A', 'B', 'C']);
        $setD   = Itemset::fromCanonicalItems(['D']);

        $candidates = [$setA, $setB, $setC, $setAB, $setAC, $setBC, $setABC, $setD];

        $counter = new SupportCounter();
        $counts = $counter->countSupport($transactions, $candidates);

        // Assert exact oracle counts (Section 21)
        $assert('Exact count A = 4', $counts[$setA->getIdentity()] === 4);
        $assert('Exact count B = 2', $counts[$setB->getIdentity()] === 2);
        $assert('Exact count C = 2', $counts[$setC->getIdentity()] === 2);
        $assert('Exact count AB = 2', $counts[$setAB->getIdentity()] === 2);
        $assert('Exact count AC = 2', $counts[$setAC->getIdentity()] === 2);
        $assert('Exact count BC = 1', $counts[$setBC->getIdentity()] === 1);
        $assert('Exact count ABC = 1', $counts[$setABC->getIdentity()] === 1);
        $assert('Absent itemset D count = 0', $counts[$setD->getIdentity()] === 0);

        // Case-distinct item test: transaction with lowercase 'a' does not match candidate 'A'
        $tCase = CanonicalTransaction::fromRawItems(5, ['a'], $w, 5);
        $countsCase = $counter->countSupport([$tCase], [$setA]);
        $assert('Case-distinct item a does not match candidate A', $countsCase[$setA->getIdentity()] === 0);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
