<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\Itemset;
use Error;
use InvalidArgumentException;

class ItemsetTest
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

        // 1. Direct instantiation is sealed (constructor is private)
        $caughtConstructor = false;
        try {
            // @phpstan-ignore-next-line
            new Itemset(['A'], 'identity');
        } catch (Error $e) {
            $caughtConstructor = true;
        }
        $assert('Itemset direct instantiation via constructor is sealed (private)', $caughtConstructor);

        // 2. Factory creation and strcmp sorting
        $set = Itemset::fromCanonicalItems(['C', 'A', 'B']);
        $assert('Itemset sorts items ascending by strcmp', $set->getItems() === ['A', 'B', 'C'] && $set->getSize() === 3);

        // 3. Rejects empty items array
        $caughtEmpty = false;
        try {
            Itemset::fromCanonicalItems([]);
        } catch (InvalidArgumentException $e) {
            $caughtEmpty = true;
        }
        $assert('Empty items array rejected', $caughtEmpty);

        // 4. Rejects non-canonical items
        $caughtNonCanonical = false;
        try {
            Itemset::fromCanonicalItems([' A ']);
        } catch (InvalidArgumentException $e) {
            $caughtNonCanonical = true;
        }
        $assert('Non-canonical item rejected', $caughtNonCanonical);

        // 5. Rejects duplicate items
        $caughtDuplicate = false;
        try {
            Itemset::fromCanonicalItems(['A', 'B', 'A']);
        } catch (InvalidArgumentException $e) {
            $caughtDuplicate = true;
        }
        $assert('Duplicate item in Itemset creation rejected', $caughtDuplicate);

        // 6. Input permutation produces identical canonical items and binary identity
        $perm1 = Itemset::fromCanonicalItems(['C', 'A', 'B']);
        $perm2 = Itemset::fromCanonicalItems(['B', 'C', 'A']);
        $assert('Permuted item inputs produce identical canonical items and identity', $perm1->getItems() === $perm2->getItems() && $perm1->equals($perm2) && $perm1->getIdentity() === $perm2->getIdentity());

        // 7. Collision-safe length-prefixed identity checks
        $delim1 = Itemset::fromCanonicalItems(['A|B', 'C']);
        $delim2 = Itemset::fromCanonicalItems(['A', 'B|C']);
        $assert("['A|B', 'C'] does not collide with ['A', 'B|C']", !$delim1->equals($delim2) && $delim1->getIdentity() !== $delim2->getIdentity());

        $concat1 = Itemset::fromCanonicalItems(['ab', 'c']);
        $concat2 = Itemset::fromCanonicalItems(['a', 'bc']);
        $assert("['ab', 'c'] does not collide with ['a', 'bc']", !$concat1->equals($concat2) && $concat1->getIdentity() !== $concat2->getIdentity());

        // 8. Comparator tests
        $setA = Itemset::fromCanonicalItems(['A']);
        $seta = Itemset::fromCanonicalItems(['a']);
        $setB = Itemset::fromCanonicalItems(['B']);
        $setAB = Itemset::fromCanonicalItems(['A', 'B']);

        $assert('Binary strcmp puts A before a', Itemset::compare($setA, $seta) === -1);
        $assert('Binary strcmp puts A before B', Itemset::compare($setA, $setB) === -1);
        $assert('Prefix relationship puts shorter itemset {A} before {A, B}', Itemset::compare($setA, $setAB) === -1);
        $assert('Same itemset compares equal (0)', Itemset::compare($setAB, $setAB) === 0);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
