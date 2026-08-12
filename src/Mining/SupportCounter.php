<?php

declare(strict_types=1);

namespace App\Mining;

use App\Dataset\CanonicalTransaction;

class SupportCounter
{
    /**
     * Computes integer support counts for evaluated candidates across canonical transactions.
     *
     * @param list<CanonicalTransaction> $transactions Loaded dataset transactions
     * @param list<Itemset> $candidates Evaluated candidate Itemsets
     * @return array<string, int> Authoritative support count map keyed by Itemset identity
     */
    public function countSupport(array $transactions, array $candidates): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        foreach ($candidates as $candidate) {
            $counts[$candidate->getIdentity()] = 0;
        }

        if (count($transactions) === 0 || count($candidates) === 0) {
            return $counts;
        }

        foreach ($candidates as $candidate) {
            $cItems = $candidate->getItems();
            $identity = $candidate->getIdentity();
            $matchCount = 0;

            foreach ($transactions as $tx) {
                $containsAll = true;
                foreach ($cItems as $item) {
                    if (!$tx->hasItem($item)) {
                        $containsAll = false;
                        break;
                    }
                }
                if ($containsAll) {
                    $matchCount++;
                }
            }

            $counts[$identity] = $matchCount;
        }

        return $counts;
    }
}
