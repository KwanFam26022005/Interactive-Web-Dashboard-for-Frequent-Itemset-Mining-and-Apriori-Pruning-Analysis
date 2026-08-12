<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Dataset\DatasetImportService;
use App\Dataset\DatasetImportLimits;
use App\Dataset\DatasetValidationException;
use App\Dataset\ParserRegistry;
use App\Http\ApiException;
use App\Http\ApiResponse;
use App\Http\JsonResponder;
use App\Http\RequestValidator;
use App\Persistence\DatasetRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class HttpInfrastructureTest
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

        self::testResponses($assert);
        self::testJsonResponder($assert);
        self::testConstructorAndMethods($assert);
        self::testContentTypes($assert);
        self::testDatasetQueries($assert);
        self::testDatasetImports($assert);
        self::testClosedEmission($assert);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testResponses(callable $assert): void
    {
        $success = ApiResponse::success(201, ['ok' => true], ['X-Test' => 'yes']);
        $assert(
            'ApiResponse success preserves immutable transport values',
            $success->getStatus() === 201
                && $success->getHeaders() === ['X-Test' => 'yes']
                && $success->getPayload() === ['ok' => true]
        );

        $error = ApiResponse::error(422, 'EXACT_CODE', 'Exact message.');
        $payload = $error->getPayload();
        $assert(
            'ApiResponse error factory produces the exact envelope',
            array_keys($payload) === ['error']
                && is_array($payload['error'])
                && array_keys($payload['error']) === ['code', 'message', 'details']
                && $payload['error']['code'] === 'EXACT_CODE'
                && $payload['error']['message'] === 'Exact message.'
                && $payload['error']['details'] instanceof \stdClass
        );
        $assert(
            'Absent error details JSON-encode as an object',
            self::encode($error->getPayload())
                === '{"error":{"code":"EXACT_CODE","message":"Exact message.","details":{}}}'
        );
        $assert(
            'Explicit empty error details JSON-encode as an object',
            self::encode(ApiResponse::error(400, 'X', 'Y', [])->getPayload())
                === '{"error":{"code":"X","message":"Y","details":{}}}'
        );

        $details = (object)['field' => 'id'];
        $detailed = ApiResponse::error(422, 'BAD_ID', 'Bad id.', $details, ['Allow' => 'GET']);
        $assert(
            'ApiResponse preserves structured details and extra headers',
            $detailed->getPayload()['error']['details'] === $details
                && $detailed->getHeaders() === ['Allow' => 'GET']
        );

        $exceptionDetails = (object)['field' => 'format'];
        $exception = new ApiException(
            400,
            'INVALID_REQUEST',
            'Request is invalid.',
            $exceptionDetails,
            ['Allow' => 'POST']
        );
        $safeResponse = ApiResponse::error(
            $exception->getStatus(),
            $exception->getApiCode(),
            $exception->getMessage(),
            $exception->getDetails(),
            $exception->getHeaders()
        );
        $safeJson = self::encode($safeResponse->getPayload());
        $assert(
            'ApiException exposes only its explicit safe mapping values',
            $exception->getStatus() === 400
                && $exception->getApiCode() === 'INVALID_REQUEST'
                && $exception->getMessage() === 'Request is invalid.'
                && $exception->getHeaders() === ['Allow' => 'POST']
                && $exception->getDetails() === $exceptionDetails
                && $exception->getPrevious() === null
                && !str_contains($safeJson, 'password mysql:host=secret D:\\private\\query.sql')
        );
    }

    private static function testJsonResponder(callable $assert): void
    {
        $responder = new JsonResponder();
        $payload = [
            'item' => '<script>café/東京 & tea</script>',
            'url' => 'https://example.test/a/b',
        ];
        $json = $responder->encode($payload);
        $assert(
            'JsonResponder preserves Unicode, slashes, and semantic HTML-like values as JSON data',
            $json === '{"item":"<script>café/東京 & tea</script>","url":"https://example.test/a/b"}'
                && !str_contains($json, '\\/')
                && !str_contains($json, '\u003C')
        );

        $encodable = true;
        try {
            $responder->assertEncodable(['run_id' => 0, 'items' => ['01', '+1']]);
        } catch (Throwable) {
            $encodable = false;
        }
        $assert('JsonResponder assertEncodable accepts a complete ordinary payload', $encodable);

        $invalidUtf8 = "\xB1\x31";
        $assertThrowsJson = false;
        try {
            $responder->assertEncodable(['unsafe' => $invalidUtf8]);
        } catch (JsonException) {
            $assertThrowsJson = true;
        }
        $assert('JsonResponder assertEncodable uses JSON_THROW_ON_ERROR', $assertThrowsJson);

        $encodeThrowsJson = false;
        try {
            $responder->encode(['recursive' => self::recursiveArray()]);
        } catch (JsonException) {
            $encodeThrowsJson = true;
        }
        $assert('JsonResponder encode fails explicitly for recursive values', $encodeThrowsJson);
    }

    private static function testConstructorAndMethods(callable $assert): void
    {
        $acceptedBounds = true;
        try {
            new RequestValidator(1);
            new RequestValidator(10_485_760);
        } catch (Throwable) {
            $acceptedBounds = false;
        }
        $assert('RequestValidator accepts the inclusive 1..10 MiB upload range', $acceptedBounds);

        foreach ([0, -1, 10_485_761] as $value) {
            $caught = false;
            try {
                new RequestValidator($value);
            } catch (InvalidArgumentException) {
                $caught = true;
            }
            $assert("RequestValidator rejects upload maximum {$value}", $caught);
        }

        $parsedLimits = [
            ['1', 1],
            ['1K', 1_024],
            ['2m', 2_097_152],
            ['2G', 2_147_483_648],
            ['0', null],
        ];
        foreach ($parsedLimits as [$input, $expected]) {
            $actual = null;
            $parsed = true;
            try {
                $actual = RequestValidator::parsePhpByteLimit($input);
            } catch (Throwable) {
                $parsed = false;
            }
            $assert("PHP byte limit {$input} parses exactly", $parsed && $actual === $expected);
        }

        foreach ([false, '', '-1', '1.5M', '1T', '999999999999999999999G'] as $input) {
            $caught = false;
            try {
                RequestValidator::parsePhpByteLimit($input);
            } catch (InvalidArgumentException) {
                $caught = true;
            }
            $assert('Unsupported PHP byte limit is rejected', $caught);
        }

        $invalidPostLimitRejected = false;
        try {
            new RequestValidator(1024, 0);
        } catch (InvalidArgumentException) {
            $invalidPostLimitRejected = true;
        }
        $assert('RequestValidator rejects a non-positive PHP post limit', $invalidPostLimitRejected);

        $postGuard = new RequestValidator(1024, 128);
        $postGuardAccepted = true;
        try {
            $postGuard->assertPhpPostBodyAccepted(null);
            $postGuard->assertPhpPostBodyAccepted('128');
            $postGuard->assertPhpPostBodyAccepted('not-a-number');
        } catch (Throwable) {
            $postGuardAccepted = false;
        }
        $assert('PHP post-size guard accepts absent, valid-boundary, and noncanonical metadata', $postGuardAccepted);
        $assert(
            'PHP post-size guard maps a discarded oversized body to 413',
            self::isApi(
                self::catchApi(static fn() => $postGuard->assertPhpPostBodyAccepted('129')),
                413,
                'UPLOAD_TOO_LARGE'
            )
        );

        $validator = new RequestValidator(1024);
        $methodAccepted = true;
        try {
            $validator->assertMethod('GET', ['GET', 'POST']);
        } catch (Throwable) {
            $methodAccepted = false;
        }
        $assert('Allowed HTTP method is accepted exactly', $methodAccepted);

        $methodError = self::catchApi(
            static fn() => $validator->assertMethod('get', ['GET', 'POST'])
        );
        $assert(
            'Unsupported method maps to 405 with exact Allow header',
            self::isApi($methodError, 405, 'METHOD_NOT_ALLOWED')
                && $methodError?->getHeaders() === ['Allow' => 'GET, POST']
        );

        $noQueryAccepted = true;
        try {
            $validator->assertNoQueryParameters([]);
        } catch (Throwable) {
            $noQueryAccepted = false;
        }
        $assert('No-query assertion accepts an empty query', $noQueryAccepted);
        $assert(
            'No-query assertion rejects every supplied query key',
            self::isApi(
                self::catchApi(static fn() => $validator->assertNoQueryParameters(['id' => '1'])),
                400,
                'INVALID_REQUEST'
            )
        );
    }

    private static function testContentTypes(callable $assert): void
    {
        $validator = new RequestValidator(1024);
        $accepted = [
            'multipart/form-data',
            'Multipart/Form-Data; boundary=abc123',
            ' multipart/form-data ; boundary="quoted"',
        ];

        foreach ($accepted as $contentType) {
            $ok = true;
            try {
                $validator->assertContentType($contentType, 'multipart/form-data');
            } catch (Throwable) {
                $ok = false;
            }
            $assert("Content-Type ignores parameters and case: {$contentType}", $ok);
        }

        foreach ([null, '', 'application/json', 'multipart/mixed'] as $contentType) {
            $error = self::catchApi(
                static fn() => $validator->assertContentType($contentType, 'multipart/form-data')
            );
            $label = $contentType ?? 'null';
            $assert(
                "Missing/wrong Content-Type {$label} maps to 415",
                self::isApi($error, 415, 'UNSUPPORTED_MEDIA_TYPE')
            );
        }
    }

    private static function testDatasetQueries(callable $assert): void
    {
        $validator = new RequestValidator(1024);
        $assert('Dataset query without id selects list mode', $validator->validateDatasetQuery([]) === null);

        foreach ([['1', 1], ['42', 42], [(string)PHP_INT_MAX, PHP_INT_MAX]] as [$input, $expected]) {
            $actual = null;
            try {
                $actual = $validator->validateDatasetQuery(['id' => $input]);
            } catch (Throwable) {
                // Assert below with the unexpected null.
            }
            $assert("Canonical dataset id {$input} is accepted", $actual === $expected);
        }

        $overflow = self::incrementDecimalString((string)PHP_INT_MAX);
        $invalid = [
            '' => '',
            'zero' => '0',
            'negative' => '-1',
            'decimal' => '1.0',
            'leading zero' => '01',
            'plus' => '+1',
            'space' => ' 1',
            'scientific' => '1e2',
            'overflow' => $overflow,
            'integer value' => 1,
            'float value' => 1.0,
            'boolean value' => true,
            'array value' => ['1'],
            'object value' => (object)['id' => '1'],
            'null value' => null,
        ];
        foreach ($invalid as $label => $id) {
            $assert(
                "Dataset id rejects {$label}",
                self::isApi(
                    self::catchApi(static fn() => $validator->validateDatasetQuery(['id' => $id])),
                    422,
                    'INVALID_DATASET_ID'
                )
            );
        }

        $assert(
            'Dataset query rejects an unknown field before id coercion',
            self::isApi(
                self::catchApi(static fn() => $validator->validateDatasetQuery(['id' => '1', 'extra' => 'x'])),
                400,
                'INVALID_REQUEST'
            )
        );
    }

    private static function testDatasetImports(callable $assert): void
    {
        $validator = new RequestValidator(10);
        $baseFile = [
            'name' => 'tiny.csv',
            'tmp_name' => 'C:\\tmp\\php123.tmp',
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ];

        $validated = $validator->validateDatasetImport(
            ['format' => 'future_profile', 'name' => ' Tiny '],
            ['file' => $baseFile]
        );
        $assert(
            'Dataset import returns validated metadata without reading bytes or rejecting unknown format',
            $validated === [
                'source_filename' => 'tiny.csv',
                'tmp_name' => 'C:\\tmp\\php123.tmp',
                'format' => 'future_profile',
                'name' => ' Tiny ',
            ]
        );

        $withPhpMetadata = $baseFile + [
            'type' => 'text/csv',
            'full_path' => 'folder/tiny.csv',
        ];
        $phpShapeAccepted = true;
        try {
            $validator->validateDatasetImport(['format' => 'basket_csv'], ['file' => $withPhpMetadata]);
        } catch (Throwable) {
            $phpShapeAccepted = false;
        }
        $assert('Real PHP scalar type/full_path upload metadata is accepted', $phpShapeAccepted);

        $nameOmitted = $validator->validateDatasetImport(
            ['format' => 'basket_csv'],
            ['file' => $baseFile]
        );
        $assert('Optional dataset name is returned as null when absent', $nameOmitted['name'] === null);

        foreach ([
            'unknown form field' => [['format' => 'basket_csv', 'extra' => 'x'], ['file' => $baseFile]],
            'unknown top-level file field' => [['format' => 'basket_csv'], ['file' => $baseFile, 'other' => $baseFile]],
            'missing format field' => [[], ['file' => $baseFile]],
            'array format field' => [['format' => ['basket_csv']], ['file' => $baseFile]],
            'boolean format field' => [['format' => true], ['file' => $baseFile]],
            'array name field' => [['format' => 'basket_csv', 'name' => ['Tiny']], ['file' => $baseFile]],
            'boolean name field' => [['format' => 'basket_csv', 'name' => false], ['file' => $baseFile]],
        ] as $label => [$post, $files]) {
            $assert(
                "Dataset import rejects {$label} as INVALID_REQUEST",
                self::isApi(
                    self::catchApi(static fn() => $validator->validateDatasetImport($post, $files)),
                    400,
                    'INVALID_REQUEST'
                )
            );
        }

        $assert(
            'Dataset import maps a missing file to UPLOAD_FAILED',
            self::isApi(
                self::catchApi(static fn() => $validator->validateDatasetImport(['format' => 'basket_csv'], [])),
                400,
                'UPLOAD_FAILED'
            )
        );

        $malformedFiles = [
            'non-array entry' => 'not-an-upload',
            'missing metadata key' => array_diff_key($baseFile, ['size' => true]),
            'unknown metadata key' => $baseFile + ['unexpected' => 'x'],
            'nested name metadata' => array_replace($baseFile, ['name' => ['tiny.csv']]),
            'nested tmp_name metadata' => array_replace($baseFile, ['tmp_name' => ['tmp']]),
            'nested error metadata' => array_replace($baseFile, ['error' => [UPLOAD_ERR_OK]]),
            'nested size metadata' => array_replace($baseFile, ['size' => [10]]),
            'nested type metadata' => $baseFile + ['type' => ['text/csv']],
            'string error metadata' => array_replace($baseFile, ['error' => '0']),
            'boolean error metadata' => array_replace($baseFile, ['error' => false]),
            'string size metadata' => array_replace($baseFile, ['size' => '10']),
            'float size metadata' => array_replace($baseFile, ['size' => 10.0]),
            'empty source name' => array_replace($baseFile, ['name' => '']),
            'empty temporary name' => array_replace($baseFile, ['tmp_name' => '']),
            'negative size' => array_replace($baseFile, ['size' => -1]),
        ];
        foreach ($malformedFiles as $label => $file) {
            $assert(
                "Dataset import rejects {$label} as INVALID_REQUEST",
                self::isApi(
                    self::catchApi(
                        static fn() => $validator->validateDatasetImport(
                            ['format' => 'basket_csv'],
                            ['file' => $file]
                        )
                    ),
                    400,
                    'INVALID_REQUEST'
                )
            );
        }

        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE] as $uploadError) {
            $file = $baseFile;
            $file['error'] = $uploadError;
            $assert(
                "PHP upload error {$uploadError} maps to UPLOAD_TOO_LARGE",
                self::isApi(
                    self::catchApi(
                        static fn() => $validator->validateDatasetImport(
                            ['format' => 'basket_csv'],
                            ['file' => $file]
                        )
                    ),
                    413,
                    'UPLOAD_TOO_LARGE'
                )
            );
        }

        foreach ([
            UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION,
            99,
        ] as $uploadError) {
            $file = $baseFile;
            $file['error'] = $uploadError;
            $assert(
                "PHP upload error {$uploadError} maps to UPLOAD_FAILED",
                self::isApi(
                    self::catchApi(
                        static fn() => $validator->validateDatasetImport(
                            ['format' => 'basket_csv'],
                            ['file' => $file]
                        )
                    ),
                    400,
                    'UPLOAD_FAILED'
                )
            );
        }

        $oversized = $baseFile;
        $oversized['size'] = 11;
        $assert(
            'Advertised upload size over effective maximum maps to 413',
            self::isApi(
                self::catchApi(
                    static fn() => $validator->validateDatasetImport(
                        ['format' => 'basket_csv'],
                        ['file' => $oversized]
                    )
                ),
                413,
                'UPLOAD_TOO_LARGE'
            )
        );

        $actualLayerStillRejects = false;
        try {
            $advertised = $baseFile;
            $advertised['size'] = 1;
            (new RequestValidator(1))->validateDatasetImport(
                ['format' => 'basket_csv'],
                ['file' => $advertised]
            );

            $repository = (new \ReflectionClass(DatasetRepository::class))->newInstanceWithoutConstructor();
            $service = new DatasetImportService(
                new ParserRegistry(),
                $repository,
                new DatasetImportLimits(1)
            );
            $service->import('AB', 'tiny.csv', 'basket_csv');
        } catch (DatasetValidationException $exception) {
            $issues = $exception->getIssues();
            $actualLayerStillRejects = isset($issues[0])
                && $issues[0]->getCode() === 'UPLOAD_TOO_LARGE';
        } catch (Throwable) {
            $actualLayerStillRejects = false;
        }
        $assert(
            'HTTP advertised-size validation is not the service actual-byte enforcement layer',
            $actualLayerStillRejects
        );
    }

    private static function testClosedEmission(callable $assert): void
    {
        $result = self::runEmitProbe('valid');
        $assert(
            'JsonResponder emits valid Unicode response through a real HTTP server',
            $result['status'] === 201
                && self::hasHeader($result['headers'], 'Content-Type', 'application/json; charset=UTF-8')
                && self::hasHeader($result['headers'], 'Allow', 'GET, POST')
                && $result['body'] === '{"value":"café/東京"}',
            self::describeProbe($result)
        );

        $closed = self::runEmitProbe('invalid');
        $assert(
            'JsonResponder fails closed to fixed 500 INTERNAL_ERROR when encoding fails',
            $closed['status'] === 500
                && self::hasHeader($closed['headers'], 'Content-Type', 'application/json; charset=UTF-8')
                && $closed['body']
                    === '{"error":{"code":"INTERNAL_ERROR","message":"An internal server error occurred.","details":{}}}',
            self::describeProbe($closed)
        );
        $assert(
            'Closed JSON encoding failure does not leak invalid value or Throwable text',
            !str_contains($closed['body'], 'Malformed UTF-8')
                && !str_contains($closed['body'], 'JsonException')
                && !str_contains($closed['body'], '\\')
        );
    }

    /**
     * @return array{status: int, headers: list<string>, body: string}
     */
    private static function runEmitProbe(string $mode): array
    {
        $php = PHP_BINARY;
        if ($php === '' || !is_file($php)) {
            throw new RuntimeException('PHP_BINARY is unavailable for the HTTP emission probe.');
        }

        $temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fim-http-' . bin2hex(random_bytes(6));
        if (!mkdir($temporaryDirectory, 0700, true)) {
            throw new RuntimeException('Unable to create the HTTP emission probe directory.');
        }

        $script = $temporaryDirectory . DIRECTORY_SEPARATOR . 'probe.php';
        $bootstrap = var_export(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'bootstrap.php', true);
        $probeSource = <<<'PHP'
<?php
declare(strict_types=1);
require __BOOTSTRAP__;
$mode = $_GET['mode'] ?? '';
$response = $mode === 'valid'
    ? \App\Http\ApiResponse::success(201, ['value' => 'café/東京'], ['Allow' => 'GET, POST'])
    : \App\Http\ApiResponse::success(200, ['unsafe' => "\xB1\x31"], ['X-Leak' => 'must-not-survive']);
(new \App\Http\JsonResponder())->emit($response);
PHP;
        $probeSource = str_replace('__BOOTSTRAP__', $bootstrap, $probeSource);
        if (file_put_contents($script, $probeSource) === false) {
            @rmdir($temporaryDirectory);
            throw new RuntimeException('Unable to create the HTTP emission probe script.');
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
        if ($socket === false) {
            @unlink($script);
            @rmdir($temporaryDirectory);
            throw new RuntimeException("Unable to allocate HTTP probe port: {$errorNumber} {$errorMessage}");
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address) || !str_contains($address, ':')) {
            @unlink($script);
            @rmdir($temporaryDirectory);
            throw new RuntimeException('Unable to resolve the HTTP probe port.');
        }
        $port = (int)substr(strrchr($address, ':'), 1);

        $command = [
            $php,
            '-d',
            'display_errors=0',
            '-S',
            "127.0.0.1:{$port}",
            '-t',
            $temporaryDirectory,
        ];
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $nullDevice, 'a'],
            2 => ['file', $nullDevice, 'a'],
        ];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($script);
            @rmdir($temporaryDirectory);
            throw new RuntimeException('Unable to start the HTTP emission probe server.');
        }

        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $connection = @stream_socket_client("tcp://127.0.0.1:{$port}", $errorNumber, $errorMessage, 0.05);
                if (is_resource($connection)) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                usleep(20_000);
            }
            if (!$ready) {
                throw new RuntimeException('HTTP emission probe server did not become ready.');
            }

            $context = stream_context_create([
                'http' => [
                    'ignore_errors' => true,
                    'timeout' => 5,
                ],
            ]);
            $body = file_get_contents("http://127.0.0.1:{$port}/probe.php?mode=" . rawurlencode($mode), false, $context);
            $headers = $http_response_header ?? [];
            if ($body === false || !isset($headers[0])) {
                throw new RuntimeException('HTTP emission probe request failed.');
            }

            preg_match('/\s(\d{3})\s/', $headers[0], $matches);
            return [
                'status' => isset($matches[1]) ? (int)$matches[1] : 0,
                'headers' => array_values($headers),
                'body' => $body,
            ];
        } finally {
            proc_terminate($process);
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(20_000);
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            @unlink($script);
            @rmdir($temporaryDirectory);
        }
    }

    /**
     * @param list<string> $headers
     */
    private static function hasHeader(array $headers, string $name, string $value): bool
    {
        $expected = strtolower("{$name}: {$value}");
        foreach ($headers as $header) {
            if (strtolower($header) === $expected) {
                return true;
            }
        }

        return false;
    }

    private static function catchApi(callable $operation): ?ApiException
    {
        try {
            $operation();
        } catch (ApiException $exception) {
            return $exception;
        }

        return null;
    }

    private static function isApi(?ApiException $exception, int $status, string $code): bool
    {
        return $exception !== null
            && $exception->getStatus() === $status
            && $exception->getApiCode() === $code;
    }

    /**
     * @return array<mixed>
     */
    private static function recursiveArray(): array
    {
        $array = [];
        $array['self'] = &$array;
        return $array;
    }

    private static function encode(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private static function incrementDecimalString(string $value): string
    {
        $digits = str_split($value);
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = (string)((int)$digits[$index] + 1);
                return implode('', $digits);
            }
            $digits[$index] = '0';
        }

        return '1' . implode('', $digits);
    }

    /**
     * @param array{status: int, headers: list<string>, body: string} $result
     */
    private static function describeProbe(array $result): string
    {
        return 'status=' . $result['status']
            . ' headers=' . implode(' | ', $result['headers'])
            . ' body=' . $result['body'];
    }
}
