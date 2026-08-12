<?php

declare(strict_types=1);

namespace App\Mining;

use App\Dataset\CanonicalItemIndexKey;
use App\Dataset\CanonicalTransaction;
use InvalidArgumentException;

class HeatmapBuilder
{
    /**
     * Builds full-dataset singleton and pair co-occurrence heatmap matrix.
     *
     * @param list<CanonicalTransaction> $transactions Canonical transactions in memory
     * @param int $maxItems Maximum items to select (1 <= maxItems <= 25)
     * @return HeatmapResult Exact Heatmap result
     * @throws InvalidArgumentException on invalid input parameters
     */
    public function build(array $transactions, int $maxItems = 25): HeatmapResult
    {
        $txCount = count($transactions);
        if ($txCount === 0) {
            throw new InvalidArgumentException("Transaction list cannot be empty.");
        }

        if ($maxItems <= 0 || $maxItems > 25) {
            throw new InvalidArgumentException("maxItems must be between 1 and 25. Got {$maxItems}.");
        }

        /** @var array<string, array{item: string, count: int}> $singletonMap */
        $singletonMap = [];

        foreach ($transactions as $tx) {
            foreach ($tx->getItems() as $item) {
                $encodedKey = CanonicalItemIndexKey::encode($item);
                if (!isset($singletonMap[$encodedKey])) {
                    $singletonMap[$encodedKey] = [
                        'item' => $item,
                        'count' => 1,
                    ];
                } else {
                    $singletonMap[$encodedKey]['count']++;
                }
            }
        }

        $singletonList = array_values($singletonMap);

        usort($singletonList, static function (array $a, array $b): int {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count']; // support count descending
            }
            return strcmp($a['item'], $b['item']); // ascending binary strcmp tie-break
        });

        $selectedEntries = array_slice($singletonList, 0, $maxItems);
        /** @var list<string> $selectedItems */
        $selectedItems = [];
        /** @var array<string, int> $encodedItemIndex */
        $encodedItemIndex = [];

        foreach ($selectedEntries as $idx => $entry) {
            $itemStr = $entry['item'];
            $selectedItems[] = $itemStr;
            $encodedKey = CanonicalItemIndexKey::encode($itemStr);
            $encodedItemIndex[$encodedKey] = $idx;
        }

        $M = count($selectedItems);

        // Initialize MxM zero matrix
        $matrix = [];
        for ($i = 0; $i < $M; $i++) {
            $matrix[$i] = array_fill(0, $M, 0);
        }

        foreach ($transactions as $tx) {
            /** @var list<int> $presentIndices */
            $presentIndices = [];
            foreach ($tx->getItems() as $item) {
                $encodedKey = CanonicalItemIndexKey::encode($item);
                if (isset($encodedItemIndex[$encodedKey])) {
                    $presentIndices[] = $encodedItemIndex[$encodedKey];
                }
            }

            $countSel = count($presentIndices);
            for ($i = 0; $i < $countSel; $i++) {
                $idxA = $presentIndices[$i];
                $matrix[$idxA][$idxA]++;
                for ($j = $i + 1; $j < $countSel; $j++) {
                    $idxB = $presentIndices[$j];
                    $matrix[$idxA][$idxB]++;
                    $matrix[$idxB][$idxA]++;
                }
            }
        }

        return new HeatmapResult($selectedItems, $matrix, $txCount);
    }
}
