<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

/**
 * Immutable import ceilings. Custom instances may only tighten the frozen
 * ceilings; the default service policy always uses the frozen values.
 */
final class DatasetImportLimits
{
    public const MAX_UPLOAD_BYTES = 10_485_760;
    public const MAX_TRANSACTIONS = 100_000;
    public const MAX_UNIQUE_ITEMS_PER_TRANSACTION = 1_000;
    public const MAX_TRANSACTION_ITEM_ROWS = 5_000_000;

    public function __construct(
        private readonly int $maxUploadBytes = self::MAX_UPLOAD_BYTES,
        private readonly int $maxTransactions = self::MAX_TRANSACTIONS,
        private readonly int $maxUniqueItemsPerTransaction = self::MAX_UNIQUE_ITEMS_PER_TRANSACTION,
        private readonly int $maxTransactionItemRows = self::MAX_TRANSACTION_ITEM_ROWS
    ) {
        self::assertWithinFrozenCeiling(
            $this->maxUploadBytes,
            self::MAX_UPLOAD_BYTES,
            'maxUploadBytes'
        );
        self::assertWithinFrozenCeiling(
            $this->maxTransactions,
            self::MAX_TRANSACTIONS,
            'maxTransactions'
        );
        self::assertWithinFrozenCeiling(
            $this->maxUniqueItemsPerTransaction,
            self::MAX_UNIQUE_ITEMS_PER_TRANSACTION,
            'maxUniqueItemsPerTransaction'
        );
        self::assertWithinFrozenCeiling(
            $this->maxTransactionItemRows,
            self::MAX_TRANSACTION_ITEM_ROWS,
            'maxTransactionItemRows'
        );
    }

    public static function frozen(): self
    {
        return new self();
    }

    public function getMaxUploadBytes(): int
    {
        return $this->maxUploadBytes;
    }

    public function getMaxTransactions(): int
    {
        return $this->maxTransactions;
    }

    public function getMaxUniqueItemsPerTransaction(): int
    {
        return $this->maxUniqueItemsPerTransaction;
    }

    public function getMaxTransactionItemRows(): int
    {
        return $this->maxTransactionItemRows;
    }

    private static function assertWithinFrozenCeiling(int $value, int $ceiling, string $name): void
    {
        if ($value <= 0 || $value > $ceiling) {
            throw new InvalidArgumentException(
                "{$name} must be a positive integer no greater than {$ceiling}."
            );
        }
    }
}
