<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dataset\ItemNormalizer;
use InvalidArgumentException;

class ItemNormalizerTest
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

        // 1. Valid ASCII item
        $assert('Valid ASCII item', ItemNormalizer::normalize('Item1') === 'Item1');

        // 2. Leading/trailing ASCII whitespace trimming
        $assert('ASCII whitespace trimming', ItemNormalizer::normalize(" \t\nItem1\r\v\f ") === 'Item1');

        // 3. Case preservation & A distinct from a
        $itemUpper = ItemNormalizer::normalize('A');
        $itemLower = ItemNormalizer::normalize('a');
        $assert('Case preservation', $itemUpper === 'A' && $itemLower === 'a' && $itemUpper !== $itemLower);

        // 4. UTF-8 item preservation
        $utf8Str = 'Müßli_日本語';
        $assert('UTF-8 item preservation', ItemNormalizer::normalize("  {$utf8Str}  ") === $utf8Str);

        // 5. 1-byte item valid
        $assert('1-byte item valid', strlen(ItemNormalizer::normalize('x')) === 1);

        // 6. 128-byte item valid
        $len128 = str_repeat('a', 128);
        $assert('128-byte item valid', ItemNormalizer::normalize($len128) === $len128);

        // 7. 129-byte item rejected
        $caught129 = false;
        try {
            ItemNormalizer::normalize(str_repeat('a', 129));
        } catch (InvalidArgumentException $e) {
            $caught129 = true;
        }
        $assert('129-byte item rejected', $caught129);

        // 8. Empty string rejected
        $caughtEmpty = false;
        try {
            ItemNormalizer::normalize('');
        } catch (InvalidArgumentException $e) {
            $caughtEmpty = true;
        }
        $assert('Empty string rejected', $caughtEmpty);

        // 9. ASCII-whitespace-only rejected
        $caughtWsOnly = false;
        try {
            ItemNormalizer::normalize("   \t\r\n  ");
        } catch (InvalidArgumentException $e) {
            $caughtWsOnly = true;
        }
        $assert('ASCII-whitespace-only rejected', $caughtWsOnly);

        // 10. Internal NUL / ASCII control characters rejected
        $caughtControl = false;
        try {
            ItemNormalizer::normalize("item\x00key");
        } catch (InvalidArgumentException $e) {
            $caughtControl = true;
        }
        $assert('Internal NUL control character rejected', $caughtControl);

        // 11. DEL (0x7F) character rejected
        $caughtDel = false;
        try {
            ItemNormalizer::normalize("item\x7Fkey");
        } catch (InvalidArgumentException $e) {
            $caughtDel = true;
        }
        $assert('DEL (0x7F) character rejected', $caughtDel);

        // 12. Invalid UTF-8 rejected
        $caughtUtf8 = false;
        try {
            ItemNormalizer::normalize("\xFF\xFE\xFD");
        } catch (InvalidArgumentException $e) {
            $caughtUtf8 = true;
        }
        $assert('Invalid UTF-8 rejected', $caughtUtf8);

        // 13. Non-ASCII whitespace (NBSP \xC2\xA0) is not trimmed as ASCII boundary whitespace
        $nbspStr = "\xC2\xA0item\xC2\xA0";
        $normalizedNbsp = ItemNormalizer::normalize($nbspStr);
        $assert('Non-ASCII NBSP whitespace preserved as item bytes', $normalizedNbsp === $nbspStr);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }
}
