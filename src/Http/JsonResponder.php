<?php

declare(strict_types=1);

namespace App\Http;

use Throwable;

/**
 * Owns safe JSON encoding and emission at the HTTP boundary.
 */
final class JsonResponder
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    private const CONTENT_TYPE = 'application/json; charset=UTF-8';

    private const INTERNAL_ERROR_JSON = '{"error":{"code":"INTERNAL_ERROR","message":"An internal server error occurred.","details":{}}}';

    public function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }

    public function assertEncodable(mixed $value): void
    {
        $this->encode($value);
    }

    public function emit(ApiResponse $response): void
    {
        try {
            $body = $this->encode($response->getPayload());
            $this->emitEncoded($response->getStatus(), $response->getHeaders(), $body);
        } catch (Throwable) {
            $this->emitInternalError();
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function emitEncoded(int $status, array $headers, string $body): void
    {
        http_response_code($status);
        header('Content-Type: ' . self::CONTENT_TYPE, true);

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $body;
    }

    private function emitInternalError(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . self::CONTENT_TYPE, true);
        }

        echo self::INTERNAL_ERROR_JSON;
    }
}
