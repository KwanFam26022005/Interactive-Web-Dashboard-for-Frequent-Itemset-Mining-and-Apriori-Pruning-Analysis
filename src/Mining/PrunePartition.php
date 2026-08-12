<?php

declare(strict_types=1);

namespace App\Mining;

class PrunePartition
{
    /** @var list<Itemset> */
    private array $pruned;
    /** @var list<Itemset> */
    private array $evaluated;

    /**
     * @param list<Itemset> $pruned
     * @param list<Itemset> $evaluated
     */
    public function __construct(array $pruned, array $evaluated)
    {
        $this->pruned = array_values($pruned);
        $this->evaluated = array_values($evaluated);
    }

    /**
     * @return list<Itemset>
     */
    public function getPruned(): array
    {
        return $this->pruned;
    }

    /**
     * @return list<Itemset>
     */
    public function getEvaluated(): array
    {
        return $this->evaluated;
    }

    public function getGeneratedCount(): int
    {
        return count($this->pruned) + count($this->evaluated);
    }

    public function getPrunedCount(): int
    {
        return count($this->pruned);
    }

    public function getEvaluatedCount(): int
    {
        return count($this->evaluated);
    }
}
