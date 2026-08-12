<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class AprioriResult
{
    private int $requiredCount;
    /** @var list<Itemset> */
    private array $frequentItemsets;
    /** @var array<string, int> */
    private array $supportMap;
    /** @var list<LevelMetrics> */
    private array $levels;
    private int $candidatesGeneratedTotal;
    private int $candidatesPrunedTotal;
    private int $candidatesEvaluatedTotal;
    private int $frequentItemsetsTotal;
    private int $maxK;
    private int $elapsedNanoseconds;

    /**
     * @param int $requiredCount Minimum required transaction count
     * @param list<Itemset> $frequentItemsets Accumulated frequent itemsets
     * @param array<string, int> $supportMap Authoritative evaluated support counts map
     * @param list<LevelMetrics> $levels Per-level metrics
     * @param int $maxK Largest level k with non-empty Lk (0 if L1 empty)
     * @param int $elapsedNanoseconds Monotonic elapsed nanoseconds
     */
    public function __construct(
        int $requiredCount,
        array $frequentItemsets,
        array $supportMap,
        array $levels,
        int $maxK,
        int $elapsedNanoseconds
    ) {
        if ($requiredCount < 1) {
            throw new InvalidArgumentException("requiredCount must be >= 1.");
        }

        $genSum = 0;
        $prunedSum = 0;
        $evalSum = 0;
        $freqSum = 0;

        foreach ($levels as $lvl) {
            $genSum += $lvl->getGenerated();
            $prunedSum += $lvl->getPruned();
            $evalSum += $lvl->getEvaluated();
            $freqSum += $lvl->getFrequent();
        }

        if ($genSum !== ($prunedSum + $evalSum)) {
            throw new InvalidArgumentException("AprioriResult invariant failure: total generated ({$genSum}) != pruned ({$prunedSum}) + evaluated ({$evalSum}).");
        }

        if ($freqSum !== count($frequentItemsets)) {
            throw new InvalidArgumentException("AprioriResult invariant failure: total frequent count ({$freqSum}) != actual frequent itemsets count (" . count($frequentItemsets) . ").");
        }

        $this->requiredCount = $requiredCount;
        $this->frequentItemsets = array_values($frequentItemsets);
        $this->supportMap = $supportMap;
        $this->levels = array_values($levels);
        $this->candidatesGeneratedTotal = $genSum;
        $this->candidatesPrunedTotal = $prunedSum;
        $this->candidatesEvaluatedTotal = $evalSum;
        $this->frequentItemsetsTotal = $freqSum;
        $this->maxK = $maxK;
        $this->elapsedNanoseconds = max(0, $elapsedNanoseconds);
    }

    public function getRequiredCount(): int
    {
        return $this->requiredCount;
    }

    /**
     * @return list<Itemset>
     */
    public function getFrequentItemsets(): array
    {
        return $this->frequentItemsets;
    }

    /**
     * @return array<string, int> Map of binary Itemset identity => integer support count
     */
    public function getSupportMap(): array
    {
        return $this->supportMap;
    }

    /**
     * @return list<LevelMetrics>
     */
    public function getLevels(): array
    {
        return $this->levels;
    }

    public function getCandidatesGeneratedTotal(): int
    {
        return $this->candidatesGeneratedTotal;
    }

    public function getCandidatesPrunedTotal(): int
    {
        return $this->candidatesPrunedTotal;
    }

    public function getCandidatesEvaluatedTotal(): int
    {
        return $this->candidatesEvaluatedTotal;
    }

    public function getFrequentItemsetsTotal(): int
    {
        return $this->frequentItemsetsTotal;
    }

    public function getMaxK(): int
    {
        return $this->maxK;
    }

    public function getElapsedNanoseconds(): int
    {
        return $this->elapsedNanoseconds;
    }

    public function getPruningRatio(): ?float
    {
        if ($this->candidatesGeneratedTotal > 0) {
            return (float)$this->candidatesPrunedTotal / (float)$this->candidatesGeneratedTotal;
        }
        return null;
    }
}
