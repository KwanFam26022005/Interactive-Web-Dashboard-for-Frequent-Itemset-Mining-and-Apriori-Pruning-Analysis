<?php

declare(strict_types=1);

namespace App\Mining;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class AssociationRuleGenerator
{
    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param (Closure(): int)|null $clock Optional injected monotonic nanosecond timer callback for deterministic tests
     */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn(): int => (int)hrtime(true);
    }

    /**
     * Generates qualifying association rules from an AprioriResult.
     *
     * @param AprioriResult $aprioriResult Authoritative Apriori result
     * @param int $transactionCount Total transactions N (must be > 0)
     * @param int $confidenceUnits Confidence threshold in millionths (0 <= confidenceUnits <= 1_000_000)
     * @param int $ruleLimit Maximum qualifying rules allowed (default 50,000)
     * @return RuleGenerationResult Complete qualifying rules result
     * @throws InvalidArgumentException on invalid input parameters
     * @throws RuntimeException on zero-denominator, invalid support map, or invariant failures
     * @throws MiningLimitExceededException if qualifying rules count exceeds ruleLimit
     */
    public function generate(
        AprioriResult $aprioriResult,
        int $transactionCount,
        int $confidenceUnits,
        int $ruleLimit = 50000
    ): RuleGenerationResult {
        if ($transactionCount <= 0) {
            throw new InvalidArgumentException("transactionCount N must be a positive integer (> 0). Got {$transactionCount}.");
        }

        if ($confidenceUnits < 0 || $confidenceUnits > 1_000_000) {
            throw new InvalidArgumentException("confidenceUnits must be between 0 and 1,000,000. Got {$confidenceUnits}.");
        }

        if ($ruleLimit <= 0) {
            throw new InvalidArgumentException("ruleLimit must be a positive integer (> 0). Got {$ruleLimit}.");
        }

        // Monotonic timer starts immediately before rule enumeration
        $startNs = ($this->clock)();

        $supportMap = $aprioriResult->getSupportMap();
        $frequentItemsets = $aprioriResult->getFrequentItemsets();

        /** @var array<string, bool> $seenIdentities */
        $seenIdentities = [];
        /** @var list<AssociationRule> $qualifyingRules */
        $qualifyingRules = [];

        foreach ($frequentItemsets as $frequentSet) {
            $items = $frequentSet->getItems();
            if (count($items) < 2) {
                continue;
            }

            $properSubsets = [];
            $this->enumerateProperSubsets($items, 0, [], $properSubsets);

            foreach ($properSubsets as $antItems) {
                $consItems = array_values(array_diff($items, $antItems));

                $antSet = Itemset::fromCanonicalItems($antItems);
                $consSet = Itemset::fromCanonicalItems($consItems);

                $identF = $frequentSet->getIdentity();
                $identA = $antSet->getIdentity();
                $identB = $consSet->getIdentity();

                if (!isset($supportMap[$identF])) {
                    throw new RuntimeException("Authoritative support map missing entry for frequent itemset union.");
                }
                if (!isset($supportMap[$identA])) {
                    throw new RuntimeException("Authoritative support map missing entry for antecedent itemset.");
                }
                if (!isset($supportMap[$identB])) {
                    throw new RuntimeException("Authoritative support map missing entry for consequent itemset.");
                }

                $cntF = $supportMap[$identF];
                $cntA = $supportMap[$identA];
                $cntB = $supportMap[$identB];

                // Exact integer cross-multiplication confidence filter
                if (($cntF * 1_000_000) >= ($confidenceUnits * $cntA)) {
                    $rule = AssociationRule::createFromCounts(
                        $antSet,
                        $consSet,
                        $cntF,
                        $cntA,
                        $cntB,
                        $transactionCount
                    );

                    $ruleIdent = $rule->getIdentity();
                    if (isset($seenIdentities[$ruleIdent])) {
                        continue;
                    }

                    if ((count($qualifyingRules) + 1) > $ruleLimit) {
                        throw new MiningLimitExceededException("Rule generation limit exceeded: qualifying rules count exceeds limit ({$ruleLimit}).");
                    }

                    $seenIdentities[$ruleIdent] = true;
                    $qualifyingRules[] = $rule;
                }
            }
        }

        // Stable rule sorting using exact integer cross-products
        usort($qualifyingRules, function (AssociationRule $r1, AssociationRule $r2) use ($supportMap): int {
            $cntF1 = $r1->getSupportCount();
            $cntA1 = $supportMap[$r1->getAntecedent()->getIdentity()];
            $cntB1 = $supportMap[$r1->getConsequent()->getIdentity()];

            $cntF2 = $r2->getSupportCount();
            $cntA2 = $supportMap[$r2->getAntecedent()->getIdentity()];
            $cntB2 = $supportMap[$r2->getConsequent()->getIdentity()];

            // 1. Lift descending: Lift1 vs Lift2 => (cntF1 / cntA1) / (cntB1 / N) vs (cntF2 / cntA2) / (cntB2 / N)
            // => cntF1 * cntA2 * cntB2 vs cntF2 * cntA1 * cntB1
            $l1 = $cntF1 * $cntA2 * $cntB2;
            $l2 = $cntF2 * $cntA1 * $cntB1;
            if ($l1 !== $l2) {
                return $l2 <=> $l1; // descending
            }

            // 2. Confidence descending: Conf1 vs Conf2 => cntF1 / cntA1 vs cntF2 / cntA2
            // => cntF1 * cntA2 vs cntF2 * cntA1
            $c1 = $cntF1 * $cntA2;
            $c2 = $cntF2 * $cntA1;
            if ($c1 !== $c2) {
                return $c2 <=> $c1; // descending
            }

            // 3. Support descending: cntF1 vs cntF2
            if ($cntF1 !== $cntF2) {
                return $cntF2 <=> $cntF1; // descending
            }

            // 4. Antecedent canonical order
            $cmpAnt = Itemset::compare($r1->getAntecedent(), $r2->getAntecedent());
            if ($cmpAnt !== 0) {
                return $cmpAnt;
            }

            // 5. Consequent canonical order
            return Itemset::compare($r1->getConsequent(), $r2->getConsequent());
        });

        // Validate RuleGenerationResult invariant before stopping clock
        new RuleGenerationResult($qualifyingRules, count($qualifyingRules), 0);

        $endNs = ($this->clock)();

        return new RuleGenerationResult(
            $qualifyingRules,
            count($qualifyingRules),
            $endNs - $startNs
        );
    }

    /**
     * Recursively enumerates all non-empty proper subsets of $items.
     *
     * @param list<string> $items Full item list
     * @param int $index Current index
     * @param list<string> $current Accumulator of items
     * @param list<list<string>> $subsets Result list of subsets
     */
    private function enumerateProperSubsets(
        array $items,
        int $index,
        array $current,
        array &$subsets
    ): void {
        if ($index === count($items)) {
            $cnt = count($current);
            if ($cnt > 0 && $cnt < count($items)) {
                $subsets[] = $current;
            }
            return;
        }

        // Branch 1: exclude items[index]
        $this->enumerateProperSubsets($items, $index + 1, $current, $subsets);

        // Branch 2: include items[index]
        $current[] = $items[$index];
        $this->enumerateProperSubsets($items, $index + 1, $current, $subsets);
    }
}
