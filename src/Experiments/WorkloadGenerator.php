<?php

declare(strict_types=1);

namespace App\Experiments;

/**
 * Deterministic Workload Generator for RQ3 Visualization Benchmark.
 *
 * Uses Numerical Recipes 32-bit LCG (a=1664525, c=1013904223, m=2^32) with seed 42.
 */
class WorkloadGenerator
{
    public const SEED = 42;
    public const WORKLOAD_SIZES = [100, 1000, 5000, 10000];

    /**
     * Advances LCG state and returns a deterministic float in [0, 1).
     *
     * @param int $state Reference to 32-bit unsigned state
     * @return float Deterministic coordinate in [0, 1) rounded to 6 decimal places
     */
    public static function nextFloat(int &$state): float
    {
        $state = (1664525 * $state + 1013904223) & 0xFFFFFFFF;
        return round($state / 4294967296.0, 6);
    }

    /**
     * Generates deterministic workload data for size N.
     *
     * @param int $n Workload point count
     * @return array{schema_version: string, workload_id: string, size: int, seed: int, domain: array{x: list<int>, y: list<int>}, base_points: list<array{id: int, x: float, y: float}>, update_points: list<array{id: int, x: float, y: float}>}
     */
    public static function generate(int $n): array
    {
        // Deterministic stream initialization based on seed and size
        $baseState = (self::SEED * 1009 + $n) & 0xFFFFFFFF;
        $updateState = ($baseState * 69069 + 1) & 0xFFFFFFFF;

        $basePoints = [];
        for ($i = 1; $i <= $n; $i++) {
            $basePoints[] = [
                'id' => $i,
                'x' => self::nextFloat($baseState),
                'y' => self::nextFloat($baseState),
            ];
        }

        $updatePoints = [];
        for ($i = 1; $i <= $n; $i++) {
            $updatePoints[] = [
                'id' => $i,
                'x' => self::nextFloat($updateState),
                'y' => self::nextFloat($updateState),
            ];
        }

        return [
            'schema_version' => '1.0.0',
            'workload_id' => "WORKLOAD-N{$n}",
            'size' => $n,
            'seed' => self::SEED,
            'domain' => [
                'x' => [0, 1],
                'y' => [0, 1],
            ],
            'base_points' => $basePoints,
            'update_points' => $updatePoints,
        ];
    }
}
