<?php

declare(strict_types=1);

namespace App\Mining;

use RuntimeException;

class FrequentFilter
{
    /**
     * Filters evaluated candidates to retain frequent itemsets meeting required_count.
     *
     * @param list<Itemset> $evaluatedCandidates Evaluated candidates
     * @param array<string, int> $supportCounts Support count map keyed by Itemset identity
     * @param int $requiredCount Minimum required transaction count
     * @return list<Itemset> Frequent Itemsets meeting required_count, sorted by canonical comparator
     * @throws RuntimeException if an evaluated candidate is missing from the support counts map
     */
    public function filter(array $evaluatedCandidates, array $supportCounts, int $requiredCount): array
    {
        $frequent = [];

        foreach ($evaluatedCandidates as $candidate) {
            $identity = $candidate->getIdentity();
            if (!array_key_exists($identity, $supportCounts)) {
                throw new RuntimeException("Internal invariant failure: Evaluated candidate missing from support count map.");
            }

            $count = $supportCounts[$identity];
            if ($count >= $requiredCount) {
                $frequent[] = $candidate;
            }
        }

        usort($frequent, [Itemset::class, 'compare']);

        return $frequent;
    }
}
