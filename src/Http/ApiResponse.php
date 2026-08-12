<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Immutable transport value for controller responses.
 */
final class ApiResponse
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly array $payload
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function success(int $status, array $payload, array $headers = []): self
    {
        return new self($status, $headers, $payload);
    }

    /**
     * @param array<string, mixed>|object $details
     * @param array<string, string> $headers
     */
    public static function error(
        int $status,
        string $code,
        string $message,
        array|object $details = [],
        array $headers = []
    ): self {
        return new self(
            $status,
            $headers,
            [
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => self::normalizeDetails($details),
                ],
            ]
        );
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed>|object $details
     */
    private static function normalizeDetails(array|object $details): object
    {
        if (is_object($details)) {
            return $details;
        }

        if ($details === []) {
            return new \stdClass();
        }

        return (object)$details;
    }
}
