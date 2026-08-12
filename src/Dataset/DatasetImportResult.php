<?php

declare(strict_types=1);

namespace App\Dataset;

use App\Persistence\DatasetRecord;
use InvalidArgumentException;

/**
 * Immutable successful import outcome, independent of HTTP response shaping.
 */
final class DatasetImportResult
{
    /** @var list<ParserIssue> */
    private readonly array $warnings;

    /**
     * @param list<ParserIssue> $warnings
     */
    public function __construct(
        private readonly DatasetRecord $dataset,
        array $warnings,
        private readonly int $totalWarningCount
    ) {
        foreach ($warnings as $warning) {
            if (!$warning instanceof ParserIssue) {
                throw new InvalidArgumentException('warnings must contain only ParserIssue values.');
            }
        }
        if ($this->totalWarningCount < count($warnings)) {
            throw new InvalidArgumentException('totalWarningCount cannot be less than the returned warning count.');
        }

        $this->warnings = array_values($warnings);
    }

    public function getDataset(): DatasetRecord
    {
        return $this->dataset;
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
}
