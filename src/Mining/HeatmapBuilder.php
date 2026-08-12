<?php

declare(strict_types=1);

namespace App\Mining;

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

        /** @var array<string, int> $singletonCounts */
        $singletonCounts = [];

        foreach ($transactions as $tx) {
            foreach ($tx->getItems() as $item) {
                if (!isset($singletonCounts[$item])) {
                    $singletonCounts[$item] = 1;
                } else {
                    $singletonCounts[$item]++;
                }
            }
        }

        uksort($singletonCounts, static function (string $item1, string $item2) use (&$singletonCounts): int {
            $c1 = $singletonCounts[$item1];
            $c2 = $singletonCounts[$item2];
            if ($c1 !== $c2) {
                return $c2 <=> $c1; // descending count
            }
            return strcmp($item1, $item2); // ascending strcmp tie-break
        });

        $selectedItems = array_slice(array_keys($singletonCounts), 0, $maxItems);
        $M = count($selectedItems);

        /** @var array<string, int> $itemIndex Map item string => index 0..M-1 */
        $itemIndex = array_flip($selectedItems);

        // Initialize MxM zero matrix
        $matrix = [];
        for ($i = 0; $i < $M; $i++) {
            $matrix[$i] = array_fill(0, $M, 0);
        }

        foreach ($transactions as $tx) {
            $txItems = $tx->getItems();
            // Filter transaction items to selected items
            /** @var list<int> $presentIndices */
            $presentIndices = [];
            foreach ($txItems as $item) {
                if (isset($itemIndex[$item])) {
                    $presentIndices[] = $itemIndex[$item];
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
