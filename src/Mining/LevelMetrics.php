<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class LevelMetrics
{
    private int $k;
    private string $source;
    private int $generated;
    private int $pruned;
    private int $evaluated;
    private int $frequent;

    public function __construct(
        int $k,
        string $source,
        int $generated,
        int $pruned,
        int $evaluated,
        int $frequent
    ) {
        if ($k < 1) {
            throw new InvalidArgumentException("Level k must be >= 1. Got {$k}.");
        }

        if ($source !== 'singleton_scan' && $source !== 'join_prune') {
            throw new InvalidArgumentException("Unknown level source '{$source}'. Must be 'singleton_scan' or 'join_prune'.");
        }

        if ($k === 1 && $source !== 'singleton_scan') {
            throw new InvalidArgumentException("Level k=1 source must be 'singleton_scan'. Got '{$source}'.");
        }

        if ($k >= 2 && $source !== 'join_prune') {
            throw new InvalidArgumentException("Level k>1 source must be 'join_prune'. Got '{$source}'.");
        }

        if ($generated < 0 || $pruned < 0 || $evaluated < 0 || $frequent < 0) {
            throw new InvalidArgumentException("Level count metrics must be non-negative integers.");
        }

        if ($generated !== ($pruned + $evaluated)) {
            throw new InvalidArgumentException("Level invariant failure: generated ({$generated}) must equal pruned ({$pruned}) + evaluated ({$evaluated}).");
        }

        if ($frequent > $evaluated) {
            throw new InvalidArgumentException("Level invariant failure: frequent ({$frequent}) must be <= evaluated ({$evaluated}).");
        }

        $this->k = $k;
        $this->source = $source;
        $this->generated = $generated;
        $this->pruned = $pruned;
        $this->evaluated = $evaluated;
        $this->frequent = $frequent;
    }

    public function getK(): int
    {
        return $this->k;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getGenerated(): int
    {
        return $this->generated;
    }

    public function getPruned(): int
    {
        return $this->pruned;
    }

    public function getEvaluated(): int
    {
        return $this->evaluated;
    }

    public function getFrequent(): int
    {
        return $this->frequent;
    }

    /**
     * @return array{k: int, source: string, generated: int, pruned: int, evaluated: int, frequent: int}
     */
    public function toArray(): array
    {
        return [
            'k' => $this->k,
            'source' => $this->source,
            'generated' => $this->generated,
            'pruned' => $this->pruned,
            'evaluated' => $this->evaluated,
            'frequent' => $this->frequent,
        ];
    }
}
