<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use PDO;
use RuntimeException;
use Throwable;

final class DatasetHttpIntegrationTest
{
    private const ENDPOINT = '/api/datasets.php';
    private const HTTP_UPLOAD_LIMIT = 64;

    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];
        $assert = static function (string $name, bool $condition, string $message = '') use (
            &$passed,
            &$failed,
            &$results
        ): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}" . ($message === '' ? '' : ": {$message}");
        };

        $originalAppEnv = getenv('APP_ENV');
        $originalDatabase = getenv('DB_NAME');
        $client = null;
        $pdo = null;

        try {
            putenv('APP_ENV=test');
            putenv('DB_NAME=fim_dashboard_test');
            SchemaTest::assertTestSafety();

            $pdo = self::createTestConnection();
            Migrator::run($pdo, dirname(__DIR__, 2) . '/database/migrations');
            $schemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'Dataset HTTP integration starts from frozen valid schema',
                $schemaErrors === [],
                implode('; ', $schemaErrors)
            );
            self::clearTestTables($pdo);

            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment()
            );
            $repository = new DatasetRepository($pdo);

            self::testEmptyList($client, $assert);
            [$tinyId, $numericId] = self::testImportsListDetailAndReload(
                $client,
                $repository,
                $assert
            );
            self::testGetValidation($client, $tinyId, $assert);
            self::testMethodAndPostTransportValidation($client, $assert);
            self::testImportFailuresAndWarnings($client, $assert);
            self::testFailureSecurityAndFinalState($client, $numericId, $assert);
            self::testPhpDiscardedMultipartBody($assert);
        } catch (Throwable $throwable) {
            $assert(
                'Dataset HTTP integration test completes without harness error',
                false,
                get_class($throwable) . ': ' . $throwable->getMessage()
            );
        } finally {
            if ($client instanceof HttpTestClient) {
                $client->stop();
            }

            if ($pdo instanceof PDO) {
                try {
                    self::clearTestTables($pdo);
                    $schemaErrors = SchemaVerifier::verify($pdo);
                    $assert(
                        'Dataset HTTP integration leaves frozen schema valid and test tables clean',
                        $schemaErrors === [] && self::tableRowCount($pdo, 'datasets') === 0,
                        implode('; ', $schemaErrors)
                    );
                } catch (Throwable $throwable) {
                    $assert(
                        'Dataset HTTP integration final database cleanup succeeds',
                        false,
                        get_class($throwable) . ': ' . $throwable->getMessage()
                    );
                }
            }

            self::restoreEnvironment('APP_ENV', $originalAppEnv);
            self::restoreEnvironment('DB_NAME', $originalDatabase);
        }

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testEmptyList(HttpTestClient $client, callable $assert): void
    {
        $response = $client->request('GET', self::ENDPOINT);
        $assert(
            'Real HTTP GET dataset list starts empty with UTF-8 JSON',
            $response['status'] === 200
                && $response['json'] === ['datasets' => []]
                && self::headerValues($response, 'content-type') === ['application/json; charset=UTF-8'],
            self::responseSummary($response)
        );
    }

    /**
     * @return array{int, int}
     */
    private static function testImportsListDetailAndReload(
        HttpTestClient $client,
        DatasetRepository $repository,
        callable $assert
    ): array {
        $tinyContent = file_get_contents(dirname(__DIR__) . '/fixtures/tiny.csv');
        if ($tinyContent === false) {
            throw new RuntimeException('Unable to read the frozen tiny.csv fixture.');
        }

        $tinyResponse = self::upload(
            $client,
            ['format' => 'basket_csv'],
            [['field' => 'file', 'filename' => 'tiny.csv', 'content' => $tinyContent, 'content_type' => 'text/csv']]
        );
        $tinyDataset = $tinyResponse['json']['dataset'] ?? null;
        $tinyId = is_array($tinyDataset) && is_int($tinyDataset['id'] ?? null)
            ? $tinyDataset['id']
            : 0;
        $assert(
            'Real HTTP tiny.csv import returns the exact 201 dataset success shape',
            $tinyResponse['status'] === 201
                && array_keys($tinyResponse['json']) === ['dataset', 'warnings', 'total_warnings']
                && is_array($tinyDataset)
                && array_keys($tinyDataset) === self::datasetKeys()
                && $tinyId > 0
                && $tinyDataset['name'] === 'tiny'
                && $tinyDataset['format'] === 'basket_csv'
                && $tinyDataset['source_filename'] === 'tiny.csv'
                && $tinyDataset['sha256'] === '63f312520eda0c5bc90b8ac6cd9c9f61fcf2ed8569b01becbb653ba66319466e'
                && $tinyDataset['byte_size'] === 15
                && $tinyDataset['transaction_count'] === 4
                && $tinyDataset['unique_item_count'] === 3
                && self::isUtcTimestamp($tinyDataset['created_at'])
                && $tinyResponse['json']['warnings'] === []
                && $tinyResponse['json']['total_warnings'] === 0,
            self::responseSummary($tinyResponse)
        );

        $numericContent = "1 01 001 1.0 +1\n1 2 10\n0 -1";
        $numericResponse = self::upload(
            $client,
            ['format' => 'basket_txt', 'name' => ' Numeric Input '],
            [[
                'field' => 'file',
                'filename' => 'numeric.dat',
                'content' => $numericContent,
                'content_type' => 'text/plain',
            ]]
        );
        $numericDataset = $numericResponse['json']['dataset'] ?? null;
        $numericId = is_array($numericDataset) && is_int($numericDataset['id'] ?? null)
            ? $numericDataset['id']
            : 0;
        $assert(
            'Real HTTP numeric basket_txt import preserves exact metadata and integer JSON types',
            $numericResponse['status'] === 201
                && is_array($numericDataset)
                && $numericId > 0
                && $numericId !== $tinyId
                && $numericDataset['name'] === 'Numeric Input'
                && $numericDataset['source_filename'] === 'numeric.dat'
                && $numericDataset['format'] === 'basket_txt'
                && $numericDataset['byte_size'] === strlen($numericContent)
                && $numericDataset['sha256'] === hash('sha256', $numericContent)
                && $numericDataset['transaction_count'] === 3
                && $numericDataset['unique_item_count'] === 9
                && self::isUtcTimestamp($numericDataset['created_at']),
            self::responseSummary($numericResponse)
        );

        $numericTransactions = array_map(
            static fn($transaction): array => $transaction->getItems(),
            $repository->loadTransactions($numericId)
        );
        $numericJson = json_encode($numericTransactions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $numericJsonReload = json_decode($numericJson, true, 512, JSON_THROW_ON_ERROR);
        $allReloadedItems = is_array($numericJsonReload) ? array_merge(...$numericJsonReload) : [];
        $assert(
            'HTTP-imported numeric values reload and JSON-round-trip only as exact strings',
            $numericTransactions === [
                ['+1', '001', '01', '1', '1.0'],
                ['1', '10', '2'],
                ['-1', '0'],
            ]
                && $allReloadedItems !== []
                && array_reduce(
                    $allReloadedItems,
                    static fn(bool $strings, mixed $item): bool => $strings && is_string($item),
                    true
                )
                && in_array('1', $allReloadedItems, true)
                && in_array('01', $allReloadedItems, true)
                && in_array('001', $allReloadedItems, true)
                && in_array('1.0', $allReloadedItems, true)
                && in_array('+1', $allReloadedItems, true)
                && in_array('0', $allReloadedItems, true)
                && in_array('-1', $allReloadedItems, true),
            $numericJson
        );

        $listResponse = $client->request('GET', self::ENDPOINT);
        $listed = $listResponse['json']['datasets'] ?? null;
        $assert(
            'Real HTTP populated dataset list is newest-first with deterministic id tie-break',
            $listResponse['status'] === 200
                && is_array($listed)
                && count($listed) === 2
                && ($listed[0]['id'] ?? null) === $numericId
                && ($listed[1]['id'] ?? null) === $tinyId
                && self::isUtcTimestamp($listed[0]['created_at'] ?? null)
                && self::isUtcTimestamp($listed[1]['created_at'] ?? null),
            self::responseSummary($listResponse)
        );

        $detailResponse = $client->request('GET', self::ENDPOINT . '?id=' . rawurlencode((string)$tinyId));
        $assert(
            'Real HTTP dataset detail returns the imported record without assuming its id',
            $detailResponse['status'] === 200
                && array_keys($detailResponse['json']) === ['dataset']
                && ($detailResponse['json']['dataset']['id'] ?? null) === $tinyId
                && ($detailResponse['json']['dataset']['sha256'] ?? null) === hash('sha256', $tinyContent),
            self::responseSummary($detailResponse)
        );

        return [$tinyId, $numericId];
    }

    private static function testGetValidation(HttpTestClient $client, int $existingId, callable $assert): void
    {
        $missing = $client->request('GET', self::ENDPOINT . '?id=' . PHP_INT_MAX);
        $assert(
            'Real HTTP missing dataset detail maps to safe 404 DATASET_NOT_FOUND',
            self::isError($missing, 404, 'DATASET_NOT_FOUND', true),
            self::responseSummary($missing)
        );

        $invalidTargets = [
            self::ENDPOINT . '?id=',
            self::ENDPOINT . '?id=0',
            self::ENDPOINT . '?id=-1',
            self::ENDPOINT . '?id=1.0',
            self::ENDPOINT . '?id=01',
            self::ENDPOINT . '?id=true',
            self::ENDPOINT . '?id%5B%5D=' . rawurlencode((string)$existingId),
            self::ENDPOINT . '?id=9223372036854775808',
        ];
        foreach ($invalidTargets as $target) {
            $response = $client->request('GET', $target);
            $assert(
                "Real HTTP dataset id variant is rejected without coercion: {$target}",
                self::isError($response, 422, 'INVALID_DATASET_ID', true),
                self::responseSummary($response)
            );
        }

        $unknown = $client->request('GET', self::ENDPOINT . '?unknown=1');
        $assert(
            'Real HTTP GET rejects an unknown query field',
            self::isError($unknown, 400, 'INVALID_REQUEST', true),
            self::responseSummary($unknown)
        );
    }

    private static function testMethodAndPostTransportValidation(HttpTestClient $client, callable $assert): void
    {
        $method = $client->request('DELETE', self::ENDPOINT);
        $assert(
            'Real HTTP unsupported dataset method returns exact Allow header',
            self::isError($method, 405, 'METHOD_NOT_ALLOWED', true)
                && self::headerValues($method, 'allow') === ['GET, POST'],
            self::responseSummary($method)
        );

        $mediaType = $client->request(
            'POST',
            self::ENDPOINT,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            '{"name":"Dữ liệu"}'
        );
        $assert(
            'Real HTTP POST rejects application/json with UTF-8 as unsupported media type',
            self::isError($mediaType, 415, 'UNSUPPORTED_MEDIA_TYPE', true)
                && self::headerValues($mediaType, 'content-type') === ['application/json; charset=UTF-8'],
            self::responseSummary($mediaType)
        );

        $validMultipart = HttpTestClient::multipart(
            ['format' => 'basket_txt'],
            [['field' => 'file', 'filename' => 'query.txt', 'content' => 'A']]
        );
        $queryResponse = $client->request(
            'POST',
            self::ENDPOINT . '?id=1',
            ['Content-Type' => $validMultipart['content_type']],
            $validMultipart['body']
        );
        $assert(
            'Real HTTP dataset POST rejects every query parameter',
            self::isError($queryResponse, 400, 'INVALID_REQUEST', true),
            self::responseSummary($queryResponse)
        );
    }

    private static function testImportFailuresAndWarnings(HttpTestClient $client, callable $assert): void
    {
        $missingFile = self::upload($client, ['format' => 'basket_csv'], []);
        $assert(
            'Real HTTP multipart request with missing file maps to UPLOAD_FAILED',
            self::isError($missingFile, 400, 'UPLOAD_FAILED', true),
            self::responseSummary($missingFile)
        );

        $nestedFile = self::upload(
            $client,
            ['format' => 'basket_csv'],
            [['field' => 'file[]', 'filename' => 'nested.csv', 'content' => 'A,B']]
        );
        $assert(
            'Real HTTP nested uploaded-file shape maps to INVALID_REQUEST',
            self::isError($nestedFile, 400, 'INVALID_REQUEST', true),
            self::responseSummary($nestedFile)
        );

        $unsupportedFormat = self::upload(
            $client,
            ['format' => 'json'],
            [['field' => 'file', 'filename' => 'unsupported.txt', 'content' => 'A']]
        );
        $assert(
            'Real HTTP unsupported declared dataset format maps to 415',
            self::isError($unsupportedFormat, 415, 'UNSUPPORTED_DATASET_FORMAT', false),
            self::responseSummary($unsupportedFormat)
        );

        $profileMismatch = self::upload(
            $client,
            ['format' => 'basket_csv'],
            [['field' => 'file', 'filename' => 'profile.txt', 'content' => 'A,B']]
        );
        $assert(
            'Real HTTP extension/profile mismatch maps to 415',
            self::isError($profileMismatch, 415, 'UNSUPPORTED_DATASET_FORMAT', false),
            self::responseSummary($profileMismatch)
        );

        $oversizedContent = str_repeat('X', self::HTTP_UPLOAD_LIMIT + 1);
        $oversized = self::upload(
            $client,
            ['format' => 'basket_txt'],
            [['field' => 'file', 'filename' => 'oversized.txt', 'content' => $oversizedContent]]
        );
        $assert(
            'Real HTTP actual file bytes over tightened composed limit map to 413',
            strlen($oversizedContent) === self::HTTP_UPLOAD_LIMIT + 1
                && self::isError($oversized, 413, 'UPLOAD_TOO_LARGE', true),
            self::responseSummary($oversized)
        );

        $parseFailure = self::upload(
            $client,
            ['format' => 'basket_csv'],
            [['field' => 'file', 'filename' => 'invalid.csv', 'content' => 'A,,B']]
        );
        $issues = $parseFailure['json']['error']['details']['issues'] ?? null;
        $assert(
            'Real HTTP parser failure maps to safe 422 issue details',
            self::isError($parseFailure, 422, 'DATASET_VALIDATION_FAILED', false)
                && is_array($issues)
                && count($issues) === 1
                && array_keys($issues[0]) === ['code', 'line', 'message']
                && $issues[0]['code'] === 'EMPTY_FIELD'
                && $issues[0]['line'] === 1
                && ($parseFailure['json']['error']['details']['total_issues'] ?? null) === 1,
            self::responseSummary($parseFailure)
        );

        $unknownForm = self::upload(
            $client,
            ['format' => 'basket_txt', 'rogue' => 'field'],
            [['field' => 'file', 'filename' => 'unknown-form.txt', 'content' => 'A']]
        );
        $assert(
            'Real HTTP multipart import rejects an unknown form field',
            self::isError($unknownForm, 400, 'INVALID_REQUEST', true),
            self::responseSummary($unknownForm)
        );

        $unknownFile = self::upload(
            $client,
            ['format' => 'basket_txt'],
            [
                ['field' => 'file', 'filename' => 'known.txt', 'content' => 'A'],
                ['field' => 'rogue', 'filename' => 'rogue.txt', 'content' => 'B'],
            ]
        );
        $assert(
            'Real HTTP multipart import rejects an unknown uploaded-file field',
            self::isError($unknownFile, 400, 'INVALID_REQUEST', true),
            self::responseSummary($unknownFile)
        );

        $warning = self::upload(
            $client,
            ['format' => 'basket_csv', 'name' => ' Cảnh báo '],
            [['field' => 'file', 'filename' => 'warning.csv', 'content' => "A,A,B\n\nC"]]
        );
        $warnings = $warning['json']['warnings'] ?? null;
        $assert(
            'Real HTTP warning import preserves UTF-8 JSON and exact warning objects',
            $warning['status'] === 201
                && ($warning['json']['dataset']['name'] ?? null) === 'Cảnh báo'
                && str_contains($warning['body'], 'Cảnh báo')
                && is_array($warnings)
                && count($warnings) === 2
                && array_keys($warnings[0]) === ['code', 'line', 'message']
                && array_keys($warnings[1]) === ['code', 'line', 'message']
                && $warnings[0]['code'] === 'DUPLICATE_ITEM'
                && $warnings[0]['line'] === 1
                && $warnings[1]['code'] === 'BLANK_RECORD_SKIPPED'
                && $warnings[1]['line'] === 2
                && $warning['json']['total_warnings'] === 2
                && self::isUtcTimestamp($warning['json']['dataset']['created_at'] ?? null),
            self::responseSummary($warning)
        );
    }

    private static function testPhpDiscardedMultipartBody(callable $assert): void
    {
        $client = null;

        try {
            $environment = self::serverEnvironment();
            $environment['UPLOAD_MAX_BYTES'] = '1024';
            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                $environment,
                [
                    'post_max_size' => '128',
                    'upload_max_filesize' => '64K',
                ]
            );
            $multipart = HttpTestClient::multipart(
                ['format' => 'basket_txt'],
                [['field' => 'file', 'filename' => 'php-discard.txt', 'content' => 'A']]
            );
            $response = $client->request(
                'POST',
                self::ENDPOINT,
                ['Content-Type' => $multipart['content_type']],
                $multipart['body']
            );

            $assert(
                'Real HTTP PHP-discarded oversized multipart body maps to 413',
                strlen($multipart['body']) > 128
                    && self::isError($response, 413, 'UPLOAD_TOO_LARGE', true),
                self::responseSummary($response)
            );
        } catch (Throwable $throwable) {
            $assert(
                'Real HTTP PHP-discarded oversized multipart body maps to 413',
                false,
                get_class($throwable) . ': ' . $throwable->getMessage()
            );
        } finally {
            if ($client instanceof HttpTestClient) {
                $client->stop();
            }
        }
    }

    private static function testFailureSecurityAndFinalState(
        HttpTestClient $client,
        int $numericId,
        callable $assert
    ): void {
        $failure = self::upload(
            $client,
            ['format' => 'basket_csv'],
            [['field' => 'file', 'filename' => 'security.csv', 'content' => 'A,,B']]
        );
        $body = $failure['body'];
        $configuredPassword = getenv('DB_PASSWORD');
        $passwordAbsent = !is_string($configuredPassword)
            || $configuredPassword === ''
            || !str_contains($body, $configuredPassword);
        $assert(
            'Real HTTP failure envelope discloses no credentials, DSN, SQL, path, or stack trace',
            self::isError($failure, 422, 'DATASET_VALIDATION_FAILED', false)
                && $passwordAbsent
                && stripos($body, 'password') === false
                && stripos($body, 'mysql:') === false
                && preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE|TRUNCATE|ALTER)\b/i', $body) !== 1
                && preg_match('/[A-Za-z]:\\\\/', $body) !== 1
                && stripos($body, dirname(__DIR__, 2)) === false
                && stripos($body, 'stack trace') === false
                && stripos($body, 'PDOException') === false
                && !str_contains($body, '#0'),
            $body
        );

        $list = $client->request('GET', self::ENDPOINT);
        $datasets = $list['json']['datasets'] ?? null;
        $assert(
            'Failed HTTP imports persist nothing and successful imports remain newest-first',
            $list['status'] === 200
                && is_array($datasets)
                && count($datasets) === 3
                && ($datasets[0]['name'] ?? null) === 'Cảnh báo'
                && ($datasets[1]['id'] ?? null) === $numericId
                && ($datasets[2]['name'] ?? null) === 'tiny',
            self::responseSummary($list)
        );
    }

    /**
     * @param array<string, string> $fields
     * @param list<array{field: string, filename: string, content: string, content_type?: string}> $files
     * @return array{status: int, headers: array<string, list<string>>, body: string, json: mixed, json_object: mixed}
     */
    private static function upload(HttpTestClient $client, array $fields, array $files): array
    {
        $multipart = HttpTestClient::multipart($fields, $files);

        return $client->request(
            'POST',
            self::ENDPOINT,
            ['Content-Type' => $multipart['content_type']],
            $multipart['body']
        );
    }

    /**
     * @param array{status: int, headers: array<string, list<string>>, body: string, json: mixed, json_object: mixed} $response
     */
    private static function isError(array $response, int $status, string $code, bool $emptyDetails): bool
    {
        $error = $response['json']['error'] ?? null;
        $objectError = is_object($response['json_object'])
            && isset($response['json_object']->error)
            && is_object($response['json_object']->error)
            ? $response['json_object']->error
            : null;
        if (
            $response['status'] !== $status
            || !is_array($error)
            || array_keys($response['json']) !== ['error']
            || array_keys($error) !== ['code', 'message', 'details']
            || $error['code'] !== $code
            || !is_string($error['message'])
            || $error['message'] === ''
            || $objectError === null
            || !isset($objectError->details)
            || !is_object($objectError->details)
            || self::headerValues($response, 'content-type') !== ['application/json; charset=UTF-8']
        ) {
            return false;
        }

        return !$emptyDetails || get_object_vars($objectError->details) === [];
    }

    /**
     * @param array{headers: array<string, list<string>>} $response
     * @return list<string>
     */
    private static function headerValues(array $response, string $name): array
    {
        return $response['headers'][strtolower($name)] ?? [];
    }

    private static function isUtcTimestamp(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private static function datasetKeys(): array
    {
        return [
            'id',
            'name',
            'format',
            'source_filename',
            'sha256',
            'byte_size',
            'transaction_count',
            'unique_item_count',
            'created_at',
        ];
    }

    /**
     * @param array{status: int, body: string} $response
     */
    private static function responseSummary(array $response): string
    {
        $body = strlen($response['body']) > 600
            ? substr($response['body'], 0, 600) . '...'
            : $response['body'];

        return "status={$response['status']}, body={$body}";
    }

    /**
     * @return array<string, string>
     */
    private static function serverEnvironment(): array
    {
        return [
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'DB_HOST' => (string)(getenv('DB_HOST') ?: '127.0.0.1'),
            'DB_PORT' => (string)(getenv('DB_PORT') ?: '3306'),
            'DB_NAME' => 'fim_dashboard_test',
            'DB_USER' => (string)(getenv('DB_USER') ?: 'root'),
            'DB_PASSWORD' => (string)(getenv('DB_PASSWORD') ?: ''),
            'UPLOAD_MAX_BYTES' => (string)self::HTTP_UPLOAD_LIMIT,
            'MINING_TIMEOUT_SECONDS' => '30',
            'MINING_MAX_CANDIDATES' => '250000',
            'MINING_MAX_RULES' => '50000',
        ];
    }

    private static function createTestConnection(): PDO
    {
        SchemaTest::assertTestSafety();

        return ConnectionFactory::create([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => 'fim_dashboard_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ]);
    }

    private static function clearTestTables(PDO $pdo): void
    {
        SchemaTest::assertTestSafety();
        $tables = ['experiment_run_levels', 'experiment_runs', 'transaction_items', 'transactions', 'datasets'];
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function tableRowCount(PDO $pdo, string $table): int
    {
        if (!in_array($table, ['datasets'], true)) {
            throw new RuntimeException('Unsafe HTTP integration row-count table.');
        }

        return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    private static function restoreEnvironment(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv("{$name}={$value}");
    }
}
