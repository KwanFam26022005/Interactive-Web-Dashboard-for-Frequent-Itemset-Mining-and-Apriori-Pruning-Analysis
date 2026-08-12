<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\AssociationRule;
use App\Mining\Itemset;
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

        // 1. Valid AssociationRule construction
        // ant A count=2, cons B count=2, union AB count=2, N=4
        // support = 2/4 = 0.5, confidence = 2/2 = 1.0, lift = 1.0 / (2/4) = 2.0
        $rule = AssociationRule::createWithDenominatorCheck($setA, $setB, 2, 2, 2, 4);
        $assert('Valid AssociationRule getters match raw unrounded metrics',
            $rule->getAntecedent()->getItems() === ['A'] &&
            $rule->getConsequent()->getItems() === ['B'] &&
            $rule->getSupportCount() === 2 &&
            $rule->getSupport() === 0.5 &&
            $rule->getConfidence() === 1.0 &&
            abs($rule->getLift() - 2.0) < 1e-9
        );

        // 2. Collision-safe binary identity test
        $rule1 = AssociationRule::createWithDenominatorCheck(
            Itemset::fromCanonicalItems(['A', 'B']),
            Itemset::fromCanonicalItems(['C']),
            1, 2, 2, 4
        );
        $rule2 = AssociationRule::createWithDenominatorCheck(
            Itemset::fromCanonicalItems(['A']),
            Itemset::fromCanonicalItems(['B', 'C']),
            1, 4, 1, 4
        );
        $assert('Rule identities differ between (AB -> C) and (A -> BC)', $rule1->getIdentity() !== $rule2->getIdentity());

        // 3. Zero-denominator Invariant Exceptions
        $caughtZeroN = false;
        try {
            AssociationRule::createWithDenominatorCheck($setA, $setB, 2, 2, 2, 0);
        } catch (RuntimeException $e) {
            $caughtZeroN = true;
        }
        $assert('Zero transaction count N throws RuntimeException', $caughtZeroN);

        $caughtZeroAnt = false;
        try {
            AssociationRule::createWithDenominatorCheck($setA, $setB, 2, 0, 2, 4);
        } catch (RuntimeException $e) {
            $caughtZeroAnt = true;
        }
        $assert('Zero antecedent support count throws RuntimeException', $caughtZeroAnt);

        $caughtZeroCons = false;
        try {
            AssociationRule::createWithDenominatorCheck($setA, $setB, 2, 2, 0, 4);
        } catch (RuntimeException $e) {
            $caughtZeroCons = true;
        }
        $assert('Zero consequent support count throws RuntimeException', $caughtZeroCons);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
