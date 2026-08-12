<?php

declare(strict_types=1);

namespace App\Tests\Oracle;

use App\Dataset\BasketCsvParser;
use App\Dataset\DatasetValidationException;

class TinyFixtureTest
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

        $fixturePath = dirname(__DIR__, 2) . '/tests/fixtures/tiny.csv';
        $assert('tiny.csv fixture exists', file_exists($fixturePath));

        $content = file_get_contents($fixturePath);
        $assert('tiny.csv content readable', $content !== false);
        if ($content === false) {
            return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
        }

        // Exact bytes, size, hash, BOM, final newline assertions
        $size = strlen($content);
        $sha = hash('sha256', $content);
        $assert('tiny.csv size is exactly 15 bytes', $size === 15, "Got {$size} bytes");
        $assert(
            'tiny.csv SHA-256 matches frozen oracle hash',
            $sha === '63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e',
            "Got {$sha}"
        );
        $assert('tiny.csv does not contain leading UTF-8 BOM', !str_starts_with($content, "\xEF\xBB\xBF"));
        $assert('tiny.csv does not contain final newline', !str_ends_with($content, "\n") && !str_ends_with($content, "\r"));

        // Parse tiny.csv as basket_csv
        $parser = new BasketCsvParser();
        $res = $parser->parse($content, 'tiny.csv');

        $assert('tiny.csv produces exactly 4 transactions', $res->getTransactionCount() === 4);
        $assert('tiny.csv produces 0 warnings', $res->getTotalWarningCount() === 0);

        $txs = $res->getTransactions();
        $assert('T1 (ordinal 1) items match {A, B, C}', isset($txs[0]) && $txs[0]->getOrdinal() === 1 && $txs[0]->getItems() === ['A', 'B', 'C']);
        $assert('T2 (ordinal 2) items match {A, B}', isset($txs[1]) && $txs[1]->getOrdinal() === 2 && $txs[1]->getItems() === ['A', 'B']);
        $assert('T3 (ordinal 3) items match {A, C}', isset($txs[2]) && $txs[2]->getOrdinal() === 3 && $txs[2]->getItems() === ['A', 'C']);
        $assert('T4 (ordinal 4) items match {A}', isset($txs[3]) && $txs[3]->getOrdinal() === 4 && $txs[3]->getItems() === ['A']);

        // Issue Cap Test (150 errors -> stored 100, total 150)
        $badContent = str_repeat("A,,C\n", 150);
        $caughtCap = false;
        try {
            $parser->parse($badContent, 'bad.csv');
        } catch (DatasetValidationException $e) {
            $caughtCap = (count($e->getIssues()) === 100 && $e->getTotalIssueCount() === 150);
        }
        $assert('Error issues capped at 100 while preserving true total count (150)', $caughtCap);

        // Warning Cap Test (150 blank lines + 1 valid line -> stored 100, total 150)
        $warnContent = str_repeat("\n", 150) . "A,B";
        $resWarn = $parser->parse($warnContent, 'warn.csv');
        $assert('Warning issues capped at 100 while preserving true total count (150)', count($resWarn->getWarnings()) === 100 && $resWarn->getTotalWarningCount() === 150);

        // Determinism Test (Repeated parsing of identical input)
        $resDet1 = $parser->parse($content, 'tiny.csv');
        $resDet2 = $parser->parse($content, 'tiny.csv');
        $detEqual = ($resDet1->getTransactionCount() === $resDet2->getTransactionCount());
        for ($i = 0; $i < $resDet1->getTransactionCount(); $i++) {
            if ($resDet1->getTransactions()[$i]->getItems() !== $resDet2->getTransactions()[$i]->getItems() ||
                $resDet1->getTransactions()[$i]->getOrdinal() !== $resDet2->getTransactions()[$i]->getOrdinal()) {
                $detEqual = false;
                break;
            }
        }
        $assert('Repeated parsing produces identical transactions, ordinals, and items', $detEqual);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
