<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\Dataset\DatasetValidationException;
use App\Dataset\ParserRegistry;
use InvalidArgumentException;

class ParserRegistryTest
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

        $registry = new ParserRegistry();

        // 1. Exact format mappings
        $assert('basket_csv parser registered', $registry->getParser('basket_csv')->getFormatToken() === 'basket_csv');
        $assert('basket_txt parser registered', $registry->getParser('basket_txt')->getFormatToken() === 'basket_txt');
        $assert('mushroom parser registered', $registry->getParser('mushroom')->getFormatToken() === 'mushroom');

        // 2. Unknown format throws InvalidArgumentException
        $caughtUnknown = false;
        try {
            $registry->getParser('unknown_format');
        } catch (InvalidArgumentException $e) {
            $caughtUnknown = true;
        }
        $assert('Unknown format token rejected', $caughtUnknown);

        // 3. Extension profile matching
        // basket_csv
        $caughtCsvMismatch = false;
        try {
            $registry->validateExtension('basket_csv', 'data.txt');
        } catch (DatasetValidationException $e) {
            $caughtCsvMismatch = ($e->getIssues()[0]->getCode() === 'PROFILE_MISMATCH');
        }
        $assert('basket_csv rejects .txt extension profile mismatch', $caughtCsvMismatch);

        // basket_txt
        $caughtTxtOk = true;
        try {
            $registry->validateExtension('basket_txt', 'retail.dat');
            $registry->validateExtension('basket_txt', 'sample.TXT');
        } catch (DatasetValidationException $e) {
            $caughtTxtOk = false;
        }
        $assert('basket_txt accepts .dat and uppercase .TXT', $caughtTxtOk);

        // mushroom
        $caughtMushroomOk = true;
        try {
            $registry->validateExtension('mushroom', 'agaricus.data');
            $registry->validateExtension('mushroom', 'mushroom.csv');
        } catch (DatasetValidationException $e) {
            $caughtMushroomOk = false;
        }
        $assert('mushroom accepts .data and .csv', $caughtMushroomOk);

        $caughtMushroomMismatch = false;
        try {
            $registry->validateExtension('mushroom', 'mushroom.txt');
        } catch (DatasetValidationException $e) {
            $caughtMushroomMismatch = ($e->getIssues()[0]->getCode() === 'PROFILE_MISMATCH');
        }
        $assert('mushroom rejects .txt extension profile mismatch', $caughtMushroomMismatch);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
