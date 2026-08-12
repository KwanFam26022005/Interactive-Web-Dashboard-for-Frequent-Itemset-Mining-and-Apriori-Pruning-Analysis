<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class HeatmapResult
{
    /** @var list<string> */
    private array $items;
    /** @var list<list<int>> */
    private array $matrix;
    private int $transactionCount;

    /**
     * @param list<string> $items Selected item strings in deterministic order
     * @param list<list<int>> $matrix Square symmetric co-occurrence count matrix
     * @param int $transactionCount Total transactions N
     */
    public function __construct(
        array $items,
        array $matrix,
        int $transactionCount
    ) {
        $itemCount = count($items);
        if (count($matrix) !== $itemCount) {
            throw new InvalidArgumentException("HeatmapResult invariant failure: matrix row count (" . count($matrix) . ") != items count ({$itemCount}).");
        }

        for ($i = 0; $i < $itemCount; $i++) {
            if (count($matrix[$i]) !== $itemCount) {
                throw new InvalidArgumentException("HeatmapResult invariant failure: matrix row {$i} length (" . count($matrix[$i]) . ") != items count ({$itemCount}).");
            }
            for ($j = 0; $j < $itemCount; $j++) {
                if ($matrix[$i][$j] !== $matrix[$j][$i]) {
                    throw new InvalidArgumentException("HeatmapResult invariant failure: matrix is not symmetric at [{$i}][{$j}].");
                }
                if ($matrix[$i][$j] < 0) {
                    throw new InvalidArgumentException("HeatmapResult invariant failure: matrix count at [{$i}][{$j}] must be non-negative.");
                }
            }
        }

        if ($transactionCount < 0) {
            throw new InvalidArgumentException("HeatmapResult invariant failure: transactionCount must be >= 0.");
        }

        $this->items = array_values($items);
        $this->matrix = array_values($matrix);
        $this->transactionCount = $transactionCount;
    }

    /**
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return list<list<int>>
     */
    public function getMatrix(): array
    {
        return $this->matrix;
    }

    public function getTransactionCount(): int
    {
        return $this->transactionCount;
    }
}
