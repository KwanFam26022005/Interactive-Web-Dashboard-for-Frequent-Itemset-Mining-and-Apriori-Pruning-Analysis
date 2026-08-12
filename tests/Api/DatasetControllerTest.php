<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Dataset\DatasetImportService;
use App\Dataset\DatasetImportLimits;
use App\Dataset\ParserRegistry;
use App\Http\ApiResponse;
use App\Http\DatasetController;
use App\Http\RequestValidator;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use PDO;
use RuntimeException;
use Throwable;

final class DatasetControllerTest
{
    /**
     * @return array{passed: int, failed: int, results: list<string>}
     */
    public static function run(): array
    {
        $passed = 0;
        $failed = 0;
        $results = [];

        $assert = static function (
            string $name,
            bool $condition,
            string $message = ''
        ) use (&$passed, &$failed, &$results): void {
            if ($condition) {
                $passed++;
                $results[] = "[PASS] {$name}";
                return;
            }

            $failed++;
            $results[] = "[FAIL] {$name}: {$message}";
        };

        SchemaTest::assertTestSafety();
        $pdo = null;
        $temporaryFiles = [];

        try {
            $pdo = self::createTestConnection();
            Migrator::run($pdo, dirname(__DIR__, 2) . '/database/migrations');
            self::clearTestTables($pdo);

            $repository = new DatasetRepository($pdo);
            $controller = new DatasetController(
                new RequestValidator(DatasetImportService::MAX_UPLOAD_BYTES),
                $repository,
                new DatasetImportService(new ParserRegistry(), $repository)
            );

            self::testEmptyList($controller, $assert);
            self::testMethodAndQueryValidation($controller, $assert);
            self::testImportsAndSerialization($controller, $temporaryFiles, $assert);
            self::testImportFailures($controller, $temporaryFiles, $assert);
            self::testUploadTooLargeMapping($repository, $temporaryFiles, $assert);
            self::testSafeInternalFailures($assert);

            $schemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'DatasetController tests preserve the frozen schema',
                count($schemaErrors) === 0,
                implode('; ', $schemaErrors)
            );
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
            if ($pdo instanceof PDO) {
                self::clearTestTables($pdo);
            }
        }

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testEmptyList(DatasetController $controller, callable $assert): void
    {
        $response = $controller->handle('GET', null, [], [], []);
        $assert(
            'GET dataset list returns an exact empty success payload',
            $response->getStatus() === 200
                && $response->getHeaders() === []
                && $response->getPayload() === ['datasets' => []],
            self::describe($response)
        );
    }

    private static function testMethodAndQueryValidation(DatasetController $controller, callable $assert): void
    {
        $method = $controller->handle('DELETE', null, [], [], []);
        $assert(
            'Unsupported dataset method maps to 405 with Allow GET, POST',
            self::isError($method, 405, 'METHOD_NOT_ALLOWED')
                && ($method->getHeaders()['Allow'] ?? null) === 'GET, POST',
            self::describe($method)
        );

        foreach (['0', '-1', '1.0', 'abc'] as $invalidId) {
            $response = $controller->handle('GET', null, ['id' => $invalidId], [], []);
            $assert(
                "GET invalid dataset id '{$invalidId}' maps to INVALID_DATASET_ID",
                self::isError($response, 422, 'INVALID_DATASET_ID'),
                self::describe($response)
            );
        }

        $arrayId = $controller->handle('GET', null, ['id' => ['1']], [], []);
        $assert(
            'GET array dataset id is rejected without coercion',
            self::isError($arrayId, 422, 'INVALID_DATASET_ID'),
            self::describe($arrayId)
        );

        $unknown = $controller->handle('GET', null, ['other' => '1'], [], []);
        $assert(
            'GET unknown query field maps to INVALID_REQUEST',
            self::isError($unknown, 400, 'INVALID_REQUEST'),
            self::describe($unknown)
        );

        $postQuery = $controller->handle(
            'POST',
            'multipart/form-data; boundary=test',
            ['id' => '1'],
            [],
            []
        );
        $assert(
            'POST rejects every query parameter',
            self::isError($postQuery, 400, 'INVALID_REQUEST'),
            self::describe($postQuery)
        );
    }

