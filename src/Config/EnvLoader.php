<?php

declare(strict_types=1);

namespace App\Config;

use InvalidArgumentException;

class EnvLoader
{
    /**
     * Parse a .env file and populate getenv() and $_ENV.
     * Existing process environment variables take precedence and will not be overwritten.
     * Throws InvalidArgumentException on malformed syntax lines.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_contains($trimmed, '=')) {
                throw new InvalidArgumentException("Malformed .env syntax on line " . ($lineNum + 1) . ": missing '=' operator.");
            }

            $parts = explode('=', $trimmed, 2);
            $key = trim($parts[0]);
            $val = trim($parts[1]);

            if ($key === '' || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
                throw new InvalidArgumentException("Malformed .env syntax on line " . ($lineNum + 1) . ": invalid key '{$key}'.");
            }

            // Strip surrounding matching quotes
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            // Process environment variables override .env
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}
