<?php

declare(strict_types=1);

namespace App\Http;

use App\Dataset\CanonicalItemIndexKey;
use App\Mining\AprioriResult;
use App\Mining\AssociationRule;
use App\Mining\HeatmapResult;
use App\Mining\Itemset;
use App\Mining\LevelMetrics;
use App\Mining\RuleGenerationResult;
use App\Persistence\DatasetRecord;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts complete mining-domain results into the frozen JSON success shape.
 *
 * This class is deliberately pure: it performs no persistence, HTTP emission,
 * mining, rule generation, or heatmap construction.
 */
final class MiningResponseAssembler
{
    private const UNITS_PER_ONE = 1_000_000;
    private const MAX_TOP_N = 100;
    private const MAX_HEATMAP_ITEMS = 25;

    /**
     * @return array<string, mixed>
     */
    public function assemble(
        int $runId,
        DatasetRecord $dataset,
        int $supportUnits,
        int $confidenceUnits,
        int $topN,
        AprioriResult $apriori,
        RuleGenerationResult $ruleResult,
        HeatmapResult $heatmap
    ): array {
        $this->assertInputs($runId, $dataset, $supportUnits, $confidenceUnits, $topN);

        $transactionCount = $dataset->getTransactionCount();
        $this->assertAprioriResult($apriori, $supportUnits, $transactionCount);
        $levels = $this->serializeLevels($apriori->getLevels());
        $allItemsets = $this->serializeDisplayItemsets($apriori, $transactionCount);
        $allRules = $this->serializeRules($ruleResult, $transactionCount);
        $heatmapPayload = $this->serializeHeatmap($heatmap, $dataset, $topN);

        $itemsets = array_slice($allItemsets, 0, $topN);
        $rules = array_slice($allRules, 0, $topN);
        $generated = $apriori->getCandidatesGeneratedTotal();
        $pruned = $apriori->getCandidatesPrunedTotal();
        $heatmapItemCount = count($heatmapPayload['items']);

        return [
            'run_id' => $runId,
            'dataset' => [
                'id' => $dataset->getId(),
                'name' => $dataset->getName(),
                'transaction_count' => $transactionCount,
                'unique_item_count' => $dataset->getUniqueItemCount(),
            ],
            'parameters' => [
                'min_support' => $supportUnits / self::UNITS_PER_ONE,
                'min_confidence' => $confidenceUnits / self::UNITS_PER_ONE,
                'top_n' => $topN,
            ],
            'summary' => [
                'frequent_itemsets' => $apriori->getFrequentItemsetsTotal(),
                'rules_count' => $ruleResult->getRulesCount(),
                'runtime_ms' => self::nanosecondsToMilliseconds($apriori->getElapsedNanoseconds()),
                'rule_generation_runtime_ms' => self::nanosecondsToMilliseconds(
                    $ruleResult->getElapsedNanoseconds()
                ),
                'max_k' => $apriori->getMaxK(),
                'candidates_generated' => $generated,
                'candidates_pruned' => $pruned,
                'candidates_evaluated' => $apriori->getCandidatesEvaluatedTotal(),
                'pruning_ratio' => self::ratio($pruned, $generated),
            ],
            'levels' => $levels,
            'itemsets' => $itemsets,
            'rules' => $rules,
            'heatmap' => $heatmapPayload,
            'result_limits' => [
                'itemsets_returned' => count($itemsets),
                'itemsets_truncated' => $apriori->getFrequentItemsetsTotal() > count($itemsets),
                'rules_returned' => count($rules),
                'rules_truncated' => $ruleResult->getRulesCount() > count($rules),
                'heatmap_items_returned' => $heatmapItemCount,
                'heatmap_items_truncated' => $dataset->getUniqueItemCount() > $heatmapItemCount,
            ],
        ];
    }

