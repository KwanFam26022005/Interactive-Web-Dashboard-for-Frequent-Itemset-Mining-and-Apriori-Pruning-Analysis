<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;
use JsonException;

/**
 * Strict HTTP-global validation for dataset requests.
 */
final class RequestValidator
{
    public const FROZEN_MAX_UPLOAD_BYTES = 10_485_760;

    public function __construct(
        private readonly int $uploadMaxBytes,
        private readonly ?int $phpPostMaxBytes = null
    ) {
        if ($uploadMaxBytes < 1 || $uploadMaxBytes > self::FROZEN_MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException(
                'uploadMaxBytes must be between 1 and 10485760 bytes.'
            );
        }

        if ($phpPostMaxBytes !== null && $phpPostMaxBytes < 1) {
            throw new InvalidArgumentException('phpPostMaxBytes must be null or a positive integer.');
        }
    }

    public static function parsePhpByteLimit(string|false $value): ?int
    {
        if ($value === false) {
            throw new InvalidArgumentException('PHP byte limit is unavailable.');
        }

        $value = trim($value);
        if (preg_match('/^(0|[1-9][0-9]*)([KMG])?$/iD', $value, $matches) !== 1) {
            throw new InvalidArgumentException('PHP byte limit has an unsupported representation.');
        }

        $base = $matches[1];
        if ($base === '0') {
            return null;
        }

        $multiplier = match (strtoupper($matches[2] ?? '')) {
            'K' => 1_024,
            'M' => 1_048_576,
            'G' => 1_073_741_824,
            default => 1,
        };

        $maxBase = intdiv(PHP_INT_MAX, $multiplier);
        if (
            strlen($base) > strlen((string)$maxBase)
            || (strlen($base) === strlen((string)$maxBase) && strcmp($base, (string)$maxBase) > 0)
        ) {
            throw new InvalidArgumentException('PHP byte limit exceeds the supported integer range.');
        }

        return ((int)$base) * $multiplier;
    }

    /**
     * @param list<string> $allowed
     */
    public function assertMethod(string $method, array $allowed): void
    {
        if (!in_array($method, $allowed, true)) {
            $allow = implode(', ', $allowed);

            throw new ApiException(
                405,
                'METHOD_NOT_ALLOWED',
                'The requested HTTP method is not allowed.',
                [],
                ['Allow' => $allow]
            );
        }
    }

    public function assertContentType(?string $contentType, string $expected): void
    {
        $actual = $contentType === null
            ? ''
            : strtolower(trim(explode(';', $contentType, 2)[0]));

        if ($actual === '' || $actual !== strtolower($expected)) {
            throw new ApiException(
                415,
                'UNSUPPORTED_MEDIA_TYPE',
                "Content-Type must be {$expected}."
            );
        }
    }

