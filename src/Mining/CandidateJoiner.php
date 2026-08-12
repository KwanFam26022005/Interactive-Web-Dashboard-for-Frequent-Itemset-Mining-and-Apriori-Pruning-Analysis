<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class CandidateJoiner
{
    /**
     * Self-joins L(k-1) frequent itemsets to generate Ck candidates.
     *
     * @param list<Itemset> $previousFrequent List of L(k-1) Itemsets
     * @return list<Itemset> Unique Ck candidates sorted by canonical Itemset comparator
     * @throws InvalidArgumentException if previous itemsets have inconsistent sizes
     */
    public function join(array $previousFrequent): array
    {
        if (count($previousFrequent) < 2) {
            return [];
        }

        // Deduplicate input L(k-1) itemsets by binary identity
        /** @var array<string, Itemset> $uniquePrev */
        $uniquePrev = [];
        $kMinusOne = null;

        foreach ($previousFrequent as $itemset) {
            $sz = $itemset->getSize();
            if ($kMinusOne === null) {
                $kMinusOne = $sz;
            } else if ($sz !== $kMinusOne) {
                throw new InvalidArgumentException("All itemsets in CandidateJoiner must have equal size.");
            }
            $uniquePrev[$itemset->getIdentity()] = $itemset;
        }

        /** @var list<Itemset> $prevList */
        $prevList = array_values($uniquePrev);
        usort($prevList, [Itemset::class, 'compare']);

        $count = count($prevList);
        $prefixLen = $kMinusOne - 1; // k - 2 for candidate size k

        /** @var array<string, Itemset> $candidates */
        $candidates = [];

        for ($i = 0; $i < $count; $i++) {
            $itemsA = $prevList[$i]->getItems();

            for ($j = $i + 1; $j < $count; $j++) {
                $itemsB = $prevList[$j]->getItems();

                $prefixMatch = true;
                for ($p = 0; $p < $prefixLen; $p++) {
                    if ($itemsA[$p] !== $itemsB[$p]) {
                        $prefixMatch = false;
                        break;
                    }
                }

                if (!$prefixMatch) {
                    continue;
                }

                $candidateItems = $itemsA;
                $lastB = $itemsB[$prefixLen];
                if (!in_array($lastB, $candidateItems, true)) {
                    $candidateItems[] = $lastB;
                }

                $candidate = Itemset::fromCanonicalItems($candidateItems);
                $candidates[$candidate->getIdentity()] = $candidate;
            }
        }

        /** @var list<Itemset> $result */
        $result = array_values($candidates);
        usort($result, [Itemset::class, 'compare']);

        return $result;
    }
}
