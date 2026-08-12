<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\FrequentFilter;
use App\Mining\Itemset;
use RuntimeException;

class FrequentFilterTest
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

        $filter = new FrequentFilter();

        $setA  = Itemset::fromCanonicalItems(['A']);
        $setB  = Itemset::fromCanonicalItems(['B']);
        $setC  = Itemset::fromCanonicalItems(['C']);
        $setBC = Itemset::fromCanonicalItems(['B', 'C']);

        $evaluated = [$setA, $setB, $setC, $setBC];
        $counts = [
            $setA->getIdentity()  => 4,
            $setB->getIdentity()  => 2,
            $setC->getIdentity()  => 2,
            $setBC->getIdentity() => 1,
        ];

        // 1. Filter with required_count = 2 -> A, B, C frequent; BC excluded
        $frequent = $filter->filter($evaluated, $counts, 2);

        $assert('FrequentFilter retains candidates with count >= required_count (2)', count($frequent) === 3 &&
            $frequent[0]->getItems() === ['A'] &&
            $frequent[1]->getItems() === ['B'] &&
            $frequent[2]->getItems() === ['C']);

        // 2. Exact boundary test: count == required_count - 1 is excluded
        $assert('Candidate with count == required_count - 1 (BC with count 1 < 2) is excluded', !in_array($setBC, $frequent, true));

        // 3. Missing support count entry throws RuntimeException
        $caughtMissing = false;
        try {
            $filter->filter([$setA], [], 2);
        } catch (RuntimeException $e) {
            $caughtMissing = true;
        }
        $assert('Missing support count map entry throws RuntimeException', $caughtMissing);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
