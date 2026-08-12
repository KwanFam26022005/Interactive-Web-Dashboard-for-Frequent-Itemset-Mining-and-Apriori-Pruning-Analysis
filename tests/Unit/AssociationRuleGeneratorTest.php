<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\BasketCsvParser;
use App\Mining\AprioriEngine;
use App\Mining\AprioriResult;
use App\Mining\AssociationRuleGenerator;
use App\Mining\Itemset;
use App\Mining\LevelMetrics;
use App\Mining\MiningLimitExceededException;
use InvalidArgumentException;
use RuntimeException;

class AssociationRuleGeneratorTest
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

        $generator = new AssociationRuleGenerator();

        // 1. Input Validation Tests
        $setA = Itemset::fromCanonicalItems(['A']);
        $setB = Itemset::fromCanonicalItems(['B']);
        $setAB = Itemset::fromCanonicalItems(['A', 'B']);

        $mockLevel = new LevelMetrics(1, 'singleton_scan', 2, 0, 2, 2);
        $mockLevel2 = new LevelMetrics(2, 'join_prune', 1, 0, 1, 1);
        $mockResult = new AprioriResult(
            1,
            [$setA, $setB, $setAB],
            [$setA->getIdentity() => 4, $setB->getIdentity() => 2, $setAB->getIdentity() => 2],
            [$mockLevel, $mockLevel2],
            2,
            12345
        );

        $caughtBadN = false;
        try {
            $generator->generate($mockResult, 0, 750000);
        } catch (InvalidArgumentException $e) {
            $caughtBadN = true;
        }
        $assert('N <= 0 rejected with InvalidArgumentException', $caughtBadN);

        $caughtBadConfLow = false;
        try {
            $generator->generate($mockResult, 4, -1);
        } catch (InvalidArgumentException $e) {
            $caughtBadConfLow = true;
        }
        $assert('confidenceUnits < 0 rejected with InvalidArgumentException', $caughtBadConfLow);

        $caughtBadConfHigh = false;
        try {
            $generator->generate($mockResult, 4, 1000001);
        } catch (InvalidArgumentException $e) {
            $caughtBadConfHigh = true;
        }
        $assert('confidenceUnits > 1000000 rejected with InvalidArgumentException', $caughtBadConfHigh);

        $caughtBadLimit = false;
        try {
            $generator->generate($mockResult, 4, 750000, 0);
        } catch (InvalidArgumentException $e) {
            $caughtBadLimit = true;
        }
        $assert('ruleLimit <= 0 rejected with InvalidArgumentException', $caughtBadLimit);

        // 2. Exact Confidence Boundary Test (3/4 = 0.75 confidence)
        // Set up support map: F={X, Y} count=3, X count=4, Y count=3, N=4
        // X -> Y confidence = 3/4 = 0.75
        // Y -> X confidence = 3/3 = 1.0 (sorted first by confidence desc)
        $setX = Itemset::fromCanonicalItems(['X']);
        $setY = Itemset::fromCanonicalItems(['Y']);
        $setXY = Itemset::fromCanonicalItems(['X', 'Y']);
        $mockResultBound = new AprioriResult(
            1,
            [$setX, $setY, $setXY],
            [$setX->getIdentity() => 4, $setY->getIdentity() => 3, $setXY->getIdentity() => 3],
            [$mockLevel, $mockLevel2],
            2,
            100
        );

        $genBoundOk = $generator->generate($mockResultBound, 4, 750000);
        $hasRuleXYInBoundOk = false;
        foreach ($genBoundOk->getRules() as $r) {
            if ($r->getAntecedent()->getItems() === ['X'] && $r->getConsequent()->getItems() === ['Y']) {
                $hasRuleXYInBoundOk = true;
            }
        }
        $assert('Exact confidence 0.75 qualifies at confidence_units=750000', $hasRuleXYInBoundOk);

        $genBoundExcl = $generator->generate($mockResultBound, 4, 750001); // 750001 / 1000000 > 0.75
        $hasXYRule = false;
        foreach ($genBoundExcl->getRules() as $r) {
            if ($r->getAntecedent()->getItems() === ['X'] && $r->getConsequent()->getItems() === ['Y']) {
                $hasXYRule = true;
            }
        }
        $assert('Rule X -> Y with confidence 0.75 excluded at confidence_units=750001', !$hasXYRule);

        // 3. min_confidence = 0 test
        $genZeroConf = $generator->generate($mockResultBound, 4, 0);
        $assert('confidence_units=0 qualifies all rules from frequent unions (X->Y and Y->X)', $genZeroConf->getRulesCount() === 2);

        // 4. Higher-Order Rule Enumeration Test ({A, B, C})
        $setC = Itemset::fromCanonicalItems(['C']);
        $setAC = Itemset::fromCanonicalItems(['A', 'C']);
        $setBC = Itemset::fromCanonicalItems(['B', 'C']);
        $setABC = Itemset::fromCanonicalItems(['A', 'B', 'C']);

        $lvl1 = new LevelMetrics(1, 'singleton_scan', 3, 0, 3, 3);
        $lvl2 = new LevelMetrics(2, 'join_prune', 3, 0, 3, 3);
        $lvl3 = new LevelMetrics(3, 'join_prune', 1, 0, 1, 1);

        $mockResultABC = new AprioriResult(
            1,
            [$setA, $setB, $setC, $setAB, $setAC, $setBC, $setABC],
            [
                $setA->getIdentity() => 4,
                $setB->getIdentity() => 4,
                $setC->getIdentity() => 4,
                $setAB->getIdentity() => 3,
                $setAC->getIdentity() => 3,
                $setBC->getIdentity() => 3,
                $setABC->getIdentity() => 2,
            ],
            [$lvl1, $lvl2, $lvl3],
            3,
            100
        );

        $resABC = $generator->generate($mockResultABC, 4, 0); // 0 confidence to qualify all
        $rulesFromABC = array_filter($resABC->getRules(), static function ($r) {
            $ant = $r->getAntecedent()->getItems();
            $cons = $r->getConsequent()->getItems();
            $union = array_merge($ant, $cons);
            sort($union);
            return $union === ['A', 'B', 'C'];
        });
        $assert('Union {A,B,C} enumerates exactly 6 proper antecedent rules', count($rulesFromABC) === 6);

        // 5. Rule Limit Test (equality succeeds, exceed fails)
        $resLimitOk = $generator->generate($mockResult, 4, 750000, 1);
        $assert('ruleLimit = 1 succeeds when 1 rule qualifies', $resLimitOk->getRulesCount() === 1);

        $caughtLimitExceed = false;
        try {
            $generator->generate($mockResultABC, 4, 0, 2);
        } catch (MiningLimitExceededException $e) {
            $caughtLimitExceed = true;
        }
        $assert('ruleLimit exceed throws MiningLimitExceededException without returning partial result', $caughtLimitExceed);

        // 6. AprioriResult Immutability Assertion
        $origNs = $mockResult->getElapsedNanoseconds();
        $origMapCount = count($mockResult->getSupportMap());
        $origLevelsCount = count($mockResult->getLevels());
        $origFreqCount = count($mockResult->getFrequentItemsets());

        $generator->generate($mockResult, 4, 750000);

        $assert('Running AssociationRuleGenerator leaves AprioriResult untouched',
            $mockResult->getElapsedNanoseconds() === $origNs &&
            count($mockResult->getSupportMap()) === $origMapCount &&
            count($mockResult->getLevels()) === $origLevelsCount &&
            count($mockResult->getFrequentItemsets()) === $origFreqCount
        );

        // 7. Deterministic Clock timing test
        $clockTick = 0;
        $mockClock = static function () use (&$clockTick): int {
            if ($clockTick === 0) {
                $clockTick++;
                return 1000;
            }
            return 5000; // 4000 ns elapsed
        };
        $genTimed = new AssociationRuleGenerator($mockClock);
        $resTimed = $genTimed->generate($mockResult, 4, 750000);
        $assert('Rule generation elapsed nanoseconds accurately captured', $resTimed->getElapsedNanoseconds() === 4000);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
