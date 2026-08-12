<?php

declare(strict_types=1);

namespace App\Config;

class EnvLoader
{
    /**
     * Parse a .env file and populate getenv() and $_ENV.
     * Existing process environment variables take precedence and will not be overwritten.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $val = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            // Strip surrounding quotes
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
