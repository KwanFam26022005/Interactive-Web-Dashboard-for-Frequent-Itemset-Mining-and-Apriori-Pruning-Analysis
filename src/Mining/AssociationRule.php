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

    public function __construct(
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

    public static function createWithDenominatorCheck(
        Itemset $antecedent,
        Itemset $consequent,
        int $supportCountF,
        int $supportCountA,
        int $supportCountB,
        int $transactionCountN
    ): self {
        if ($transactionCountN <= 0) {
            throw new RuntimeException("Zero denominator invariant failure: transaction count N must be > 0. Got {$transactionCountN}.");
        }

        if ($supportCountA <= 0) {
            throw new RuntimeException("Zero denominator invariant failure: antecedent support count must be > 0. Got {$supportCountA}.");
        }

        if ($supportCountB <= 0) {
            throw new RuntimeException("Zero denominator invariant failure: consequent support count must be > 0. Got {$supportCountB}.");
        }

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