    /**
     * @param list<string> $temporaryFiles
     */
    private static function testImportsAndSerialization(
        DatasetController $controller,
        array &$temporaryFiles,
        callable $assert
    ): void {
        $first = self::import(
            $controller,
            "A,B,C\nA,B\nA,C\nA",
            'C:\\client\\tiny.csv',
            'basket_csv',
            'tiny-oracle',
            $temporaryFiles
        );
        $firstPayload = $first->getPayload();
        $firstDataset = $firstPayload['dataset'] ?? null;

        $assert(
            'POST tiny import returns 201 with exact response top-level keys',
            $first->getStatus() === 201
                && array_keys($firstPayload) === ['dataset', 'warnings', 'total_warnings'],
            self::describe($first)
        );
        $assert(
            'Tiny import dataset serialization has the exact frozen shape and values',
            is_array($firstDataset)
                && array_keys($firstDataset) === self::datasetKeys()
                && ($firstDataset['name'] ?? null) === 'tiny-oracle'
                && ($firstDataset['format'] ?? null) === 'basket_csv'
                && ($firstDataset['source_filename'] ?? null) === 'tiny.csv'
                && ($firstDataset['sha256'] ?? null) === hash('sha256', "A,B,C\nA,B\nA,C\nA")
                && ($firstDataset['byte_size'] ?? null) === strlen("A,B,C\nA,B\nA,C\nA")
                && ($firstDataset['transaction_count'] ?? null) === 4
                && ($firstDataset['unique_item_count'] ?? null) === 3,
            self::describe($first)
        );
        $assert(
            'Dataset created_at is emitted in the frozen UTC API shape',
            is_array($firstDataset)
                && is_string($firstDataset['created_at'] ?? null)
                && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $firstDataset['created_at']) === 1,
            self::describe($first)
        );

        $warning = self::import(
            $controller,
            "A,A,B\n\nC,D",
            '/browser/warnings.csv',
            'basket_csv',
            null,
            $temporaryFiles
        );
        $warningPayload = $warning->getPayload();
        $warnings = $warningPayload['warnings'] ?? null;
        $assert(
            'Successful import serializes warning code, line, and message exactly',
            $warning->getStatus() === 201
                && is_array($warnings)
                && count($warnings) === 2
                && array_keys($warnings[0]) === ['code', 'line', 'message']
                && ($warnings[0]['code'] ?? null) === 'DUPLICATE_ITEM'
                && ($warnings[0]['line'] ?? null) === 1
                && ($warnings[1]['code'] ?? null) === 'BLANK_RECORD_SKIPPED'
                && ($warnings[1]['line'] ?? null) === 2
                && ($warningPayload['total_warnings'] ?? null) === 2,
            self::describe($warning)
        );

        $list = $controller->handle('GET', null, [], [], []);
        $listPayload = $list->getPayload();
        $datasets = $listPayload['datasets'] ?? null;
        $assert(
            'GET populated list is newest-first with deterministic ID tie-breaker',
            $list->getStatus() === 200
                && is_array($datasets)
                && count($datasets) === 2
                && ($datasets[0]['id'] ?? 0) > ($datasets[1]['id'] ?? 0)
                && ($datasets[0]['name'] ?? null) === 'warnings'
                && ($datasets[1]['name'] ?? null) === 'tiny-oracle',
            self::describe($list)
        );

        $firstId = is_array($firstDataset) ? ($firstDataset['id'] ?? null) : null;
        $detail = $controller->handle('GET', null, ['id' => (string)$firstId], [], []);
        $assert(
            'GET existing dataset detail returns the same frozen dataset shape',
            $detail->getStatus() === 200
                && ($detail->getPayload()['dataset'] ?? null) === $firstDataset,
            self::describe($detail)
        );

