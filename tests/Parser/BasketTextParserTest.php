<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Dataset\BasketTextParser;
use App\Dataset\DatasetValidationException;

class BasketTextParserTest
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

        $parser = new BasketTextParser();

        // 1. Single and multiple space separation
        $res1 = $parser->parse("A B C\nX    Y", 'sample.txt');
        $assert('Single and multiple ASCII space token separation works', $res1->getTransactionCount() === 2 && $res1->getTransactions()[0]->getItems() === ['A', 'B', 'C'] && $res1->getTransactions()[1]->getItems() === ['X', 'Y']);

        // 2. Tab separation
        $res2 = $parser->parse("A\tB\tC", 'sample.dat');
        $assert('ASCII tab token separation works', $res2->getTransactions()[0]->getItems() === ['A', 'B', 'C']);

        // 3. Case preservation
        $res3 = $parser->parse("A a", 'sample.txt');
        $assert('Case preserved in text tokens', $res3->getTransactions()[0]->getItems() === ['A', 'a']);

        // 4. Duplicate item warning
        $res4 = $parser->parse("A B A", 'sample.txt');
        $assert('Duplicate text token produces DUPLICATE_ITEM warning', $res4->getTransactions()[0]->getItems() === ['A', 'B'] && $res4->getTotalWarningCount() === 1 && $res4->getWarnings()[0]->getCode() === 'DUPLICATE_ITEM');

        // 5. Blank line warning
        $res5 = $parser->parse("A B\n\nC D", 'sample.txt');
        $assert('Blank text line produces BLANK_RECORD_SKIPPED warning', $res5->getTransactionCount() === 2 && $res5->getTotalWarningCount() === 1 && $res5->getWarnings()[0]->getCode() === 'BLANK_RECORD_SKIPPED');

        // 6. Empty / blank-only upload
        $caughtEmpty = false;
        try {
            $parser->parse("", 'sample.txt');
        } catch (DatasetValidationException $e) {
            $caughtEmpty = ($e->getIssues()[0]->getCode() === 'EMPTY_UPLOAD');
        }
        $assert('Empty text file rejected', $caughtEmpty);

        // 7. Invalid UTF-8
        $caughtUtf8 = false;
        try {
            $parser->parse("\xFF\xFE", 'sample.txt');
        } catch (DatasetValidationException $e) {
            $caughtUtf8 = ($e->getIssues()[0]->getCode() === 'INVALID_UTF8');
        }
        $assert('Invalid UTF-8 text file rejected', $caughtUtf8);

        // 8. Non-ASCII Unicode whitespace (NBSP \xC2\xA0) is not treated as a token delimiter
        $nbspContent = "A\xC2\xA0B C";
        $resNbsp = $parser->parse($nbspContent, 'sample.txt');
        $assert('Non-ASCII NBSP whitespace is not treated as token delimiter', $resNbsp->getTransactions()[0]->getItems() === ["A\xC2\xA0B", "C"]);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
