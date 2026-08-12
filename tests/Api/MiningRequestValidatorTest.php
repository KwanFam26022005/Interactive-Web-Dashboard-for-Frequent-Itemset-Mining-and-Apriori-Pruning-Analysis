<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Http\ApiException;
use App\Http\RequestValidator;
use Throwable;

final class MiningRequestValidatorTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (string $name, bool $condition, string $message = '') use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}: {$message}";
        };

        $validator = new RequestValidator(1_024);

        self::testAcceptedThresholds($validator, $assert);
        self::testThresholdRejections($validator, $assert);
        self::testIntegerFields($validator, $assert);
        self::testJsonShape($validator, $assert);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testAcceptedThresholds(RequestValidator $validator, callable $assert): void
    {
        $default = $validator->validateMiningJson(
            '{"dataset_id":1,"min_support":0.5,"min_confidence":0.75}'
        );
        $assert(
            'Mining request returns exact units and default top_n',
            $default === [
                'dataset_id' => 1,
                'support_units' => 500_000,
                'confidence_units' => 750_000,
                'top_n' => 20,
            ],
            self::describe($default)
        );

        $supportCases = [
            '0.000001' => 1,
            '0.123456' => 123_456,
            '0.1234560' => 123_456,
            '1' => 1_000_000,
            '1.000000' => 1_000_000,
            '1e-6' => 1,
            '10e-7' => 1,
            '123456e-6' => 123_456,
            '1e0' => 1_000_000,
        ];

        foreach ($supportCases as $token => $expected) {
            $actual = $validator->validateMiningJson(
                '{"dataset_id":1,"min_support":' . $token . ',"min_confidence":0}'
            );
            $assert(
                "Exact min_support token {$token} is accepted",
                $actual['support_units'] === $expected,
                self::describe($actual)
            );
        }

        $confidenceCases = [
            '0' => 0,
            '-0' => 0,
            '0.123456' => 123_456,
            '0.1234560' => 123_456,
            '1' => 1_000_000,
            '1e-6' => 1,
            '10e-7' => 1,
            '1e0' => 1_000_000,
        ];

        foreach ($confidenceCases as $token => $expected) {
            $actual = $validator->validateMiningJson(
                '{"dataset_id":1,"min_support":1,"min_confidence":' . $token . '}'
            );
            $assert(
                "Exact min_confidence token {$token} is accepted",
                $actual['confidence_units'] === $expected,
                self::describe($actual)
            );
        }
    }

    private static function testThresholdRejections(RequestValidator $validator, callable $assert): void
    {
        $supportTokens = [
            '0',
            '-0.000001',
            '1.000001',
            '2',
            '0.0000001',
            '0.1234567',
            '1e-7',
            '1e1',
            '1e400',
            '"0.5"',
            'true',
            'false',
            'null',
            '[]',
            '{}',
        ];

        foreach ($supportTokens as $token) {
            $error = self::catchApi(
                static fn() => $validator->validateMiningJson(
                    '{"dataset_id":1,"min_support":' . $token . ',"min_confidence":0}'
                )
            );
            $assert(
                "min_support rejects {$token} without float rounding",
                self::isApi($error, 422, 'INVALID_MIN_SUPPORT'),
                self::describeError($error)
            );
        }

        $confidenceTokens = [
            '-0.000001',
            '1.000001',
            '2',
            '0.0000001',
            '0.1234567',
            '1e-7',
            '1e1',
            '1e400',
            '"0.5"',
            'true',
            'false',
            'null',
            '[]',
            '{}',
        ];

        foreach ($confidenceTokens as $token) {
            $error = self::catchApi(
                static fn() => $validator->validateMiningJson(
                    '{"dataset_id":1,"min_support":1,"min_confidence":' . $token . '}'
                )
            );
            $assert(
                "min_confidence rejects {$token} without float rounding",
                self::isApi($error, 422, 'INVALID_MIN_CONFIDENCE'),
                self::describeError($error)
            );
        }
    }

    private static function testIntegerFields(RequestValidator $validator, callable $assert): void
    {
        $integer = $validator->validateMiningJson(
            '{"dataset_id":' . PHP_INT_MAX . ',"min_support":1,"min_confidence":0,"top_n":100}'
        );
        $assert(
            'dataset_id and top_n accept their exact integer boundaries',
            $integer['dataset_id'] === PHP_INT_MAX && $integer['top_n'] === 100,
            self::describe($integer)
        );

        $invalidIds = ['0', '-1', '1.0', '1e0', '"1"', 'true', 'false', 'null', '[]', '{}'];
        foreach ($invalidIds as $token) {
            $error = self::catchApi(
                static fn() => $validator->validateMiningJson(
                    '{"dataset_id":' . $token . ',"min_support":1,"min_confidence":0}'
                )
            );
            $assert(
                "dataset_id rejects non-positive/non-integer token {$token}",
                self::isApi($error, 422, 'INVALID_DATASET_ID'),
                self::describeError($error)
            );
        }

        $overflow = self::incrementDecimalString((string)PHP_INT_MAX);
        $overflowError = self::catchApi(
            static fn() => $validator->validateMiningJson(
                '{"dataset_id":' . $overflow . ',"min_support":1,"min_confidence":0}'
            )
        );
        $assert(
            'dataset_id rejects an integer beyond the platform range',
            self::isApi($overflowError, 422, 'INVALID_DATASET_ID'),
            self::describeError($overflowError)
        );

        $invalidTopN = ['0', '-1', '101', '1.0', '1e0', '"1"', 'true', 'false', 'null', '[]', '{}'];
        foreach ($invalidTopN as $token) {
            $error = self::catchApi(
                static fn() => $validator->validateMiningJson(
                    '{"dataset_id":1,"min_support":1,"min_confidence":0,"top_n":' . $token . '}'
                )
            );
            $assert(
                "top_n rejects out-of-range/non-integer token {$token}",
                self::isApi($error, 422, 'INVALID_TOP_N'),
                self::describeError($error)
            );
        }
    }

    private static function testJsonShape(RequestValidator $validator, callable $assert): void
    {
        foreach (['', '{', 'NaN', 'Infinity', '-Infinity', '{"x":NaN}', '[1,]'] as $body) {
            $error = self::catchApi(static fn() => $validator->validateMiningJson($body));
            $assert(
                'Malformed/non-JSON syntax maps to INVALID_JSON: ' . ($body === '' ? '<empty>' : $body),
                self::isApi($error, 400, 'INVALID_JSON'),
                self::describeError($error)
            );
        }

        foreach (['[]', '"body"', '1', '1.0', 'true', 'false', 'null'] as $body) {
            $error = self::catchApi(static fn() => $validator->validateMiningJson($body));
            $assert(
                "Valid non-object JSON root {$body} maps to INVALID_REQUEST",
                self::isApi($error, 400, 'INVALID_REQUEST'),
                self::describeError($error)
            );
        }

        $shapeBodies = [
            '{}' => 'missing all required fields',
            '{"dataset_id":1,"min_support":1}' => 'missing confidence',
            '{"dataset_id":1,"min_support":1,"min_confidence":0,"debug":true}' => 'unknown field',
            '{"dataset_id":1,"dataset_id":2,"min_support":1,"min_confidence":0}' => 'literal duplicate field',
            '{"dataset_id":1,"dataset_\u0069d":2,"min_support":1,"min_confidence":0}' => 'escaped duplicate field',
        ];

        foreach ($shapeBodies as $body => $label) {
            $error = self::catchApi(static fn() => $validator->validateMiningJson($body));
            $assert(
                "Mining JSON rejects {$label}",
                self::isApi($error, 400, 'INVALID_REQUEST'),
                self::describeError($error)
            );
        }
    }

    private static function catchApi(callable $operation): ?ApiException
    {
        try {
            $operation();
        } catch (ApiException $exception) {
            return $exception;
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function isApi(?ApiException $error, int $status, string $code): bool
    {
        return $error instanceof ApiException
            && $error->getStatus() === $status
            && $error->getApiCode() === $code;
    }

    /** @param array<string, mixed> $value */
    private static function describe(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'not encodable';
    }

    private static function describeError(?ApiException $error): string
    {
        return $error instanceof ApiException
            ? $error->getStatus() . ' ' . $error->getApiCode()
            : 'no ApiException';
    }

    private static function incrementDecimalString(string $value): string
    {
        $digits = str_split($value);
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string)(((int)$digits[$index]) + 1);
                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        return '1' . implode('', $digits);
    }
}
