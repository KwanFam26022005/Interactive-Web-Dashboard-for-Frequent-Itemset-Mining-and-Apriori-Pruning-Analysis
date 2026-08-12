<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Dataset\BasketCsvParser;
use App\Dataset\DatasetValidationException;

class BasketCsvParserTest
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

        $parser = new BasketCsvParser();

        // 1. Ordinary CSV
        $res = $parser->parse("A,B,C\nX,Y", 'test.csv');
        $assert('Ordinary CSV parses transactions', $res->getTransactionCount() === 2 && $res->getTransactions()[0]->getItems() === ['A', 'B', 'C'] && $res->getTransactions()[1]->getItems() === ['X', 'Y']);

        // 2. Quoted comma
        $res2 = $parser->parse("\"A,B\",C", 'test.csv');
        $assert('Quoted comma supported as single item', $res2->getTransactions()[0]->getItems() === ['A,B', 'C']);

        // 3. Escaped quote
        $res3 = $parser->parse("\"A\"\"B\",C", 'test.csv');
        $assert('Escaped quotes inside field supported', $res3->getTransactions()[0]->getItems() === ['A"B', 'C']);

        // 4. Leading UTF-8 BOM
        $res4 = $parser->parse("\xEF\xBB\xBFItem1,Item2", 'test.csv');
        $assert('Leading UTF-8 BOM stripped cleanly', $res4->getTransactions()[0]->getItems() === ['Item1', 'Item2']);

        // 5. Blank row warning
        $res5 = $parser->parse("A,B\n\nC,D", 'test.csv');
        $assert('Blank row produces BLANK_RECORD_SKIPPED warning and preserves ordinals', $res5->getTransactionCount() === 2 && $res5->getTotalWarningCount() === 1 && $res5->getWarnings()[0]->getCode() === 'BLANK_RECORD_SKIPPED' && $res5->getWarnings()[0]->getLine() === 2 && $res5->getTransactions()[1]->getOrdinal() === 2);

        // 6. Duplicate item warning
        $res6 = $parser->parse("A,B,A", 'test.csv');
        $assert('Duplicate item produces DUPLICATE_ITEM warning', $res6->getTransactions()[0]->getItems() === ['A', 'B'] && $res6->getTotalWarningCount() === 1 && $res6->getWarnings()[0]->getCode() === 'DUPLICATE_ITEM');

        // 7. Empty field error
        $caughtEmpty = false;
        try {
            $parser->parse("A,,C", 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtEmpty = ($e->getIssues()[0]->getCode() === 'EMPTY_FIELD');
        }
        $assert('Empty CSV field fails dataset parse with EMPTY_FIELD error', $caughtEmpty);

        // 8. Whitespace-normalized empty field error
        $caughtWsEmpty = false;
        try {
            $parser->parse("A,   ,C", 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtWsEmpty = ($e->getIssues()[0]->getCode() === 'EMPTY_FIELD');
        }
        $assert('Whitespace-normalized empty field fails dataset parse', $caughtWsEmpty);

        // 9. Malformed CSV quote error
        $caughtMalformed = false;
        try {
            $parser->parse("\"A,B,C", 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtMalformed = ($e->getIssues()[0]->getCode() === 'MALFORMED_CSV');
        }
        $assert('Unbalanced quote fails dataset parse with MALFORMED_CSV error', $caughtMalformed);

        // 10. Invalid UTF-8
        $caughtUtf8 = false;
        try {
            $parser->parse("\xFF\xFE\xFD", 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtUtf8 = ($e->getIssues()[0]->getCode() === 'INVALID_UTF8');
        }
        $assert('Invalid UTF-8 content rejected', $caughtUtf8);

        // 11. Empty upload
        $caughtUploadEmpty = false;
        try {
            $parser->parse('', 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtUploadEmpty = ($e->getIssues()[0]->getCode() === 'EMPTY_UPLOAD');
        }
        $assert('Empty upload rejected with EMPTY_UPLOAD error', $caughtUploadEmpty);

        // 12. Blank-only upload
        $caughtBlankOnly = false;
        try {
            $parser->parse("  \n\r\n  ", 'test.csv');
        } catch (DatasetValidationException $e) {
            $caughtBlankOnly = ($e->getIssues()[0]->getCode() === 'BLANK_ONLY_UPLOAD');
        }
        $assert('Blank-only upload rejected with BLANK_ONLY_UPLOAD error', $caughtBlankOnly);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