    private function assertInputs(
        int $runId,
        DatasetRecord $dataset,
        int $supportUnits,
        int $confidenceUnits,
        int $topN
    ): void {
        if ($runId < 0) {
            throw new InvalidArgumentException('runId must be zero or a positive integer.');
        }
        if ($dataset->getId() < 1) {
            throw new RuntimeException('Dataset invariant failure: id must be positive.');
        }
        if ($dataset->getTransactionCount() < 1) {
            throw new RuntimeException('Dataset invariant failure: transaction count must be positive.');
        }
        if ($dataset->getUniqueItemCount() < 0) {
            throw new RuntimeException('Dataset invariant failure: unique item count must be non-negative.');
        }
        if ($supportUnits < 1 || $supportUnits > self::UNITS_PER_ONE) {
            throw new InvalidArgumentException('supportUnits must be in [1, 1000000].');
        }
        if ($confidenceUnits < 0 || $confidenceUnits > self::UNITS_PER_ONE) {
            throw new InvalidArgumentException('confidenceUnits must be in [0, 1000000].');
        }
        if ($topN < 1 || $topN > self::MAX_TOP_N) {
            throw new InvalidArgumentException('topN must be in [1, 100].');
        }
    }

    private function assertAprioriResult(
        AprioriResult $apriori,
        int $supportUnits,
        int $transactionCount
    ): void {
        $requiredCount = self::requiredSupportCount($supportUnits, $transactionCount);
        if ($apriori->getRequiredCount() !== $requiredCount) {
            throw new RuntimeException(
                'Apriori result invariant failure: required count does not match the request threshold.'
            );
        }

        $largestFrequentK = 0;
        $expectedK = 1;
        foreach ($apriori->getLevels() as $level) {
            if (!$level instanceof LevelMetrics) {
                throw new RuntimeException('Apriori result invariant failure: invalid level value.');
            }
            if ($level->getK() !== $expectedK) {
                throw new RuntimeException(
                    'Apriori result invariant failure: levels must be contiguous in ascending k order.'
                );
            }
            if ($level->getFrequent() > 0) {
                $largestFrequentK = $level->getK();
            }
            $expectedK++;
        }

        if ($apriori->getMaxK() !== $largestFrequentK) {
            throw new RuntimeException(
                'Apriori result invariant failure: maxK does not match the reported frequent levels.'
            );
        }
    }

    /**
     * @param list<LevelMetrics> $levels
     * @return list<array{
     *   k: int,
     *   source: string,
     *   generated: int,
     *   pruned: int,
     *   evaluated: int,
     *   frequent: int,
     *   pruning_ratio: float|null
     * }>
     */
    private function serializeLevels(array $levels): array
    {
        $serialized = [];

        foreach ($levels as $level) {
            $serialized[] = [
                'k' => $level->getK(),
                'source' => $level->getSource(),
                'generated' => $level->getGenerated(),
                'pruned' => $level->getPruned(),
                'evaluated' => $level->getEvaluated(),
                'frequent' => $level->getFrequent(),
                'pruning_ratio' => self::ratio($level->getPruned(), $level->getGenerated()),
            ];
        }

        return $serialized;
    }

    /**
     * @return list<array{items: list<string>, k: int, support_count: int, support: float}>
     */
    private function serializeDisplayItemsets(AprioriResult $apriori, int $transactionCount): array
    {
        $supportMap = $apriori->getSupportMap();
        $requiredCount = $apriori->getRequiredCount();
        $seen = [];
        $sortable = [];

        foreach ($apriori->getFrequentItemsets() as $itemset) {
            if (!$itemset instanceof Itemset) {
                throw new RuntimeException('Apriori result invariant failure: invalid frequent itemset value.');
            }

            $identity = $itemset->getIdentity();
            if (isset($seen[$identity])) {
                throw new RuntimeException('Apriori result invariant failure: duplicate frequent itemset.');
            }
            $seen[$identity] = true;

            if (!array_key_exists($identity, $supportMap)) {
                throw new RuntimeException(
                    'Apriori result invariant failure: frequent itemset is missing authoritative support.'
                );
            }

            $supportCount = $supportMap[$identity];
            if (
                !is_int($supportCount)
                || $supportCount < $requiredCount
                || $supportCount > $transactionCount
            ) {
                throw new RuntimeException(
                    'Apriori result invariant failure: frequent itemset has an invalid support count.'
                );
            }

            $sortable[] = [
                'itemset' => $itemset,
                'support_count' => $supportCount,
            ];
        }

        usort(
            $sortable,
            static function (array $left, array $right): int {
                $supportComparison = $right['support_count'] <=> $left['support_count'];
                if ($supportComparison !== 0) {
                    return $supportComparison;
                }

                /** @var Itemset $leftItemset */
                $leftItemset = $left['itemset'];
                /** @var Itemset $rightItemset */
                $rightItemset = $right['itemset'];
                $sizeComparison = $rightItemset->getSize() <=> $leftItemset->getSize();
                if ($sizeComparison !== 0) {
                    return $sizeComparison;
                }

                return Itemset::compare($leftItemset, $rightItemset);
            }
        );

        $serialized = [];
        foreach ($sortable as $entry) {
            /** @var Itemset $itemset */
            $itemset = $entry['itemset'];
            /** @var int $supportCount */
            $supportCount = $entry['support_count'];
            $serialized[] = [
                'items' => $itemset->getItems(),
                'k' => $itemset->getSize(),
                'support_count' => $supportCount,
                'support' => round($supportCount / $transactionCount, 6),
            ];
        }

        return $serialized;
    }

