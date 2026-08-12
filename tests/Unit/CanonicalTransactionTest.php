<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\CanonicalTransaction;
use Error;
use InvalidArgumentException;

class CanonicalTransactionTest
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

        // 1. Constructor sealing verification (direct new throws Error)
        $caughtPrivateConstructor = false;
        try {
            // @phpstan-ignore-next-line
            new CanonicalTransaction(1, '1', ['A'], ['A' => true]);
        } catch (Error $e) {
            $caughtPrivateConstructor = true;
        }
        $assert('CanonicalTransaction direct instantiation via constructor is sealed (private)', $caughtPrivateConstructor);

        // 2. Canonical item deduplication & strcmp ordering via factory
        $warnings = [];
        $tx = CanonicalTransaction::fromRawItems(1, ['C', 'A', 'B', 'A'], $warnings, 10);
        $assert('Factory method deduplicates and sorts items by strcmp', $tx->getItems() === ['A', 'B', 'C']);
        $assert('Transaction guarantees transactionKey === decimal string of ordinal', $tx->getOrdinal() === 1 && $tx->getTransactionKey() === '1');
        $assert('Duplicate item produces warning', count($warnings) === 1 && $warnings[0]->getCode() === 'DUPLICATE_ITEM' && $warnings[0]->getLine() === 10);

        // 3. One duplicate item contributes once
        $warnings2 = [];
        $tx2 = CanonicalTransaction::fromRawItems(2, ['B', 'A', 'B', 'B'], $warnings2, 12);
        $assert('Multiple occurrences of duplicate item produce single warning', count($warnings2) === 1 && $warnings2[0]->getCode() === 'DUPLICATE_ITEM');
        $assert('Duplicate item contributes once only', $tx2->getItems() === ['A', 'B']);
        $assert('Transaction guarantees transactionKey === decimal string of ordinal 2', $tx2->getOrdinal() === 2 && $tx2->getTransactionKey() === '2');

        // 4. Empty transaction rejection
        $caughtEmpty = false;
        try {
            $w = [];
            CanonicalTransaction::fromRawItems(3, [], $w, 15);
        } catch (InvalidArgumentException $e) {
            $caughtEmpty = true;
        }
        $assert('Empty transaction rejected by factory', $caughtEmpty);

        // 5. Invalid ordinal rejection
        $caughtBadOrdinal = false;
        try {
            $w = [];
            CanonicalTransaction::fromRawItems(0, ['A'], $w, 1);
        } catch (InvalidArgumentException $e) {
            $caughtBadOrdinal = true;
        }
        $assert('Ordinal < 1 rejected by factory', $caughtBadOrdinal);

        // 6. Case-distinct items coexist
        $w3 = [];
        $tx3 = CanonicalTransaction::fromRawItems(4, ['a', 'A'], $w3, 20);
        $assert('Case-distinct items (A, a) coexist in transaction', count($tx3->getItems()) === 2 && $tx3->hasItem('A') && $tx3->hasItem('a'));
        $assert('Binary strcmp order puts A before a', $tx3->getItems() === ['A', 'a']);

        // 7. Input item permutation produces identical canonical transaction
        $wPerm1 = [];
        $wPerm2 = [];
        $txPerm1 = CanonicalTransaction::fromRawItems(5, ['C', 'A', 'B'], $wPerm1, 1);
        $txPerm2 = CanonicalTransaction::fromRawItems(5, ['B', 'C', 'A'], $wPerm2, 1);
        $assert('Input item permutation produces identical canonical transaction items',
            $txPerm1->getItems() === $txPerm2->getItems() &&
            $txPerm1->getItemCount() === $txPerm2->getItemCount() &&
            $txPerm1->hasItem('A') && $txPerm1->hasItem('B') && $txPerm1->hasItem('C')
        );

        // 8. Numeric Item Identity Edge Cases (Section 12 & 13)
        $wNum = [];
        $txNum = CanonicalTransaction::fromRawItems(6, ['2', '10', '1'], $wNum, 1);
        $itemsNum = $txNum->getItems();
        $allStringsNum = true;
        foreach ($itemsNum as $it) {
            if (!is_string($it)) {
                $allStringsNum = false;
            }
        }
        $assert('Numeric items sorted by binary strcmp are strictly strings ["1", "10", "2"]',
            $itemsNum === ['1', '10', '2'] && $allStringsNum &&
            $txNum->hasItem('1') && $txNum->hasItem('10') && $txNum->hasItem('2')
        );

        $wDistinct = [];
        $txDistinct = CanonicalTransaction::fromRawItems(7, ['1', '01', '001', '1.0', '+1'], $wDistinct, 1);
        $itemsDistinct = $txDistinct->getItems();
        $allStringsDistinct = true;
        foreach ($itemsDistinct as $it) {
            if (!is_string($it)) {
                $allStringsDistinct = false;
            }
        }
        $assert('Visually similar numeric strings ("1", "01", "001", "1.0", "+1") remain 5 distinct exact strings',
            count($itemsDistinct) === 5 && $allStringsDistinct &&
            $txDistinct->hasItem('1') && $txDistinct->hasItem('01') && $txDistinct->hasItem('001') &&
            $txDistinct->hasItem('1.0') && $txDistinct->hasItem('+1')
        );

        $wDupNum = [];
        $txDupNum = CanonicalTransaction::fromRawItems(8, ['1', '1'], $wDupNum, 1);
        $assert('Duplicate numeric item "1" produces exactly one string "1" item and one DUPLICATE_ITEM warning',
            $txDupNum->getItems() === ['1'] && is_string($txDupNum->getItems()[0]) &&
            count($wDupNum) === 1 && $wDupNum[0]->getCode() === 'DUPLICATE_ITEM'
        );

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
