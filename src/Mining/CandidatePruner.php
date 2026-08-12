<?php

declare(strict_types=1);

namespace App\Mining;

class CandidatePruner
{
    /**
     * Prunes generated Ck candidates by checking if ALL k immediate (k-1) subsets are present in L(k-1).
     *
     * @param list<Itemset> $candidates Generated Ck candidates
     * @param list<Itemset> $previousFrequent L(k-1) frequent itemsets
     * @return PrunePartition Partition containing pruned and evaluated Itemsets
     */
    public function prune(array $candidates, array $previousFrequent): PrunePartition
    {
        /** @var array<string, true> $prevMap */
        $prevMap = [];
        foreach ($previousFrequent as $f) {
            $prevMap[$f->getIdentity()] = true;
        }

        $pruned = [];
        $evaluated = [];

        foreach ($candidates as $candidate) {
            $items = $candidate->getItems();
            $k = count($items);
            $shouldPrune = false;

            // Enumerate all k immediate subsets of size k-1
            for ($i = 0; $i < $k; $i++) {
                $subsetItems = $items;
                array_splice($subsetItems, $i, 1);

                $subset = Itemset::fromCanonicalItems($subsetItems);
                if (!isset($prevMap[$subset->getIdentity()])) {
                    $shouldPrune = true;
                    break;
                }
            }

            if ($shouldPrune) {
                $pruned[] = $candidate;
            } else {
                $evaluated[] = $candidate;
            }
        }

        usort($pruned, [Itemset::class, 'compare']);
        usort($evaluated, [Itemset::class, 'compare']);

        return new PrunePartition($pruned, $evaluated);
    }
}
