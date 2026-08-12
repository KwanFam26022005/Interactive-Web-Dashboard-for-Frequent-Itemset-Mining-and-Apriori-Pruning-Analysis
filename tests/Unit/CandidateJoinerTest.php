<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\CandidateJoiner;
use App\Mining\Itemset;
use InvalidArgumentException;

class CandidateJoinerTest
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

        $joiner = new CandidateJoiner();

        // 1. L1 self-join: [A], [B], [C] -> [AB], [AC], [BC]
        $l1 = [
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['B']),
            Itemset::fromCanonicalItems(['C']),
        ];
        $c2 = $joiner->join($l1);
        $assert('L1 join produces exactly 3 candidate pairs AB, AC, BC', count($c2) === 3 &&
            $c2[0]->getItems() === ['A', 'B'] &&
            $c2[1]->getItems() === ['A', 'C'] &&
            $c2[2]->getItems() === ['B', 'C']);

        // 2. Compatible L2 join: AB, AC -> ABC
        $l2Comp = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
        ];
        $c3Comp = $joiner->join($l2Comp);
        $assert('Compatible L2 join AB, AC produces ABC', count($c3Comp) === 1 && $c3Comp[0]->getItems() === ['A', 'B', 'C']);

        // 3. Incompatible L2 join: AB, CD -> no join
        $l2Incomp = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['C', 'D']),
        ];
        $c3Incomp = $joiner->join($l2Incomp);
        $assert('Incompatible L2 join AB, CD produces empty candidate list', count($c3Incomp) === 0);

        // 4. Mixed compatible set: AB, AC, AD -> ABC, ABD, ACD
        $l2Mixed = [
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['A', 'C']),
            Itemset::fromCanonicalItems(['A', 'D']),
        ];
        $c3Mixed = $joiner->join($l2Mixed);
        $assert('Mixed compatible L2 join AB, AC, AD produces ABC, ABD, ACD', count($c3Mixed) === 3 &&
            $c3Mixed[0]->getItems() === ['A', 'B', 'C'] &&
            $c3Mixed[1]->getItems() === ['A', 'B', 'D'] &&
            $c3Mixed[2]->getItems() === ['A', 'C', 'D']);

        // 5. Fewer than two members -> empty list
        $l2Single = [Itemset::fromCanonicalItems(['A', 'B'])];
        $c3Single = $joiner->join($l2Single);
        $assert('Single input itemset produces empty candidate list', count($c3Single) === 0);

        // 6. Input shuffle invariant
        $l1Shuffled = [
            Itemset::fromCanonicalItems(['C']),
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['B']),
        ];
        $c2Shuffled = $joiner->join($l1Shuffled);
        $assert('Shuffled input produces identical sorted candidates', count($c2Shuffled) === count($c2) &&
            $c2Shuffled[0]->equals($c2[0]) &&
            $c2Shuffled[1]->equals($c2[1]) &&
            $c2Shuffled[2]->equals($c2[2]));

        // 7. Duplicate input itemsets deduplicated defensively
        $l1Dup = [
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['B']),
            Itemset::fromCanonicalItems(['C']),
        ];
        $c2Dup = $joiner->join($l1Dup);
        $assert('Duplicate input itemsets do not create duplicate candidates', count($c2Dup) === 3);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
