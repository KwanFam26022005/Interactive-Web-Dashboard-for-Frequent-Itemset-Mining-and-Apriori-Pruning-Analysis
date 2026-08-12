<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class ItemNormalizer
{
    /**
     * Normalizes a raw item string according to Phase 1 canonical item rules:
     * 1. Valid UTF-8
     * 2. Trim ONLY leading/trailing ASCII whitespace
     * 3. Non-empty
     * 4. 1..128 bytes
     * 5. No remaining ASCII control characters (0x00..0x1F, 0x7F)
     * 6. Case preserved, no Unicode normalization / lowercasing / accent folding
     *
     * @throws InvalidArgumentException if normalization fails
     */
    public static function normalize(string $rawItem): string
    {
        // 1. Valid UTF-8 check
        if (!preg_match('//u', $rawItem)) {
            throw new InvalidArgumentException("Item is not valid UTF-8.");
        }

        // 2. Trim ONLY leading/trailing ASCII whitespace
        // ASCII whitespace characters: 0x20 (space), 0x09 (tab), 0x0A (LF), 0x0D (CR), 0x0B (VT), 0x0C (FF)
        $trimmed = trim($rawItem, " \t\n\r\x0B\x0C");

        // 3. Result must be non-empty
        if ($trimmed === '') {
            throw new InvalidArgumentException("Item is empty after ASCII whitespace trimming.");
        }

        // 4. Result byte length must be between 1 and 128 bytes
        $byteLength = strlen($trimmed);
        if ($byteLength < 1 || $byteLength > 128) {
            throw new InvalidArgumentException("Item byte length must be between 1 and 128 bytes, got {$byteLength} bytes.");
        }

        // 5. Reject remaining ASCII control characters (0x00..0x1F, 0x7F)
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw new InvalidArgumentException("Item contains forbidden ASCII control characters.");
        }

        return $trimmed;
    }
}