        $missing = $controller->handle('GET', null, ['id' => '999999999'], [], []);
        $assert(
            'GET missing dataset detail maps to DATASET_NOT_FOUND',
            self::isError($missing, 404, 'DATASET_NOT_FOUND'),
            self::describe($missing)
        );
    }

    /**
     * @param list<string> $temporaryFiles
     */
    private static function testImportFailures(
        DatasetController $controller,
        array &$temporaryFiles,
        callable $assert
    ): void {
        $unsupportedMedia = $controller->handle('POST', 'application/json', [], [], []);
        $assert(
            'POST unsupported content type uses the safe API exception envelope',
            self::isError($unsupportedMedia, 415, 'UNSUPPORTED_MEDIA_TYPE')
                && self::hasSafeErrorShape($unsupportedMedia),
            self::describe($unsupportedMedia)
        );

        $missingFile = $controller->handle(
            'POST',
            'multipart/form-data; boundary=test',
            [],
            ['format' => 'basket_csv'],
            []
        );
        $assert(
            'POST missing file maps to UPLOAD_FAILED',
            self::isError($missingFile, 400, 'UPLOAD_FAILED'),
            self::describe($missingFile)
        );

        $unreadablePath = 'C:\\private\\upload-secret.tmp';
        $unreadable = $controller->handle(
            'POST',
            'multipart/form-data; boundary=test',
            [],
            ['format' => 'basket_csv'],
            [
                'file' => [
                    'name' => 'tiny.csv',
                    'tmp_name' => $unreadablePath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => 4,
                ],
            ]
        );
        $unreadableJson = json_encode($unreadable->getPayload());
        $assert(
            'Unreadable validated upload maps to UPLOAD_FAILED without tmp-path leakage',
            self::isError($unreadable, 400, 'UPLOAD_FAILED')
                && is_string($unreadableJson)
                && !str_contains($unreadableJson, $unreadablePath)
                && !str_contains($unreadableJson, 'private'),
            self::describe($unreadable)
        );

        $unsupported = self::import(
            $controller,
            'A,B',
            'tiny.csv',
            'json',
            null,
            $temporaryFiles
        );
        $assert(
            'Unsupported declared format maps first issue to UNSUPPORTED_DATASET_FORMAT',
            self::isError($unsupported, 415, 'UNSUPPORTED_DATASET_FORMAT')
                && self::hasIssueDetails($unsupported, 'UNSUPPORTED_FORMAT', 1),
            self::describe($unsupported)
        );

        $profileMismatch = self::import(
            $controller,
            'A,B',
            'tiny.txt',
            'basket_csv',
            null,
            $temporaryFiles
        );
        $assert(
            'Extension profile mismatch maps to UNSUPPORTED_DATASET_FORMAT',
            self::isError($profileMismatch, 415, 'UNSUPPORTED_DATASET_FORMAT')
                && self::hasIssueDetails($profileMismatch, 'PROFILE_MISMATCH', 1),
            self::describe($profileMismatch)
        );

        $parseFailure = self::import(
            $controller,
            'A,,C',
            'invalid.csv',
            'basket_csv',
            null,
            $temporaryFiles
        );
        $assert(
            'Parse failure maps to DATASET_VALIDATION_FAILED with safe issue details',
            self::isError($parseFailure, 422, 'DATASET_VALIDATION_FAILED')
                && self::hasIssueDetails($parseFailure, 'EMPTY_FIELD', 1),
            self::describe($parseFailure)
        );

        $assert(
            'Dataset validation failure envelope exposes no temporary upload path',
            !str_contains((string)json_encode($parseFailure->getPayload()), 'fim-dashboard-controller-'),
            self::describe($parseFailure)
        );
    }

    private static function testSafeInternalFailures(callable $assert): void
    {
        $validController = self::createSqliteControllerWithTimestamp('2026-08-12 00:00:00');
        $validUtc = $validController->handle('GET', null, [], [], []);
        $datasets = $validUtc->getPayload()['datasets'] ?? [];
        $hasExpectedUtc = false;
        foreach ($datasets as $dataset) {
            if (($dataset['created_at'] ?? null) === '2026-08-12T00:00:00Z') {
                $hasExpectedUtc = true;
            }
        }
        $assert(
            'Frozen UTC database timestamp converts deterministically to API form',
            $validUtc->getStatus() === 200 && $hasExpectedUtc,
            self::describe($validUtc)
        );

        $invalidController = self::createSqliteControllerWithTimestamp('not-a-database-timestamp');
        $invalidUtc = $invalidController->handle('GET', null, [], [], []);
        $invalidPayload = $invalidUtc->getPayload();
        $invalidJson = json_encode($invalidPayload);

        $assert(
            'Invalid internal timestamp fails closed as generic INTERNAL_ERROR',
            self::isError($invalidUtc, 500, 'INTERNAL_ERROR')
                && self::hasSafeErrorShape($invalidUtc)
                && is_string($invalidJson)
                && !str_contains($invalidJson, 'timestamp')
                && !str_contains($invalidJson, 'RuntimeException')
                && !str_contains($invalidJson, 'D:\\Projects'),
            self::describe($invalidUtc)
        );
    }

    /**
     * @param list<string> $temporaryFiles
     */
    private static function testUploadTooLargeMapping(
        DatasetRepository $repository,
        array &$temporaryFiles,
        callable $assert
    ): void {
        $controller = new DatasetController(
            new RequestValidator(DatasetImportService::MAX_UPLOAD_BYTES),
            $repository,
            new DatasetImportService(
                new ParserRegistry(),
                $repository,
                new DatasetImportLimits(3)
            )
        );

        $response = self::import(
            $controller,
            'A,B,C',
            'too-large.csv',
            'basket_csv',
            null,
            $temporaryFiles
        );

        $assert(
            'Actual-content upload limit failure maps to UPLOAD_TOO_LARGE',
            self::isError($response, 413, 'UPLOAD_TOO_LARGE')
                && self::hasIssueDetails($response, 'UPLOAD_TOO_LARGE', 1),
            self::describe($response)
        );
    }

    /**
     * @param list<string> $temporaryFiles
     */
    private static function import(
        DatasetController $controller,
        string $content,
        string $sourceFilename,
        string $format,
        ?string $name,
        array &$temporaryFiles
    ): ApiResponse {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'fim-dashboard-controller-');
        if ($temporaryPath === false || file_put_contents($temporaryPath, $content) === false) {
            throw new RuntimeException('Unable to prepare the controller test upload.');
        }
        $temporaryFiles[] = $temporaryPath;

        $post = ['format' => $format];
        if ($name !== null) {
            $post['name'] = $name;
        }

        return $controller->handle(
            'POST',
            'multipart/form-data; boundary=test',
            [],
            $post,
            [
                'file' => [
                    'name' => $sourceFilename,
                    'tmp_name' => $temporaryPath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => strlen($content),
                ],
            ]
        );
    }

    private static function isError(ApiResponse $response, int $status, string $code): bool
    {
        $error = $response->getPayload()['error'] ?? null;

        return $response->getStatus() === $status
            && is_array($error)
            && ($error['code'] ?? null) === $code;
    }

    private static function hasSafeErrorShape(ApiResponse $response): bool
    {
        $payload = $response->getPayload();
        $error = $payload['error'] ?? null;

        return array_keys($payload) === ['error']
            && is_array($error)
            && array_keys($error) === ['code', 'message', 'details']
            && is_string($error['code'])
            && is_string($error['message'])
            && is_object($error['details']);
    }

    private static function hasIssueDetails(ApiResponse $response, string $issueCode, int $totalIssues): bool
    {
        $details = $response->getPayload()['error']['details'] ?? null;
        if (!is_object($details)) {
            return false;
        }

        $issues = $details->issues ?? null;

        return is_array($issues)
            && ($issues[0]['code'] ?? null) === $issueCode
            && ($details->total_issues ?? null) === $totalIssues;
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

    private static function describe(ApiResponse $response): string
    {
        try {
            return 'status=' . $response->getStatus() . ' payload=' . json_encode(
                $response->getPayload(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (Throwable) {
            return 'response could not be encoded';
        }
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

    private static function createSqliteControllerWithTimestamp(string $createdAt): DatasetController
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(
            'CREATE TABLE datasets ('
            . 'id INTEGER PRIMARY KEY, name TEXT, format TEXT, source_filename TEXT, '
            . 'sha256 TEXT, byte_size INTEGER, transaction_count INTEGER, '
            . 'unique_item_count INTEGER, created_at TEXT)'
        );

        $statement = $pdo->prepare(
            'INSERT INTO datasets '
            . '(id, name, format, source_filename, sha256, byte_size, transaction_count, unique_item_count, created_at) '
            . 'VALUES (1, :name, :format, :source_filename, :sha256, 3, 1, 1, :created_at)'
        );
        $statement->execute([
            ':name' => 'internal fixture',
            ':format' => 'basket_csv',
            ':source_filename' => 'internal.csv',
            ':sha256' => str_repeat('a', 64),
            ':created_at' => $createdAt,
        ]);

        $repository = new DatasetRepository($pdo);

        return new DatasetController(
            new RequestValidator(DatasetImportService::MAX_UPLOAD_BYTES),
            $repository,
            new DatasetImportService(new ParserRegistry(), $repository)
        );
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
}
