<?php

declare(strict_types=1);

namespace App\Experiments;

/**
 * Deterministic Workload Generator for RQ3 Visualization Benchmark.
 *
 * Implements literal Mulberry32 PRNG starting from exact frozen initial state 0xDEADBEEF (3735928559).
 * Single canonical artifact: experiments/visualization/workload_data.json
 */
class WorkloadGenerator
{
    public const SEED = 0xDEADBEEF; // 3735928559
    public const WORKLOAD_SIZES = [100, 1000, 5000, 10000];

    /**
     * Emulates 32-bit integer multiplication (Math.imul in JS).
     */
    public static function imul32(int $a, int $b): int
    {
        $a = $a & 0xFFFFFFFF;
        $b = $b & 0xFFFFFFFF;
        $ah = ($a >> 16) & 0xFFFF;
        $al = $a & 0xFFFF;
        $bh = ($b >> 16) & 0xFFFF;
        $bl = $b & 0xFFFF;
        return (($al * $bl) + ((($ah * $bl + $al * $bh) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    /**
     * Advances Mulberry32 state and returns a deterministic float in [0, 1).
     *
     * @param int $state Reference to 32-bit unsigned state
     * @return float Deterministic coordinate in [0, 1) rounded to 6 decimal places
     */
    public static function nextFloat(int &$state): float
    {
        $state = ($state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $state;
        $t = self::imul32($t ^ ($t >> 15), $t | 1);
        $term = ($t + self::imul32($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;
        $t = ($t ^ $term) & 0xFFFFFFFF;
        $res = ($t ^ ($t >> 14)) & 0xFFFFFFFF;
        return round($res / 4294967296.0, 6);
    }

    /**
     * Generates a single workload dataset for size N starting directly from SEED (0xDEADBEEF).
     * Exactly 50% (N/2) of points are displaced via y_i <- round(fmod(y_i + 0.1, 1.0), 6).
     *
     * @param int $n Workload point count
     * @return array{size: int, base_points: list<array{id: int, x: float, y: float}>, update_points: list<array{id: int, x: float, y: float}>}
     */
    public static function generateWorkloadForSize(int $n): array
    {
        $state = self::SEED;

        $basePoints = [];
        for ($i = 1; $i <= $n; $i++) {
            $basePoints[] = [
                'id' => $i,
                'x' => self::nextFloat($state),
                'y' => self::nextFloat($state),
            ];
        }

        // Exactly 50% (even-indexed point IDs) displaced: y_i <- round(fmod(y_i + 0.1, 1.0), 6)
        $updatePoints = [];
        for ($i = 0; $i < $n; $i++) {
            $base = $basePoints[$i];
            if (($i + 1) % 2 === 0) {
                $newY = round(fmod($base['y'] + 0.1, 1.0), 6);
                $updatePoints[] = [
                    'id' => $base['id'],
                    'x' => $base['x'],
                    'y' => $newY,
                ];
            } else {
                $updatePoints[] = [
                    'id' => $base['id'],
                    'x' => $base['x'],
                    'y' => $base['y'],
                ];
            }
        }

        return [
            'size' => $n,
            'base_points' => $basePoints,
            'update_points' => $updatePoints,
        ];
    }

    /**
     * Generates the single canonical workload bundle containing N = 100, 1000, 5000, 10000.
     *
     * @return array<string, mixed>
     */
    public static function generateCanonicalBundle(): array
    {
        $workloads = [];
        foreach (self::WORKLOAD_SIZES as $size) {
            $workloads[(string)$size] = self::generateWorkloadForSize($size);
        }

        return [
            'schema_version' => '1.0.0',
            'benchmark_id' => 'BENCHMARK-VIS-SCATTER-V1',
            'generator' => 'Mulberry32',
            'seed' => '0xDEADBEEF',
            'seed_decimal' => self::SEED,
            'domain' => [
                'x' => [0, 1],
                'y' => [0, 1],
            ],
            'displacement_rule' => 'y_i <- round(fmod(y_i + 0.1, 1.0), 6) for even point IDs (exactly 50%)',
            'workloads' => $workloads,
        ];
    }
}
