<?php

declare(strict_types=1);

namespace App\Tests\Api;

use JsonException;
use RuntimeException;

/**
 * Small standard-library HTTP harness for exercising the real PHP server.
 */
final class HttpTestClient
{
    /** @var resource|null */
    private $process;

    /** @var array<int, resource> */
    private array $pipes;

    private bool $stopped = false;

    private function __construct(
        $process,
        array $pipes,
        private readonly string $host,
        private readonly int $port,
        private readonly string $stdoutPath,
        private readonly string $stderrPath
    ) {
        $this->process = $process;
        $this->pipes = $pipes;
    }

    /**
     * @param array<string, string> $environmentOverrides
     */
    public static function start(
        string $documentRoot,
        array $environmentOverrides,
        array $iniOverrides = [],
        int $readinessTimeoutMilliseconds = 8_000
    ): self {
        if (!is_dir($documentRoot)) {
            throw new RuntimeException('HTTP test document root does not exist.');
        }
        if (PHP_BINARY === '' || !is_file(PHP_BINARY)) {
            throw new RuntimeException('PHP_BINARY is unavailable for the HTTP integration test.');
        }

        $host = '127.0.0.1';
        $port = self::reserveLoopbackPort($host);
        $stdoutPath = self::temporaryLogPath('fim-http-out-');
        $stderrPath = self::temporaryLogPath('fim-http-err-');
        $environment = self::inheritedEnvironment($environmentOverrides);
        $iniSettings = [
            'display_errors' => '0',
            'html_errors' => '0',
            'log_errors' => '1',
        ];
        foreach ($iniOverrides as $name => $value) {
            if (
                !is_string($name)
                || preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/D', $name) !== 1
                || !is_string($value)
                || str_contains($value, "\r")
                || str_contains($value, "\n")
            ) {
                throw new RuntimeException('HTTP test server INI override is invalid.');
            }
            $iniSettings[$name] = $value;
        }

        $command = [PHP_BINARY];
        foreach ($iniSettings as $name => $value) {
            $command[] = '-d';
            $command[] = "{$name}={$value}";
        }
        array_push($command, '-S', "{$host}:{$port}", '-t', $documentRoot);
        $descriptorSpecification = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'ab'],
            2 => ['file', $stderrPath, 'ab'],
        ];
        $pipes = [];
        $process = @proc_open(
            $command,
            $descriptorSpecification,
            $pipes,
            dirname($documentRoot),
            $environment,
            ['bypass_shell' => true, 'suppress_errors' => true]
        );

        if (!is_resource($process)) {
            @unlink($stdoutPath);
            @unlink($stderrPath);
            throw new RuntimeException('Unable to start the PHP HTTP integration test server.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
            unset($pipes[0]);
        }

        $client = new self($process, $pipes, $host, $port, $stdoutPath, $stderrPath);

        try {
            $client->awaitReadiness($readinessTimeoutMilliseconds);
        } catch (\Throwable $throwable) {
            $logs = $client->serverLogs();
            $client->stop();
            throw new RuntimeException(
                'PHP HTTP integration test server did not become ready. ' . $logs,
                0,
                $throwable
            );
        }

        return $client;
    }

    public function baseUrl(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @return array{
     *   status: int,
     *   headers: array<string, list<string>>,
     *   body: string,
     *   json: mixed,
     *   json_object: mixed
     * }
     */
    public function request(string $method, string $target, array $headers = [], string $body = ''): array
    {
        if ($this->stopped) {
            throw new RuntimeException('HTTP test client has already been stopped.');
        }
        if (preg_match('/^[A-Z]+$/D', $method) !== 1) {
            throw new RuntimeException('HTTP test method must contain uppercase ASCII letters only.');
        }
        if (!str_starts_with($target, '/') || str_contains($target, "\r") || str_contains($target, "\n")) {
            throw new RuntimeException('HTTP test request target must be an absolute safe path.');
        }

        $requestHeaders = [
            'Host' => ["{$this->host}:{$this->port}"],
            'Accept' => ['application/json'],
            'Connection' => ['close'],
        ];
        foreach ($headers as $name => $values) {
            self::assertSafeHeader($name);
            $requestHeaders[$name] = [];
            foreach (is_array($values) ? $values : [$values] as $value) {
                self::assertSafeHeader($value);
                $requestHeaders[$name][] = $value;
            }
        }

        // The helper owns framing, including multipart framing, so this is always
        // authoritative and cannot drift from the actual bytes written below.
        foreach (array_keys($requestHeaders) as $name) {
            if (strtolower($name) === 'content-length') {
                unset($requestHeaders[$name]);
            }
        }
        $requestHeaders['Content-Length'] = [(string)strlen($body)];

        $wire = "{$method} {$target} HTTP/1.1\r\n";
        foreach ($requestHeaders as $name => $values) {
            foreach ($values as $value) {
                $wire .= "{$name}: {$value}\r\n";
            }
        }
        $wire .= "\r\n{$body}";

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errorNumber,
            $errorMessage,
            5.0,
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($socket)) {
            throw new RuntimeException(
                "Unable to connect to HTTP test server ({$errorNumber}). " . self::safeSingleLine($errorMessage)
            );
        }

        try {
            stream_set_timeout($socket, 10);
            self::writeAll($socket, $wire);
            stream_socket_shutdown($socket, STREAM_SHUT_WR);

            $rawResponse = '';
            while (!feof($socket)) {
                $chunk = fread($socket, 16_384);
                if ($chunk === false) {
                    throw new RuntimeException('Failed while reading an HTTP test response.');
                }
                $rawResponse .= $chunk;
            }
            $metadata = stream_get_meta_data($socket);
            if (($metadata['timed_out'] ?? false) === true) {
                throw new RuntimeException('Timed out while reading an HTTP test response.');
            }
        } finally {
            fclose($socket);
        }

        return self::parseResponse($rawResponse);
    }

    /**
     * @param array<string, string> $fields
     * @param list<array{field: string, filename: string, content: string, content_type?: string}> $files
     * @return array{content_type: string, body: string, boundary: string}
     */
    public static function multipart(array $fields, array $files, ?string $boundary = null): array
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            self::assertMultipartToken($name);
            $parts[] = [
                'headers' => [
                    'Content-Disposition' => 'form-data; name="' . self::quoteMultipartToken($name) . '"',
                ],
                'body' => $value,
            ];
        }

        foreach ($files as $file) {
            self::assertMultipartToken($file['field']);
            self::assertMultipartToken($file['filename']);
            $parts[] = [
                'headers' => [
                    'Content-Disposition' => 'form-data; name="'
                        . self::quoteMultipartToken($file['field'])
                        . '"; filename="'
                        . self::quoteMultipartToken($file['filename'])
                        . '"',
                    'Content-Type' => $file['content_type'] ?? 'application/octet-stream',
                ],
                'body' => $file['content'],
            ];
        }

        return self::rawMultipart($parts, $boundary);
    }

    /**
     * @param list<array{headers: array<string, string>, body: string}> $parts
     * @return array{content_type: string, body: string, boundary: string}
     */
    public static function rawMultipart(array $parts, ?string $boundary = null): array
    {
        $boundary ??= 'fim-boundary-' . bin2hex(random_bytes(12));
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $boundary) !== 1) {
            throw new RuntimeException('Multipart boundary contains unsafe characters.');
        }

        $body = '';
        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n";
            foreach ($part['headers'] as $name => $value) {
                self::assertSafeHeader($name);
                self::assertSafeHeader($value);
                $body .= "{$name}: {$value}\r\n";
            }
            $body .= "\r\n" . $part['body'] . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        return [
            'content_type' => "multipart/form-data; boundary={$boundary}",
            'body' => $body,
            'boundary' => $boundary,
        ];
    }

    public function serverLogs(): string
    {
        $stdout = @file_get_contents($this->stdoutPath);
        $stderr = @file_get_contents($this->stderrPath);

        return trim(self::safeSingleLine(($stdout === false ? '' : $stdout) . "\n" . ($stderr === false ? '' : $stderr)));
    }

    public function stop(): void
    {
        if ($this->stopped) {
            return;
        }
        $this->stopped = true;

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if (($status['running'] ?? false) === true) {
                @proc_terminate($this->process);
                $deadline = microtime(true) + 2.0;
                do {
                    usleep(20_000);
                    $status = proc_get_status($this->process);
                } while (($status['running'] ?? false) === true && microtime(true) < $deadline);

                if (($status['running'] ?? false) === true) {
                    @proc_terminate($this->process, 9);
                }
            }
            @proc_close($this->process);
        }
        $this->process = null;

        @unlink($this->stdoutPath);
        @unlink($this->stderrPath);
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function awaitReadiness(int $timeoutMilliseconds): void
    {
        if ($timeoutMilliseconds < 1 || $timeoutMilliseconds > 60_000) {
            throw new RuntimeException('HTTP readiness timeout must be between 1 and 60000 milliseconds.');
        }

        $deadline = microtime(true) + ($timeoutMilliseconds / 1_000);
        do {
            $status = proc_get_status($this->process);
            if (($status['running'] ?? false) !== true) {
                throw new RuntimeException('PHP HTTP integration test server exited before becoming ready.');
            }

            $errorNumber = 0;
            $errorMessage = '';
            $socket = @stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errorNumber,
                $errorMessage,
                0.1,
                STREAM_CLIENT_CONNECT
            );
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }

            usleep(20_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for the PHP HTTP integration test server.');
    }

    private static function reserveLoopbackPort(string $host): int
    {
        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_server(
            "tcp://{$host}:0",
            $errorNumber,
            $errorMessage,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );
        if (!is_resource($socket)) {
            throw new RuntimeException(
                "Unable to reserve an isolated HTTP test port ({$errorNumber}). " . self::safeSingleLine($errorMessage)
            );
        }

        try {
            $name = stream_socket_get_name($socket, false);
            if ($name === false || preg_match('/:(\d+)$/D', $name, $matches) !== 1) {
                throw new RuntimeException('Unable to determine the reserved HTTP test port.');
            }
            $port = (int)$matches[1];
        } finally {
            fclose($socket);
        }

        if ($port < 1 || $port > 65_535) {
            throw new RuntimeException('Reserved HTTP test port is outside the valid range.');
        }

        return $port;
    }

    private static function temporaryLogPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to allocate an HTTP test server log file.');
        }

        return $path;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private static function inheritedEnvironment(array $overrides): array
    {
        $environment = [];
        $current = getenv();
        if (is_array($current)) {
            foreach ($current as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $environment[$name] = $value;
                }
            }
        }
        foreach ($overrides as $name => $value) {
            if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                throw new RuntimeException('HTTP test server environment contains an invalid key.');
            }
            $environment[$name] = $value;
        }

        return $environment;
    }

    /**
     * @param resource $stream
     */
    private static function writeAll($stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Failed while writing an HTTP test request.');
            }
            $offset += $written;
        }
    }

    /**
     * @return array{
     *   status: int,
     *   headers: array<string, list<string>>,
     *   body: string,
     *   json: mixed,
     *   json_object: mixed
     * }
     */
    private static function parseResponse(string $rawResponse): array
    {
        $separator = strpos($rawResponse, "\r\n\r\n");
        if ($separator === false) {
            throw new RuntimeException('HTTP test response did not contain a complete header block.');
        }

        $headerBlock = substr($rawResponse, 0, $separator);
        $body = substr($rawResponse, $separator + 4);
        $lines = explode("\r\n", $headerBlock);
        $statusLine = array_shift($lines);
        if ($statusLine === null || preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})(?:\s|$)/D', $statusLine, $matches) !== 1) {
            throw new RuntimeException('HTTP test response contained an invalid status line.');
        }

        $headers = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                throw new RuntimeException('HTTP test response contained an invalid header line.');
            }
            $name = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));
            $headers[$name][] = $value;
        }

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $jsonObject = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('HTTP test response was not valid JSON.', 0, $exception);
        }

        return [
            'status' => (int)$matches[1],
            'headers' => $headers,
            'body' => $body,
            'json' => $json,
            'json_object' => $jsonObject,
        ];
    }

    private static function assertSafeHeader(string $value): void
    {
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('HTTP test header contains unsafe characters.');
        }
    }

    private static function assertMultipartToken(string $value): void
    {
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('Multipart token contains unsafe characters.');
        }
    }

    private static function quoteMultipartToken(string $value): string
    {
        return addcslashes($value, "\\\"");
    }

    private static function safeSingleLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