    /**
     * @return list<array{
     *   antecedent: list<string>,
     *   consequent: list<string>,
     *   support_count: int,
     *   support: float,
     *   confidence: float,
     *   lift: float
     * }>
     */
    private function serializeRules(RuleGenerationResult $ruleResult, int $transactionCount): array
    {
        $serialized = [];

        foreach ($ruleResult->getRules() as $rule) {
            if (!$rule instanceof AssociationRule) {
                throw new RuntimeException('Rule result invariant failure: invalid rule value.');
            }

            $supportCount = $rule->getSupportCount();
            $support = $rule->getSupport();
            $confidence = $rule->getConfidence();
            $lift = $rule->getLift();
            if (
                $supportCount < 1
                || $supportCount > $transactionCount
                || !is_finite($support)
                || !is_finite($confidence)
                || !is_finite($lift)
                || $support <= 0.0
                || $support > 1.0
                || $confidence <= 0.0
                || $confidence > 1.0
                || $lift <= 0.0
            ) {
                throw new RuntimeException('Rule result invariant failure: invalid rule metric.');
            }

            $serialized[] = [
                'antecedent' => $rule->getAntecedent()->getItems(),
                'consequent' => $rule->getConsequent()->getItems(),
                'support_count' => $supportCount,
                'support' => round($support, 6),
                'confidence' => round($confidence, 6),
                'lift' => round($lift, 6),
            ];
        }

        return $serialized;
    }

    /**
     * @return array{metric: string, items: list<string>, values: list<list<int>>}
     */
    private function serializeHeatmap(HeatmapResult $heatmap, DatasetRecord $dataset, int $topN): array
    {
        if ($heatmap->getTransactionCount() !== $dataset->getTransactionCount()) {
            throw new RuntimeException(
                'Heatmap result invariant failure: transaction count does not match the dataset.'
            );
        }

        $items = $heatmap->getItems();
        $itemCount = count($items);
        $allowedCount = min($topN, self::MAX_HEATMAP_ITEMS);
        if ($itemCount > $dataset->getUniqueItemCount() || $itemCount > $allowedCount) {
            throw new RuntimeException('Heatmap result invariant failure: item count exceeds its frozen cap.');
        }

        $seen = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new RuntimeException('Heatmap result invariant failure: every item must be a string.');
            }

            $identity = CanonicalItemIndexKey::encode($item);
            if (isset($seen[$identity])) {
                throw new RuntimeException('Heatmap result invariant failure: duplicate item.');
            }
            $seen[$identity] = true;
        }

        foreach ($heatmap->getMatrix() as $row) {
            foreach ($row as $value) {
                if (!is_int($value) || $value > $dataset->getTransactionCount()) {
                    throw new RuntimeException('Heatmap result invariant failure: invalid support count.');
                }
            }
        }

        return [
            'metric' => 'support_count',
            'items' => $items,
            'values' => $heatmap->getMatrix(),
        ];
    }

    private static function nanosecondsToMilliseconds(int $nanoseconds): float
    {
        return round($nanoseconds / 1_000_000, 3);
    }

    private static function ratio(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round($numerator / $denominator, 6);
    }

    private static function requiredSupportCount(int $supportUnits, int $transactionCount): int
    {
        $wholeMillions = intdiv($transactionCount, self::UNITS_PER_ONE);
        $remainder = $transactionCount % self::UNITS_PER_ONE;
        $remainderProduct = $remainder * $supportUnits;

        return ($wholeMillions * $supportUnits)
            + intdiv($remainderProduct + self::UNITS_PER_ONE - 1, self::UNITS_PER_ONE);
    }
}
