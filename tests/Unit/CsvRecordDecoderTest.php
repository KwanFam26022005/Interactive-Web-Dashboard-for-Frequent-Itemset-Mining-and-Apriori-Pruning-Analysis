<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\CsvRecordDecoder;
use InvalidArgumentException;

class CsvRecordDecoderTest
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

        // 1. Valid CSV decoding cases
        $assert('Simple comma decoding A,B,C', CsvRecordDecoder::decode('A,B,C') === ['A', 'B', 'C']);
        $assert('Quoted comma "A,B",C', CsvRecordDecoder::decode('"A,B",C') === ['A,B', 'C']);
        $assert('Doubled quote "A""B",C', CsvRecordDecoder::decode('"A""B",C') === ['A"B', 'C']);
        $assert('Empty quoted field "",A', CsvRecordDecoder::decode('"",A') === ['', 'A']);

        // 2. Malformed quote cases (must throw InvalidArgumentException)
        $malformedCases = [
            'A"B"C,D',
            '"A"B,C',
            'A,"B"C',
            '"A"B"',
            'A,B"C"',
            '"A,B', // Unbalanced quote
            "\"A\nB\",C", // Embedded newline / unclosed quote
            "\"A\\\"B\",C", // Backslash escape attempt
        ];

        foreach ($malformedCases as $idx => $case) {
            $caught = false;
            try {
                CsvRecordDecoder::decode($case);
            } catch (InvalidArgumentException $e) {
                $caught = true;
            }
            $assert("Malformed CSV case #" . ($idx + 1) . " '{$case}' rejected", $caught);
        }

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
