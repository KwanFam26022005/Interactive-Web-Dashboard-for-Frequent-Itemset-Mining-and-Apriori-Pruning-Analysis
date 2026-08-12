<?php

declare(strict_types=1);

namespace App\Dataset;

use RuntimeException;

class DatasetValidationException extends RuntimeException
{
    /** @var list<ParserIssue> */
    private array $issues;
    private int $totalIssueCount;

    /**
     * @param list<ParserIssue> $issues
     * @param int|null $totalIssueCount
     * @param string $message
     */
    public function __construct(array $issues, ?int $totalIssueCount = null, string $message = '')
    {
        $this->totalIssueCount = $totalIssueCount ?? count($issues);
        $this->issues = array_slice(array_values($issues), 0, 100);

        if ($message === '') {
            $message = "Dataset validation failed with {$this->totalIssueCount} issues.";
            if (count($this->issues) > 0) {
                $first = $this->issues[0];
                $lineStr = $first->getLine() > 0 ? " line {$first->getLine()}" : '';
                $message .= " First issue [{$first->getCode()}]{$lineStr}: {$first->getMessage()}";
            }
        }

        parent::__construct($message);
    }

    /**
     * @return list<ParserIssue> Stored issues (at most 100)
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getTotalIssueCount(): int
    {
        return $this->totalIssueCount;
    }
}
