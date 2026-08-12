<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\AssociationRule;
use App\Mining\Itemset;
use Error;
use RuntimeException;

class AssociationRuleTest
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

        $setA = Itemset::fromCanonicalItems(['A']);
        $setB = Itemset::fromCanonicalItems(['B']);
        $setC = Itemset::fromCanonicalItems(['C']);
        $setAB = Itemset::fromCanonicalItems(['A', 'B']);
        $setBC = Itemset::fromCanonicalItems(['B', 'C']);

        // 1. Direct new AssociationRule(...) is private (sealed constructor)
        $caughtPrivateConstructor = false;
        try {
            // @phpstan-ignore-next-line
            new AssociationRule($setA, $setB, 2, 0.5, 1.0, 2.0);
        } catch (Error $e) {
            $caughtPrivateConstructor = true;
        }
        $assert('Direct instantiation via new AssociationRule(...) is sealed (private)', $caughtPrivateConstructor);

        // 2. Valid AssociationRule factory construction
        $rule = AssociationRule::createFromCounts($setA, $setB, 2, 2, 2, 4);
        $assert('Valid AssociationRule getters match raw unrounded metrics',
            $rule->getAntecedent()->getItems() === ['A'] &&
            $rule->getConsequent()->getItems() === ['B'] &&
            $rule->getSupportCount() === 2 &&
            $rule->getSupport() === 0.5 &&
            $rule->getConfidence() === 1.0 &&
            abs($rule->getLift() - 2.0) < 1e-9
        );

        // 3. Collision-safe binary identity test
        $rule1 = AssociationRule::createFromCounts($setAB, $setC, 1, 2, 2, 4);
        $rule2 = AssociationRule::createFromCounts($setA, $setBC, 1, 4, 1, 4);
        $assert('Rule identities differ between (AB -> C) and (A -> BC)', $rule1->getIdentity() !== $rule2->getIdentity());

        // 4. Rule Side Invariants (Disjointness)
        $caughtOverlapSame = false;
        try {
            AssociationRule::createFromCounts($setA, $setA, 2, 2, 2, 4);
        } catch (RuntimeException $e) {
            $caughtOverlapSame = true;
        }
        $assert('Overlapping sides (A -> A) throws RuntimeException', $caughtOverlapSame);

        $caughtOverlapIntersect = false;
        try {
            AssociationRule::createFromCounts($setAB, $setBC, 1, 2, 2, 4);
        } catch (RuntimeException $e) {
            $caughtOverlapIntersect = true;
        }
        $assert('Overlapping sides (AB -> BC) throws RuntimeException', $caughtOverlapIntersect);

        // 5. Support Count Invariants
        $caughtZeroN = false;
        try {
            AssociationRule::createFromCounts($setA, $setB, 2, 2, 2, 0);
        } catch (RuntimeException $e) {
            $caughtZeroN = true;
        }
        $assert('Zero transaction count N throws RuntimeException', $caughtZeroN);

        $caughtZeroF = false;
        try {
            AssociationRule::createFromCounts($setA, $setB, 0, 2, 2, 4);
        } catch (RuntimeException $e) {
            $caughtZeroF = true;
        }
        $assert('supportCountF = 0 throws RuntimeException', $caughtZeroF);

        $caughtFOverA = false;
        try {
            AssociationRule::createFromCounts($setA, $setB, 3, 2, 3, 4); // F=3 > A=2
        } catch (RuntimeException $e) {
            $caughtFOverA = true;
        }
        $assert('supportCountF > supportCountA throws RuntimeException', $caughtFOverA);

        $caughtFOverB = false;
        try {
            AssociationRule::createFromCounts($setA, $setB, 3, 3, 2, 4); // F=3 > B=2
        } catch (RuntimeException $e) {
            $caughtFOverB = true;
        }
        $assert('supportCountF > supportCountB throws RuntimeException', $caughtFOverB);

        $caughtFOverN = false;
        try {
            AssociationRule::createFromCounts($setA, $setB, 5, 5, 5, 4); // F=5 > N=4
        } catch (RuntimeException $e) {
            $caughtFOverN = true;
        }
        $assert('supportCountF > N throws RuntimeException', $caughtFOverN);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
