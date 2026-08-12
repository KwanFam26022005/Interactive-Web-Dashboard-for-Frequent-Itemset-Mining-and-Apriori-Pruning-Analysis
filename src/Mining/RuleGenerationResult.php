<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class RuleGenerationResult
{
    /** @var list<AssociationRule> */
    private array $rules;
    private int $rulesCount;
    private int $elapsedNanoseconds;

    /**
     * @param list<AssociationRule> $rules
     * @param int $rulesCount
     * @param int $elapsedNanoseconds
     */
    public function __construct(
        array $rules,
        int $rulesCount,
        int $elapsedNanoseconds
    ) {
        if ($rulesCount !== count($rules)) {
            throw new InvalidArgumentException("RuleGenerationResult invariant failure: rulesCount ({$rulesCount}) != count(rules) (" . count($rules) . ").");
        }

        $this->rules = array_values($rules);
        $this->rulesCount = $rulesCount;
        $this->elapsedNanoseconds = max(0, $elapsedNanoseconds);
    }

    /**
     * @return list<AssociationRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getRulesCount(): int
    {
        return $this->rulesCount;
    }

    public function getElapsedNanoseconds(): int
    {
        return $this->elapsedNanoseconds;
    }
}
