<?php

declare(strict_types=1);

namespace App\Dataset;

class CanonicalItemIndexKey
{
    /**
     * Encodes a canonical item string into a collision-safe internal binary key
     * that PHP cannot coerce to an integer array key.
     *
     * @param string $item Exact canonical item string
     * @return string Binary length-prefixed internal index key
     */
    public static function encode(string $item): string
    {
        return pack('N', strlen($item)) . $item;
    }
}
