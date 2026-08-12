<?php

declare(strict_types=1);

namespace App\Mining;

use RuntimeException;

class AssociationRule
{
    private Itemset $antecedent;
    private Itemset $consequent;
    private int $supportCount;
    private float $support;
    private float $confidence;
    private float $lift;
    private string $identity;

    private function __construct(
        Itemset $antecedent,
        Itemset $consequent,
        int $supportCount,
        float $support,
        float $confidence,
        float $lift
    ) {
        $antIdent = $antecedent->getIdentity();
        $consIdent = $consequent->getIdentity();

        // Collision-safe binary length-prefixed identity encoding
        $this->identity = pack('N', strlen($antIdent)) . $antIdent . pack('N', strlen($consIdent)) . $consIdent;
        $this->antecedent = $antecedent;
        $this->consequent = $consequent;
        $this->supportCount = $supportCount;
        $this->support = $support;
        $this->confidence = $confidence;
        $this->lift = $lift;
    }

    /**
     * Authoritative factory deriving metrics and enforcing complete rule-domain invariants.
     *
     * @param Itemset $antecedent Non-empty canonical antecedent Itemset
     * @param Itemset $consequent Non-empty canonical consequent Itemset
     * @param int $supportCountF Integer support count of union F = A union B
     * @param int $supportCountA Integer support count of antecedent A
     * @param int $supportCountB Integer support count of consequent B
     * @param int $transactionCountN Total transactions N (> 0)
     * @return self Sealed AssociationRule instance
     * @throws RuntimeException on any rule-side or support-count invariant violation
     */
    public static function createFromCounts(
        Itemset $antecedent,
        Itemset $consequent,
        int $supportCountF,
        int $supportCountA,
        int $supportCountB,
        int $transactionCountN
    ): self {
        // 1. Rule Side Invariants
        if (count($antecedent->getItems()) === 0) {
            throw new RuntimeException("Antecedent Itemset cannot be empty.");
        }

        if (count($consequent->getItems()) === 0) {
            throw new RuntimeException("Consequent Itemset cannot be empty.");
        }

        if (!empty(array_intersect($antecedent->getItems(), $consequent->getItems()))) {
            throw new RuntimeException("Antecedent and consequent Itemsets must be disjoint.");
        }

        // 2. Support-Count Invariants
        if ($transactionCountN <= 0) {
            throw new RuntimeException("Zero denominator invariant failure: transaction count N must be > 0. Got {$transactionCountN}.");
        }

        if ($supportCountF <= 0 || $supportCountA <= 0 || $supportCountB <= 0) {
            throw new RuntimeException("Zero/negative denominator invariant failure: support counts must be > 0. Got F={$supportCountF}, A={$supportCountA}, B={$supportCountB}.");
        }

        if ($supportCountF > $supportCountA) {
            throw new RuntimeException("Impossible support count invariant failure: supportCountF ({$supportCountF}) cannot exceed supportCountA ({$supportCountA}).");
        }

        if ($supportCountF > $supportCountB) {
            throw new RuntimeException("Impossible support count invariant failure: supportCountF ({$supportCountF}) cannot exceed supportCountB ({$supportCountB}).");
        }

        if ($supportCountF > $transactionCountN || $supportCountA > $transactionCountN || $supportCountB > $transactionCountN) {
            throw new RuntimeException("Support count invariant failure: support counts cannot exceed transactionCount N ({$transactionCountN}).");
        }

        // 3. Unrounded Metric Derivation
        $support = (float)$supportCountF / (float)$transactionCountN;
        $confidence = (float)$supportCountF / (float)$supportCountA;
        $lift = $confidence / ((float)$supportCountB / (float)$transactionCountN);

        return new self(
            $antecedent,
            $consequent,
            $supportCountF,
            $support,
            $confidence,
            $lift
        );
    }

    public function getAntecedent(): Itemset
    {
        return $this->antecedent;
    }

    public function getConsequent(): Itemset
    {
        return $this->consequent;
    }

    public function getSupportCount(): int
    {
        return $this->supportCount;
    }

    public function getSupport(): float
    {
        return $this->support;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function getLift(): float
    {
        return $this->lift;
    }

    public function getIdentity(): string
    {
        return $this->identity;
    }
}
