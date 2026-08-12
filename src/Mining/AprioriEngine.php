<?php

declare(strict_types=1);

namespace App\Mining;

use App\Dataset\CanonicalItemIndexKey;
use App\Dataset\CanonicalTransaction;
use Closure;
use InvalidArgumentException;

class AprioriEngine
{
    private CandidateJoiner $joiner;
    private CandidatePruner $pruner;
    private SupportCounter $counter;
    private FrequentFilter $filter;
    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param (Closure(): int)|null $clock Optional injected monotonic nanosecond timer callback for deterministic tests
     */
    public function __construct(
        ?CandidateJoiner $joiner = null,
        ?CandidatePruner $pruner = null,
        ?SupportCounter $counter = null,
        ?FrequentFilter $filter = null,
        ?Closure $clock = null
    ) {
        $this->joiner = $joiner ?? new CandidateJoiner();
        $this->pruner = $pruner ?? new CandidatePruner();
        $this->counter = $counter ?? new SupportCounter();
        $this->filter = $filter ?? new FrequentFilter();
        $this->clock = $clock ?? static fn(): int => (int)hrtime(true);
    }

    /**
     * Runs Apriori algorithm over in-memory canonical transactions.
     *
     * @param list<CanonicalTransaction> $transactions Canonical in-memory dataset
     * @param int $supportUnits Integer threshold in millionths (0 < supportUnits <= 1_000_000)
     * @param int $candidateLimit Maximum cumulative generated candidates (default 250,000)
     * @param float $deadlineSeconds Maximum allowed execution time in seconds (default 30.0)
     * @return AprioriResult Exact Apriori result
     * @throws InvalidArgumentException on invalid input parameters
     * @throws MiningLimitExceededException if candidate limit or deadline is exceeded
     */
    public function run(
        array $transactions,
        int $supportUnits,
        int $candidateLimit = 250000,
        float $deadlineSeconds = 30.0
    ): AprioriResult {
        $txCount = count($transactions);
        if ($txCount === 0) {
            throw new InvalidArgumentException("Transaction list cannot be empty.");
        }

        if ($candidateLimit <= 0) {
            throw new InvalidArgumentException("candidateLimit must be a positive integer (> 0). Got {$candidateLimit}.");
        }

        if (!is_finite($deadlineSeconds) || $deadlineSeconds <= 0) {
            throw new InvalidArgumentException("deadlineSeconds must be a finite positive number (> 0).");
        }

        // Validate and compute required_count BEFORE starting timer
        $requiredCount = SupportThreshold::calculateRequiredCount($supportUnits, $txCount);
        $deadlineNs = (int)($deadlineSeconds * 1_000_000_000.0);

        // Monotonic nanosecond timer starts immediately before C1 discovery scan
        $startNs = ($this->clock)();

        $cumulativeGenerated = 0;
        $levels = [];
        $frequentItemsets = [];
        $authoritativeSupportMap = [];

        $this->checkDeadline($startNs, $deadlineNs);

        // --------------------------------------------------
        // Level 1 — Singleton Scan
        // --------------------------------------------------
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

        /** @var list<Itemset> $singletons */
        /** @var array<string, int> $singletonCountsByIdent */
        $singletons = [];
        $singletonCountsByIdent = [];

        foreach ($singletonMap as $data) {
            $itemStr = $data['item'];
            $cnt = $data['count'];
            $itemset = Itemset::fromCanonicalItems([$itemStr]);
            $singletons[] = $itemset;
            $singletonCountsByIdent[$itemset->getIdentity()] = $cnt;
        }

        usort($singletons, [Itemset::class, 'compare']);

        $c1Generated = count($singletons);
        $cumulativeGenerated += $c1Generated;
        if ($cumulativeGenerated > $candidateLimit) {
            throw new MiningLimitExceededException("Mining limit exceeded: cumulative generated candidates ({$cumulativeGenerated}) exceeds limit ({$candidateLimit}).");
        }

        /** @var list<Itemset> $l1Frequent */
        $l1Frequent = [];
        foreach ($singletons as $singleton) {
            $ident = $singleton->getIdentity();
            $cnt = $singletonCountsByIdent[$ident];
            $authoritativeSupportMap[$ident] = $cnt;

            if ($cnt >= $requiredCount) {
                $l1Frequent[] = $singleton;
            }
        }

        $c1FrequentCount = count($l1Frequent);
        $levels[] = new LevelMetrics(
            1,
            'singleton_scan',
            $c1Generated,
            0,
            $c1Generated,
            $c1FrequentCount
        );

        foreach ($l1Frequent as $freqSet) {
            $frequentItemsets[] = $freqSet;
        }

        $this->checkDeadline($startNs, $deadlineNs);

        if ($c1FrequentCount === 0) {
            // Validate totals and invariants before stopping clock
            new AprioriResult($requiredCount, $frequentItemsets, $authoritativeSupportMap, $levels, 0, 0);
            $this->checkDeadline($startNs, $deadlineNs);
            $endNs = ($this->clock)();

            return new AprioriResult(
                $requiredCount,
                $frequentItemsets,
                $authoritativeSupportMap,
                $levels,
                0,
                $endNs - $startNs
            );
        }

        // --------------------------------------------------
        // Levels k >= 2 Orchestration
        // --------------------------------------------------
        $prevFrequent = $l1Frequent;
        $k = 2;

        while (!empty($prevFrequent)) {
            $this->checkDeadline($startNs, $deadlineNs);

            // 1. CandidateJoiner
            $generatedCandidates = $this->joiner->join($prevFrequent);
            $this->checkDeadline($startNs, $deadlineNs);

            $genCount = count($generatedCandidates);

            // If join returns 0 candidates, STOP without adding a synthetic level
            if ($genCount === 0) {
                break;
            }

            if (($cumulativeGenerated + $genCount) > $candidateLimit) {
                throw new MiningLimitExceededException("Mining limit exceeded: cumulative generated candidates (" . ($cumulativeGenerated + $genCount) . ") exceeds limit ({$candidateLimit}).");
            }
            $cumulativeGenerated += $genCount;

            // 2. CandidatePruner
            $partition = $this->pruner->prune($generatedCandidates, $prevFrequent);
            $prunedList = $partition->getPruned();
            $evaluatedList = $partition->getEvaluated();

            // 3. SupportCounter (evaluated candidates only)
            $evalCounts = $this->counter->countSupport($transactions, $evaluatedList);

            foreach ($evalCounts as $ident => $cnt) {
                $authoritativeSupportMap[$ident] = $cnt;
            }

            // 4. FrequentFilter
            $lk = $this->filter->filter($evaluatedList, $evalCounts, $requiredCount);
            $freqCount = count($lk);

            $levels[] = new LevelMetrics(
                $k,
                'join_prune',
                $genCount,
                count($prunedList),
                count($evaluatedList),
                $freqCount
            );

            foreach ($lk as $freqSet) {
                $frequentItemsets[] = $freqSet;
            }

            $this->checkDeadline($startNs, $deadlineNs);

            if ($freqCount === 0) {
                break;
            }

            $prevFrequent = $lk;
            $k++;
        }

        $maxK = 0;
        foreach ($levels as $lvl) {
            if ($lvl->getFrequent() > 0) {
                $maxK = max($maxK, $lvl->getK());
            }
        }

        // Validate complete AprioriResult totals and invariants before stopping timer
        new AprioriResult(
            $requiredCount,
            $frequentItemsets,
            $authoritativeSupportMap,
            $levels,
            $maxK,
            0
        );

        $this->checkDeadline($startNs, $deadlineNs);
        $endNs = ($this->clock)();

        return new AprioriResult(
            $requiredCount,
            $frequentItemsets,
            $authoritativeSupportMap,
            $levels,
            $maxK,
            $endNs - $startNs
        );
    }

    private function checkDeadline(int $startNs, int $deadlineNs): void
    {
        $nowNs = ($this->clock)();
        if (($nowNs - $startNs) >= $deadlineNs) {
            throw new MiningLimitExceededException("Mining limit exceeded: execution deadline exceeded.");
        }
    }
}
