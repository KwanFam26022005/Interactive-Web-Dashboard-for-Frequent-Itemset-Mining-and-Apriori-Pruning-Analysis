<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\CanonicalTransaction;
use App\Mining\HeatmapBuilder;
use InvalidArgumentException;

class HeatmapBuilderTest
{
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $msg = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
            } else {
                $failed++;
                $results[] = "[FAIL] {$name}: {$msg}";
            }
        };

        $builder = new HeatmapBuilder();
        $w = [];

        // 1. Input Validation
        $caughtEmptyTx = false;
        try {
            $builder->build([], 25);
        } catch (InvalidArgumentException $e) {
            $caughtEmptyTx = true;
        }
        $assert('Empty transaction list rejected with InvalidArgumentException', $caughtEmptyTx);

        $t1 = CanonicalTransaction::fromRawItems(1, ['A', 'B'], $w, 1);
        $t2 = CanonicalTransaction::fromRawItems(2, ['A', 'C'], $w, 2);
        $txs = [$t1, $t2];

        $caughtMax0 = false;
        try {
            $builder->build($txs, 0);
        } catch (InvalidArgumentException $e) {
            $caughtMax0 = true;
        }
        $assert('maxItems <= 0 rejected with InvalidArgumentException', $caughtMax0);

        $caughtMax26 = false;
        try {
            $builder->build($txs, 26);
        } catch (InvalidArgumentException $e) {
            $caughtMax26 = true;
        }
        $assert('maxItems > 25 rejected with InvalidArgumentException', $caughtMax26);

        // 2. Binary strcmp Tie Order Test (A vs a)
        $tTie1 = CanonicalTransaction::fromRawItems(1, ['a', 'A', 'b'], $w, 1);
        $tTie2 = CanonicalTransaction::fromRawItems(2, ['a', 'A', 'c'], $w, 2);
        $resTie = $builder->build([$tTie1, $tTie2], 25);

        // Both 'A' and 'a' have support count 2. Binary strcmp: 'A' (65) < 'a' (97). So 'A' comes before 'a'!
        $itemsTie = $resTie->getItems();
        $assert('Binary strcmp tie-breaking puts uppercase A before lowercase a', $itemsTie[0] === 'A' && $itemsTie[1] === 'a');

        // 3. Truncation Test (maxItems = 2 with 3 items available)
        $resTrunc = $builder->build($txs, 2);
        $itemsTrunc = $resTrunc->getItems();
        $matrixTrunc = $resTrunc->getMatrix();

        $assert('maxItems = 2 selects top 2 items', count($itemsTrunc) === 2 && $itemsTrunc === ['A', 'B']);
        $assert('Truncated matrix dimensions match selected items count (2x2)', count($matrixTrunc) === 2 && count($matrixTrunc[0]) === 2);

        // 4. Matrix Invariants Verification
        $resInv = $builder->build($txs, 25);
        $itemsInv = $resInv->getItems(); // ['A', 'B', 'C']
        $matInv = $resInv->getMatrix();
        $M = count($itemsInv);

        $isSquareSymmetric = true;
        $diagMatchesSingleton = true;
        $offDiagBounded = true;

        for ($i = 0; $i < $M; $i++) {
            if (count($matInv[$i]) !== $M) {
                $isSquareSymmetric = false;
            }
            for ($j = 0; $j < $M; $j++) {
                if ($matInv[$i][$j] !== $matInv[$j][$i]) {
                    $isSquareSymmetric = false;
                }
                if ($i !== $j) {
                    if ($matInv[$i][$j] > $matInv[$i][$i] || $matInv[$i][$j] > $matInv[$j][$j]) {
                        $offDiagBounded = false;
                    }
                }
            }
        }

        $assert('Matrix is square and symmetric', $isSquareSymmetric);
        $assert('Off-diagonal co-occurrence is bounded by participating diagonals', $offDiagBounded);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
