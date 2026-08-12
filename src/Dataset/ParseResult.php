<?php

declare(strict_types=1);

namespace App\Dataset;

class ParseResult
{
    /** @var list<CanonicalTransaction> */
    private array $transactions;
    /** @var list<ParserIssue> */
    private array $warnings;
    private int $totalWarningCount;

    /**
     * @param list<CanonicalTransaction> $transactions
     * @param list<ParserIssue> $warnings
     * @param int|null $totalWarningCount
     */
    public function __construct(array $transactions, array $warnings = [], ?int $totalWarningCount = null)
    {
        $this->transactions = array_values($transactions);
        $this->totalWarningCount = $totalWarningCount ?? count($warnings);
        // Store at most 100 warning objects
        $this->warnings = array_slice(array_values($warnings), 0, 100);
    }

    /**
     * @return list<CanonicalTransaction>
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * @return list<ParserIssue>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getTotalWarningCount(): int
    {
        return $this->totalWarningCount;
    }

    public function getTransactionCount(): int
    {
        return count($this->transactions);
    }
}
