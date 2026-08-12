<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Dataset\DatasetValidationException;
use App\Dataset\MushroomParser;

class MushroomParserTest
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

        $parser = new MushroomParser();

        // 1. Positional mapping & ? validation
        $content1 = "x,x,?\ny,z,a";
        $res1 = $parser->parse($content1, 'agaricus.data');
        $tx1 = $res1->getTransactions()[0];
        $assert('Mushroom positional prefix mapping c1=, c2=, c3=', $tx1->getItems() === ['c1=x', 'c2=x', 'c3=?']);
        $assert('Same raw code in two columns remains two distinct item keys', $tx1->hasItem('c1=x') && $tx1->hasItem('c2=x') && $tx1->getItemCount() === 3);

        // 2. Blank row before first record establishes fixed field count on first valid record
        $content2 = "\n\nx,y\na,b";
        $res2 = $parser->parse($content2, 'mushroom.csv');
        $assert('Blank lines before first record skipped with warning', $res2->getTransactionCount() === 2 && $res2->getTotalWarningCount() === 2 && $res2->getWarnings()[0]->getCode() === 'BLANK_RECORD_SKIPPED');

        // 3. Inconsistent later field count rejected
        $content3 = "x,y,z\na,b";
        $caughtInconsistent = false;
        try {
            $parser->parse($content3, 'agaricus.data');
        } catch (DatasetValidationException $e) {
            $caughtInconsistent = ($e->getIssues()[0]->getCode() === 'INCONSISTENT_FIELD_COUNT');
        }
        $assert('Inconsistent field count rejected with INCONSISTENT_FIELD_COUNT error', $caughtInconsistent);

        // 4. Empty categorical field rejected
        $content4 = "x,,z";
        $caughtEmptyField = false;
        try {
            $parser->parse($content4, 'agaricus.data');
        } catch (DatasetValidationException $e) {
            $caughtEmptyField = ($e->getIssues()[0]->getCode() === 'EMPTY_FIELD');
        }
        $assert('Empty categorical field rejected with EMPTY_FIELD error', $caughtEmptyField);

        // 5. Malformed quote rejected in Mushroom parser
        $contentMalformed = "\"x\"y,z";
        $caughtMalformed = false;
        try {
            $parser->parse($contentMalformed, 'mushroom.csv');
        } catch (DatasetValidationException $e) {
            $caughtMalformed = ($e->getIssues()[0]->getCode() === 'MALFORMED_CSV');
        }
        $assert('Mushroom parser rejects malformed balanced quote line as MALFORMED_CSV', $caughtMalformed);

        // 6. UTF-8 BOM support
        $contentBOM = "\xEF\xBB\xBFa,b,c";
        $resBOM = $parser->parse($contentBOM, 'mushroom.csv');
        $assert('Mushroom parser strips leading UTF-8 BOM', $resBOM->getTransactions()[0]->getItems() === ['c1=a', 'c2=b', 'c3=c']);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
