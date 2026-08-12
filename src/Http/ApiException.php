<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Carries only explicitly client-safe HTTP failure data.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param array<string, mixed>|object $details
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $apiCode,
        string $message,
        private readonly array|object $details = [],
        private readonly array $headers = []
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    /**
     * @return array<string, mixed>|object
     */
    public function getDetails(): array|object
    {
        return $this->details;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