    public function assertPhpPostBodyAccepted(?string $contentLength): void
    {
        if ($this->phpPostMaxBytes === null || $contentLength === null) {
            return;
        }

        $contentLength = trim($contentLength);
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $contentLength) !== 1) {
            return;
        }

        $limit = (string)$this->phpPostMaxBytes;
        $exceedsLimit = strlen($contentLength) > strlen($limit)
            || (strlen($contentLength) === strlen($limit) && strcmp($contentLength, $limit) > 0);

        if ($exceedsLimit) {
            throw self::uploadTooLarge();
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function assertNoQueryParameters(array $query): void
    {
        if ($query !== []) {
            throw self::invalidRequest('Query parameters are not allowed for this request.');
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function validateDatasetQuery(array $query): ?int
    {
        foreach (array_keys($query) as $key) {
            if ($key !== 'id') {
                throw self::invalidRequest('Unknown query parameter.');
            }
        }

        if (!array_key_exists('id', $query)) {
            return null;
        }

        $id = $query['id'];
        if (!is_string($id) || preg_match('/^[1-9][0-9]*$/D', $id) !== 1) {
            throw self::invalidDatasetId();
        }

        $max = (string)PHP_INT_MAX;
        if (strlen($id) > strlen($max) || (strlen($id) === strlen($max) && strcmp($id, $max) > 0)) {
            throw self::invalidDatasetId();
        }

        return (int)$id;
    }

    /**
     * @return array{dataset_id: int, support_units: int, confidence_units: int, top_n: int}
     */
    public function validateMiningJson(string $rawBody): array
    {
        try {
            $decoded = json_decode(
                $rawBody,
                false,
                512,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
        } catch (JsonException) {
            throw new ApiException(
                400,
                'INVALID_JSON',
                'Request body must contain valid JSON.'
            );
        }

        if (!is_object($decoded)) {
            throw self::invalidRequest('Request body must be a JSON object.');
        }

        $tokens = $this->scanTopLevelObject($rawBody);
        $required = ['dataset_id', 'min_support', 'min_confidence'];
        $allowed = [...$required, 'top_n'];

        foreach (array_keys($tokens) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw self::invalidRequest('Unknown JSON field.');
            }
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $tokens)) {
                throw self::invalidRequest('A required JSON field is missing.');
            }
        }

        $datasetId = $this->parsePositiveJsonInteger($tokens['dataset_id']);
        if ($datasetId === null) {
            throw self::invalidDatasetId();
        }

        $supportUnits = $this->parseExactMillionths($tokens['min_support']);
        if ($supportUnits === null || $supportUnits < 1) {
            throw new ApiException(
                422,
                'INVALID_MIN_SUPPORT',
                'Minimum support must be an exact number between 0.000001 and 1.'
            );
        }

        $confidenceUnits = $this->parseExactMillionths($tokens['min_confidence']);
        if ($confidenceUnits === null) {
            throw new ApiException(
                422,
                'INVALID_MIN_CONFIDENCE',
                'Minimum confidence must be an exact number between 0 and 1.'
            );
        }

        $topN = 20;
        if (array_key_exists('top_n', $tokens)) {
            $topN = $this->parsePositiveJsonInteger($tokens['top_n']);
            if ($topN === null || $topN > 100) {
                throw new ApiException(
                    422,
                    'INVALID_TOP_N',
                    'Top n must be an integer between 1 and 100.'
                );
            }
        }

        return [
            'dataset_id' => $datasetId,
            'support_units' => $supportUnits,
            'confidence_units' => $confidenceUnits,
            'top_n' => $topN,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{source_filename: string, tmp_name: string, format: string, name: ?string}
     */
    public function validateDatasetImport(array $post, array $files): array
    {
        $this->assertAllowedKeys($post, ['format', 'name'], 'form');
        $this->assertAllowedKeys($files, ['file'], 'file');

        if (!array_key_exists('format', $post)) {
            throw self::invalidRequest('The format field is required.');
        }

        $format = $post['format'];
        if (!is_string($format)) {
            throw self::invalidRequest('The format field must be a scalar string.');
        }

        $name = null;
        if (array_key_exists('name', $post)) {
            if (!is_string($post['name'])) {
                throw self::invalidRequest('The name field must be a scalar string.');
            }

            $name = $post['name'];
        }

        if (!array_key_exists('file', $files)) {
            throw self::uploadFailed('An uploaded file is required.');
        }

        $file = $files['file'];
        if (!is_array($file)) {
            throw self::invalidRequest('The uploaded file metadata is malformed.');
        }

        $this->assertUploadMetadata($file);

        $sourceFilename = $file['name'];
        $temporaryName = $file['tmp_name'];
        $error = $file['error'];
        $advertisedSize = $file['size'];

        if (
            !is_string($sourceFilename)
            || !is_string($temporaryName)
            || !is_int($error)
            || !is_int($advertisedSize)
            || $advertisedSize < 0
        ) {
            throw self::invalidRequest('The uploaded file metadata is malformed.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            $this->throwUploadError($error);
        }

        if ($sourceFilename === '' || $temporaryName === '') {
            throw self::invalidRequest('The uploaded file metadata is malformed.');
        }

        if ($advertisedSize > $this->uploadMaxBytes) {
            throw self::uploadTooLarge();
        }

        return [
            'source_filename' => $sourceFilename,
            'tmp_name' => $temporaryName,
            'format' => $format,
            'name' => $name,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $allowed
     */
    private function assertAllowedKeys(array $input, array $allowed, string $kind): void
    {
        foreach (array_keys($input) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw self::invalidRequest("Unknown {$kind} field.");
            }
        }
    }

    /**
     * Captures raw top-level value tokens while preserving their original numeric lexemes.
     *
     * The complete body has already passed json_decode validation before this scanner runs.
     *
     * @return array<string, string>
     */
    private function scanTopLevelObject(string $rawBody): array
    {
        $length = strlen($rawBody);
        $offset = 0;
        $this->skipJsonWhitespace($rawBody, $offset, $length);
        $offset++;

        $tokens = [];
        $seen = [];

        while (true) {
            $this->skipJsonWhitespace($rawBody, $offset, $length);
            if ($offset < $length && $rawBody[$offset] === '}') {
                break;
            }

            $keyStart = $offset;
            $this->scanJsonString($rawBody, $offset, $length);
            $keyToken = substr($rawBody, $keyStart, $offset - $keyStart);

            try {
                $key = json_decode($keyToken, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw self::invalidRequest('The JSON object could not be inspected safely.');
            }

            if (!is_string($key)) {
                throw self::invalidRequest('The JSON object could not be inspected safely.');
            }

            $identity = 'key:' . $key;
            if (array_key_exists($identity, $seen)) {
                throw self::invalidRequest('Duplicate JSON fields are not allowed.');
            }
            $seen[$identity] = true;

            $this->skipJsonWhitespace($rawBody, $offset, $length);
            $offset++;
            $this->skipJsonWhitespace($rawBody, $offset, $length);

            $valueStart = $offset;
            $this->scanJsonValue($rawBody, $offset, $length);
            $tokens[$key] = trim(substr($rawBody, $valueStart, $offset - $valueStart));

            $this->skipJsonWhitespace($rawBody, $offset, $length);
            if ($offset < $length && $rawBody[$offset] === ',') {
                $offset++;
                continue;
            }

            break;
        }

        return $tokens;
    }

    private function skipJsonWhitespace(string $json, int &$offset, int $length): void
    {
        while ($offset < $length) {
            $character = $json[$offset];
            if ($character !== ' ' && $character !== "\t" && $character !== "\n" && $character !== "\r") {
                return;
            }

            $offset++;
        }
    }

    private function scanJsonString(string $json, int &$offset, int $length): void
    {
        $offset++;
        $escaped = false;

        while ($offset < $length) {
            $character = $json[$offset++];
            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
                continue;
            }

            if ($character === '"') {
                return;
            }
        }

        throw self::invalidRequest('The JSON object could not be inspected safely.');
    }

    private function scanJsonValue(string $json, int &$offset, int $length): void
    {
        $depth = 0;
        $inString = false;
        $escaped = false;

        while ($offset < $length) {
            $character = $json[$offset];

            if ($inString) {
                $offset++;
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                $inString = true;
                $offset++;
                continue;
            }

            if ($character === '{' || $character === '[') {
                $depth++;
                $offset++;
                continue;
            }

            if ($character === '}' || $character === ']') {
                if ($depth === 0) {
                    return;
                }

                $depth--;
                $offset++;
                continue;
            }

            if ($character === ',' && $depth === 0) {
                return;
            }

            $offset++;
        }
    }

    private function parsePositiveJsonInteger(string $token): ?int
    {
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $token) !== 1 || $token === '0') {
            return null;
        }

        $maximum = (string)PHP_INT_MAX;
        if (
            strlen($token) > strlen($maximum)
            || (strlen($token) === strlen($maximum) && strcmp($token, $maximum) > 0)
        ) {
            return null;
        }

        return (int)$token;
    }

    private function parseExactMillionths(string $token): ?int
    {
        if (
            preg_match(
                '/^(-?)(0|[1-9][0-9]*+)(?:\.([0-9]++))?(?:[eE]([+-]?)([0-9]++))?$/D',
                $token,
                $matches,
                PREG_UNMATCHED_AS_NULL
            ) !== 1
        ) {
            return null;
        }

        $integerDigits = $matches[2];
        $fractionDigits = $matches[3] ?? '';
        $coefficient = ltrim($integerDigits . $fractionDigits, '0');
        if ($coefficient === '') {
            return 0;
        }

        if ($matches[1] === '-') {
            return null;
        }

        $fractionLength = strlen($fractionDigits);
        $coefficientLength = strlen($coefficient);
        if ($fractionLength > PHP_INT_MAX - $coefficientLength - 16) {
            return null;
        }

        $exponent = $this->parseBoundedExponent(
            $matches[4] ?? '',
            $matches[5] ?? '',
            $fractionLength + $coefficientLength + 15
        );
        $shift = $exponent - $fractionLength + 6;

        if ($shift >= 0) {
            if ($coefficientLength + $shift > 7) {
                return null;
            }

            $unitDigits = $coefficient . str_repeat('0', $shift);
        } else {
            $digitsToRemove = -$shift;
            $withoutTrailingZeros = rtrim($coefficient, '0');
            $trailingZeros = $coefficientLength - strlen($withoutTrailingZeros);
            if ($digitsToRemove > $trailingZeros) {
                return null;
            }

            $unitDigits = substr($coefficient, 0, $coefficientLength - $digitsToRemove);
        }

        if (
            strlen($unitDigits) > 7
            || (strlen($unitDigits) === 7 && strcmp($unitDigits, '1000000') > 0)
        ) {
            return null;
        }

        return (int)$unitDigits;
    }

    private function parseBoundedExponent(string $sign, string $digits, int $bound): int
    {
        if ($digits === '') {
            return 0;
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $boundDigits = (string)$bound;
        if (
            strlen($digits) > strlen($boundDigits)
            || (strlen($digits) === strlen($boundDigits) && strcmp($digits, $boundDigits) > 0)
        ) {
            return $sign === '-' ? -($bound + 1) : $bound + 1;
        }

        $value = (int)$digits;
        return $sign === '-' ? -$value : $value;
    }

    /**
     * @param array<mixed> $file
     */
    private function assertUploadMetadata(array $file): void
    {
        $required = ['name', 'tmp_name', 'error', 'size'];
        $allowed = [...$required, 'type', 'full_path'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $file)) {
                throw self::invalidRequest('The uploaded file metadata is malformed.');
            }
        }

        foreach ($file as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowed, true) || !is_scalar($value)) {
                throw self::invalidRequest('The uploaded file metadata is malformed.');
            }
        }
    }

    private function throwUploadError(int $error): never
    {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw self::uploadTooLarge();
        }

        throw self::uploadFailed('The upload did not complete successfully.');
    }

    private static function invalidRequest(string $message): ApiException
    {
        return new ApiException(400, 'INVALID_REQUEST', $message);
    }

    private static function invalidDatasetId(): ApiException
    {
        return new ApiException(
            422,
            'INVALID_DATASET_ID',
            'Dataset id must be a positive integer.'
        );
    }

    private static function uploadFailed(string $message): ApiException
    {
        return new ApiException(400, 'UPLOAD_FAILED', $message);
    }

    private static function uploadTooLarge(): ApiException
    {
        return new ApiException(
            413,
            'UPLOAD_TOO_LARGE',
            'Uploaded dataset exceeds the maximum allowed size.'
        );
    }
}
