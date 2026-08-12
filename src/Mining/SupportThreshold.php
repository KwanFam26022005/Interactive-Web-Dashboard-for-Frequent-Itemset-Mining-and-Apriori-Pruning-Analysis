<?php

declare(strict_types=1);

namespace App\Mining;

use InvalidArgumentException;

class SupportThreshold
{
    /**
     * Calculates the required integer transaction count using pure 64-bit integer arithmetic.
     * Formula: ceil(support_units * N / 1,000,000) = intdiv(support_units * N + 999,999, 1,000,000)
     *
     * @param int $supportUnits Integer threshold in millionths (0 < supportUnits <= 1_000_000)
     * @param int $n Total transaction count N (N > 0)
     * @return int Minimum required integer transaction count
     * @throws InvalidArgumentException if parameters are out of bounds or arithmetic would overflow
     */
    public static function calculateRequiredCount(int $supportUnits, int $n): int
    {
        if ($supportUnits <= 0 || $supportUnits > 1_000_000) {
            throw new InvalidArgumentException("support_units must be integer in range (0, 1000000]. Got {$supportUnits}.");
        }

        if ($n <= 0) {
            throw new InvalidArgumentException("Transaction count N must be a positive integer (> 0). Got {$n}.");
        }

        // Overflow check before multiplication
        if ($n > (intdiv(PHP_INT_MAX - 999_999, $supportUnits))) {
            throw new InvalidArgumentException("Integer multiplication overflow for support threshold calculation.");
        }

        $numerator = ($supportUnits * $n) + 999_999;
        return intdiv($numerator, 1_000_000);
    }
}
