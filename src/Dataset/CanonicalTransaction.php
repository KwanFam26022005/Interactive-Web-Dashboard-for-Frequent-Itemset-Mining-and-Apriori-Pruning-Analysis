<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class CanonicalTransaction
{
    private int $ordinal;
    private string $transactionKey;
    /** @var list<string> */
    private array $canonicalItems;
    /** @var array<string, true> */
    private array $membershipIndex;

    /**
     * Factory method: Normalizes raw items, deduplicates, sorts by strcmp, and validates canonical invariants.
     *
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

        /** @var list<string> $canonicalItems */
        $canonicalItems = [];
        /** @var array<string, true> $membershipIndex */
        $membershipIndex = [];
        /** @var array<string, true> $seenDuplicates */
        $seenDuplicates = [];

        foreach ($rawItems as $rawItem) {
            $normalized = ItemNormalizer::normalize($rawItem);
            $encodedKey = CanonicalItemIndexKey::encode($normalized);

            if (isset($membershipIndex[$encodedKey])) {
                if (!isset($seenDuplicates[$encodedKey])) {
                    $seenDuplicates[$encodedKey] = true;
                    $warnings[] = new ParserIssue(
                        'DUPLICATE_ITEM',
                        $physicalLine,
                        "Duplicate canonical item '{$normalized}' in transaction record."
                    );
                }
                continue;
            }

            $membershipIndex[$encodedKey] = true;
            $canonicalItems[] = $normalized;
        }

        if (count($canonicalItems) === 0) {
            throw new InvalidArgumentException("Transaction must contain at least one item.");
        }

        // Sort canonical items using binary byte strcmp comparison
        usort($canonicalItems, 'strcmp');

        return new self($ordinal, (string)$ordinal, $canonicalItems, $membershipIndex);
    }

    /**
     * Sealed private constructor to prevent public instantiation into a non-canonical state.
     *
     * @param list<string> $canonicalItems Pre-sorted canonical item strings
     * @param array<string, true> $membershipIndex Internal encoded membership index
     */
    private function __construct(int $ordinal, string $transactionKey, array $canonicalItems, array $membershipIndex)
    {
        $this->ordinal = $ordinal;
        $this->transactionKey = $transactionKey;
        $this->canonicalItems = $canonicalItems;
        $this->membershipIndex = $membershipIndex;
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
     * Every element is guaranteed to be a PHP string.
     *
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->canonicalItems;
    }

    public function hasItem(string $item): bool
    {
        $encodedKey = CanonicalItemIndexKey::encode($item);
        return isset($this->membershipIndex[$encodedKey]);
    }

    public function getItemCount(): int
    {
        return count($this->canonicalItems);
    }
}
