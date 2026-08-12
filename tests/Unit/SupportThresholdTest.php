<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Mining\SupportThreshold;
use InvalidArgumentException;

class SupportThresholdTest
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

        // Section 24 exact boundary test cases
        $assert('N=4, units=500000 => required_count=2', SupportThreshold::calculateRequiredCount(500000, 4) === 2);
        $assert('N=4, units=500001 => required_count=3', SupportThreshold::calculateRequiredCount(500001, 4) === 3);
        $assert('N=4, units=1000000 => required_count=4', SupportThreshold::calculateRequiredCount(1000000, 4) === 4);
        $assert('N=4, units=1 => required_count=1', SupportThreshold::calculateRequiredCount(1, 4) === 1);
        $assert('N=3, units=333333 => required_count=1', SupportThreshold::calculateRequiredCount(333333, 3) === 1);
        $assert('N=3, units=333334 => required_count=2', SupportThreshold::calculateRequiredCount(333334, 3) === 2);

        // Invalid boundaries
        $caughtUnitsZero = false;
        try {
            SupportThreshold::calculateRequiredCount(0, 4);
        } catch (InvalidArgumentException $e) {
            $caughtUnitsZero = true;
        }
        $assert('units=0 rejected', $caughtUnitsZero);

        $caughtUnitsOver = false;
        try {
            SupportThreshold::calculateRequiredCount(1000001, 4);
        } catch (InvalidArgumentException $e) {
            $caughtUnitsOver = true;
        }
        $assert('units=1000001 rejected', $caughtUnitsOver);

        $caughtNZero = false;
        try {
            SupportThreshold::calculateRequiredCount(500000, 0);
        } catch (InvalidArgumentException $e) {
            $caughtNZero = true;
        }
        $assert('N=0 rejected', $caughtNZero);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
