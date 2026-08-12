<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Immutable persisted dataset metadata.
 *
 * This value deliberately contains no transactions: callers load the
 * canonical transaction representation separately when mining is required.
 */
final class DatasetRecord
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $format,
        private readonly string $sourceFilename,
        private readonly string $sha256,
        private readonly int $byteSize,
        private readonly int $transactionCount,
        private readonly int $uniqueItemCount,
        private readonly string $createdAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getSourceFilename(): string
    {
        return $this->sourceFilename;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getByteSize(): int
    {
        return $this->byteSize;
    }

    public function getTransactionCount(): int
    {
        return $this->transactionCount;
    }

    public function getUniqueItemCount(): int
    {
        return $this->uniqueItemCount;
    }

    /**
     * Database timestamp in the connection's configured stable format.
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
