<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class CanonicalTransaction
{
    private int $ordinal;
    private string $transactionKey;
    /** @var array<string, true> */
    private array $membershipMap;

    /**
     * @param int $ordinal 1-indexed transaction ordinal
     * @param array<int, string> $rawItems Source items to be normalized and added to transaction
     * @param list<ParserIssue> $warnings Warning collector array reference
     * @param int $physicalLine Source physical line number for warnings
     */
    public static function fromRawItems(int $ordinal, array $rawItems, array &$warnings = [], int $physicalLine = 0): self
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException("Transaction ordinal must be positive integer.");
        }

        $membershipMap = [];
        $seenDuplicates = [];

        foreach ($rawItems as $rawItem) {
            $normalized = ItemNormalizer::normalize($rawItem);
            if (isset($membershipMap[$normalized])) {
                if (!isset($seenDuplicates[$normalized])) {
                    $seenDuplicates[$normalized] = true;
                    $warnings[] = new ParserIssue(
                        'DUPLICATE_ITEM',
                        $physicalLine,
                        "Duplicate canonical item '{$normalized}' in transaction record."
                    );
                }
                continue;
            }
            $membershipMap[$normalized] = true;
        }

        if (count($membershipMap) === 0) {
            throw new InvalidArgumentException("Transaction must contain at least one item.");
        }

        // Sort membership map keys using binary byte strcmp comparison
        uksort($membershipMap, 'strcmp');

        return new self($ordinal, (string)$ordinal, $membershipMap);
    }

    /**
     * @param array<string, true> $membershipMap Pre-sorted canonical membership map
     */
    public function __construct(int $ordinal, string $transactionKey, array $membershipMap)
    {
        if ($ordinal < 1) {
            throw new InvalidArgumentException("Transaction ordinal must be positive integer.");
        }
        if (count($membershipMap) === 0) {
            throw new InvalidArgumentException("Transaction must contain at least one item.");
        }

        $this->ordinal = $ordinal;
        $this->transactionKey = $transactionKey;
        $this->membershipMap = $membershipMap;
    }

    public function getOrdinal(): int
    {
        return $this->ordinal;
    }

    public function getTransactionKey(): string
    {
        return $this->transactionKey;
    }

    /**
     * Returns canonical item strings sorted ascending by PHP strcmp byte comparison.
     *
     * @return list<string>
     */
    public function getItems(): array
    {
        return array_keys($this->membershipMap);
    }

    /**
     * Returns associative membership map keyed by canonical item string.
     *
     * @return array<string, true>
     */
    public function getMembershipMap(): array
    {
        return $this->membershipMap;
    }

    public function hasItem(string $item): bool
    {
        return isset($this->membershipMap[$item]);
    }

    public function getItemCount(): int
    {
        return count($this->membershipMap);
    }
}
