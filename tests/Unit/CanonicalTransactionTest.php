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
            new CanonicalTransaction(1, '1', ['A' => true]);
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
        $assert('Input item permutation produces identical canonical transaction items', $txPerm1->getItems() === $txPerm2->getItems() && $txPerm1->getMembershipMap() === $txPerm2->getMembershipMap());

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
