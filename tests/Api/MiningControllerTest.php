<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Dataset\DatasetImportService;
use App\Dataset\ParserRegistry;
use App\Http\ApiResponse;
use App\Http\JsonResponder;
use App\Http\MiningController;
use App\Http\MiningResponseAssembler;
use App\Http\RequestValidator;
use App\Mining\AprioriEngine;
use App\Mining\AssociationRuleGenerator;
use App\Mining\HeatmapBuilder;
use App\Persistence\ConnectionFactory;
use App\Persistence\DatasetRepository;
use App\Persistence\ExperimentRunRepository;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use InvalidArgumentException;
use PDO;
use Throwable;

final class MiningControllerTest
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

        try {
            $pdo = self::createTestConnection();
            Migrator::run($pdo, dirname(__DIR__, 2) . '/database/migrations');
            self::clearTestTables($pdo);

            $schemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'MiningController tests start from the frozen valid schema',
                count($schemaErrors) === 0,
                implode('; ', $schemaErrors)
            );

            $datasets = new DatasetRepository($pdo);
            $tiny = self::importTiny($datasets);
            $controller = self::createController($pdo, $datasets);

            self::testConstructorGuardrails($pdo, $datasets, $assert);
            self::testRequestFailuresDoNotPersist($controller, $pdo, $tiny->getId(), $assert);
            self::testTinySuccessAndPersistence($controller, $pdo, $tiny->getId(), $assert);
            self::testTopNDoesNotChangePersistence($controller, $pdo, $tiny->getId(), $assert);
            self::testCandidateLimitFailure($pdo, $datasets, $tiny->getId(), $assert);
            self::testRuleLimitFailure($pdo, $datasets, $tiny->getId(), $assert);
            self::testPrePersistenceEncodingFailure($assert);

            $postSchemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'MiningController tests leave the frozen schema valid',
                count($postSchemaErrors) === 0,
                implode('; ', $postSchemaErrors)
            );
        } finally {
            if ($pdo instanceof PDO) {
                self::clearTestTables($pdo);
            }
        }

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testConstructorGuardrails(
        PDO $pdo,
        DatasetRepository $datasets,
        callable $assert
    ): void {
        $cases = [
            'candidateLimit=0' => [0, 30, 50_000],
            'candidateLimit above frozen maximum' => [250_001, 30, 50_000],
            'deadlineSeconds=0' => [250_000, 0, 50_000],
            'deadlineSeconds above frozen maximum' => [250_000, 31, 50_000],
            'ruleLimit=0' => [250_000, 30, 0],
            'ruleLimit above frozen maximum' => [250_000, 30, 50_001],
        ];

        foreach ($cases as $name => [$candidateLimit, $deadlineSeconds, $ruleLimit]) {
            $caught = false;
            try {
                self::createController(
                    $pdo,
                    $datasets,
                    $candidateLimit,
                    $deadlineSeconds,
                    $ruleLimit
                );
            } catch (InvalidArgumentException) {
                $caught = true;
            }

            $assert("MiningController refuses {$name}", $caught);
        }
    }

    private static function testRequestFailuresDoNotPersist(
        MiningController $controller,
        PDO $pdo,
        int $datasetId,
        callable $assert
    ): void {
        self::clearRuns($pdo);

        $method = $controller->handle('GET', null, [], '');
        $assert(
            'Unsupported mining method maps to 405 with Allow POST',
            self::isError($method, 405, 'METHOD_NOT_ALLOWED')
                && ($method->getHeaders()['Allow'] ?? null) === 'POST',
            self::describe($method)
        );

        $query = $controller->handle(
            'POST',
            'application/json',
            ['debug' => '1'],
            self::validBody($datasetId)
        );
        $assert(
            'Mining query parameters map to INVALID_REQUEST',
            self::isError($query, 400, 'INVALID_REQUEST'),
            self::describe($query)
        );

        $contentType = $controller->handle(
            'POST',
            'text/plain',
            [],
            self::validBody($datasetId)
        );
        $assert(
            'Mining unsupported content type maps to UNSUPPORTED_MEDIA_TYPE',
            self::isError($contentType, 415, 'UNSUPPORTED_MEDIA_TYPE'),
            self::describe($contentType)
        );

        $malformed = $controller->handle('POST', 'application/json', [], '{');
        $assert(
            'Malformed mining JSON maps to INVALID_JSON',
            self::isError($malformed, 400, 'INVALID_JSON'),
            self::describe($malformed)
        );

        $invalid = $controller->handle(
            'POST',
            'application/json',
            [],
            '{"dataset_id":0,"min_support":0.5,"min_confidence":0.75}'
        );
        $assert(
            'Invalid mining dataset id maps to INVALID_DATASET_ID',
            self::isError($invalid, 422, 'INVALID_DATASET_ID'),
            self::describe($invalid)
        );

        $missing = $controller->handle(
            'POST',
            'application/json',
            [],
            self::validBody(999_999_999)
        );
        $assert(
            'Missing mining dataset maps to DATASET_NOT_FOUND',
            self::isError($missing, 404, 'DATASET_NOT_FOUND'),
            self::describe($missing)
        );

        $assert(
            'Method, query, content, validation, and missing-dataset failures persist no run',
            self::runCount($pdo) === 0,
            'experiment_runs count=' . self::runCount($pdo)
        );
        $assert(
            'Mining ApiException response uses the exact safe error envelope',
            self::hasSafeErrorShape($missing),
            self::describe($missing)
        );
    }

    private static function testTinySuccessAndPersistence(
        MiningController $controller,
        PDO $pdo,
        int $datasetId,
        callable $assert
    ): void {
        self::clearRuns($pdo);

        $response = $controller->handle(
            'POST',
            'application/json; charset=UTF-8',
            [],
            self::validBody($datasetId)
        );
        $payload = $response->getPayload();
        $runId = $payload['run_id'] ?? null;

        $assert(
            'Tiny mining succeeds with the exact top-level response structure',
            $response->getStatus() === 200
                && array_keys($payload) === [
                    'run_id',
                    'dataset',
                    'parameters',
                    'summary',
                    'levels',
                    'itemsets',
                    'rules',
                    'heatmap',
                    'result_limits',
                ]
                && is_int($runId)
                && $runId > 0,
            self::describe($response)
        );

        $summary = $payload['summary'] ?? [];
        $assert(
            'Tiny mining response retains complete deterministic totals',
            ($summary['frequent_itemsets'] ?? null) === 5
                && ($summary['rules_count'] ?? null) === 2
                && ($summary['max_k'] ?? null) === 2
                && ($summary['candidates_generated'] ?? null) === 7
                && ($summary['candidates_pruned'] ?? null) === 1
                && ($summary['candidates_evaluated'] ?? null) === 6
                && ($summary['pruning_ratio'] ?? null) === 0.142857,
            self::describe($response)
        );

        $stored = self::loadRun($pdo, (int)$runId);
        $assert(
            'Tiny mining persists one run with exact thresholds and complete totals',
            self::runCount($pdo) === 1
                && is_array($stored)
                && (int)$stored['id'] === $runId
                && (int)$stored['dataset_id'] === $datasetId
                && (string)$stored['min_support'] === '0.500000'
                && (string)$stored['min_confidence'] === '0.750000'
                && (int)$stored['candidates_generated'] === 7
                && (int)$stored['candidates_pruned'] === 1
                && (int)$stored['candidates_evaluated'] === 6
                && (int)$stored['frequent_itemsets'] === 5
                && (int)$stored['rules_count'] === 2
                && (int)$stored['max_k'] === 2,
            self::rowDescription($stored)
        );

        $levels = self::loadLevels($pdo, (int)$runId);
        $expectedLevels = [
            ['k' => 1, 'source' => 'singleton_scan', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 3],
            ['k' => 2, 'source' => 'join_prune', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 2],
            ['k' => 3, 'source' => 'join_prune', 'generated' => 1, 'pruned' => 1, 'evaluated' => 0, 'frequent' => 0],
        ];
        $assert(
            'Tiny mining persists every exact reported Apriori level',
            $levels === $expectedLevels && ($payload['levels'] ?? null) === self::responseLevels($expectedLevels),
            json_encode($levels) ?: 'levels not encodable'
        );

        $assert(
            'Tiny mining response run_id equals the persisted generated run ID',
            is_array($stored) && (int)$stored['id'] === $runId,
            self::describe($response)
        );
    }

    private static function testTopNDoesNotChangePersistence(
        MiningController $controller,
        PDO $pdo,
        int $datasetId,
        callable $assert
    ): void {
        self::clearRuns($pdo);

        $response = $controller->handle(
            'POST',
            'application/json',
            [],
            '{"dataset_id":' . $datasetId
                . ',"min_support":0.5,"min_confidence":0.75,"top_n":1}'
        );
        $payload = $response->getPayload();
        $runId = $payload['run_id'] ?? 0;
        $stored = self::loadRun($pdo, (int)$runId);

        $assert(
            'top_n=1 limits display arrays without changing complete response totals',
            $response->getStatus() === 200
                && count($payload['itemsets'] ?? []) === 1
                && count($payload['rules'] ?? []) === 1
                && ($payload['summary']['frequent_itemsets'] ?? null) === 5
                && ($payload['summary']['rules_count'] ?? null) === 2
                && ($payload['result_limits']['itemsets_truncated'] ?? null) === true
                && ($payload['result_limits']['rules_truncated'] ?? null) === true,
            self::describe($response)
        );
        $assert(
            'top_n=1 persists complete totals and all levels',
            self::runCount($pdo) === 1
                && is_array($stored)
                && (int)$stored['frequent_itemsets'] === 5
                && (int)$stored['rules_count'] === 2
                && count(self::loadLevels($pdo, (int)$runId)) === 3,
            self::rowDescription($stored)
        );
    }

    private static function testCandidateLimitFailure(
        PDO $pdo,
        DatasetRepository $datasets,
        int $datasetId,
        callable $assert
    ): void {
        self::clearRuns($pdo);
        $controller = self::createController($pdo, $datasets, 1, 30, 50_000);
        $response = $controller->handle(
            'POST',
            'application/json',
            [],
            self::validBody($datasetId)
        );

        $assert(
            'Candidate limit=1 maps real computation failure to MINING_LIMIT_EXCEEDED',
            self::isError($response, 503, 'MINING_LIMIT_EXCEEDED')
                && self::hasSafeErrorShape($response),
            self::describe($response)
        );
        $assert(
            'Candidate-limit failure persists no completed run or level',
            self::runCount($pdo) === 0 && self::levelCount($pdo) === 0
        );
    }

    private static function testRuleLimitFailure(
        PDO $pdo,
        DatasetRepository $datasets,
        int $datasetId,
        callable $assert
    ): void {
        self::clearRuns($pdo);
        $controller = self::createController($pdo, $datasets, 250_000, 30, 1);
        $response = $controller->handle(
            'POST',
            'application/json',
            [],
            '{"dataset_id":' . $datasetId
                . ',"min_support":0.5,"min_confidence":0,"top_n":20}'
        );

        $assert(
            'Rule limit=1 maps real rule enumeration failure to MINING_LIMIT_EXCEEDED',
            self::isError($response, 503, 'MINING_LIMIT_EXCEEDED')
                && self::hasSafeErrorShape($response),
            self::describe($response)
        );
        $assert(
            'Rule-limit failure persists no completed run or level',
            self::runCount($pdo) === 0 && self::levelCount($pdo) === 0
        );
    }

    private static function testPrePersistenceEncodingFailure(callable $assert): void
    {
        [$pdo, $controller] = self::createInvalidUtf8SqliteController();
        $response = $controller->handle(
            'POST',
            'application/json',
            [],
            '{"dataset_id":1,"min_support":1,"min_confidence":0,"top_n":20}'
        );
        $encoded = json_encode($response->getPayload());

        $assert(
            'Non-serializable pre-persistence payload fails closed as generic INTERNAL_ERROR',
            self::isError($response, 500, 'INTERNAL_ERROR')
                && self::hasSafeErrorShape($response)
                && is_string($encoded)
                && !str_contains($encoded, 'JsonException')
                && !str_contains($encoded, 'Malformed UTF-8')
                && !str_contains($encoded, 'D:\\Projects'),
            self::describe($response)
        );
        $assert(
            'Non-serializable pre-persistence payload creates no completed run',
            (int)$pdo->query('SELECT COUNT(*) FROM experiment_runs')->fetchColumn() === 0
        );
    }

    private static function createController(
        PDO $pdo,
        DatasetRepository $datasets,
        int $candidateLimit = 250_000,
        int $deadlineSeconds = 30,
        int $ruleLimit = 50_000
    ): MiningController {
        return new MiningController(
            new RequestValidator(DatasetImportService::MAX_UPLOAD_BYTES),
            $datasets,
            new AprioriEngine(),
            new AssociationRuleGenerator(),
            new HeatmapBuilder(),
            new ExperimentRunRepository($pdo),
            new MiningResponseAssembler(),
            new JsonResponder(),
            $candidateLimit,
            $deadlineSeconds,
            $ruleLimit
        );
    }

    private static function importTiny(DatasetRepository $datasets): \App\Persistence\DatasetRecord
    {
        $path = dirname(__DIR__) . '/fixtures/tiny.csv';
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Unable to read the tiny fixture.');
        }

        $service = new DatasetImportService(new ParserRegistry(), $datasets);

        return $service->import($content, 'tiny.csv', 'basket_csv', 'tiny-oracle')->getDataset();
    }

    private static function validBody(int $datasetId): string
    {
        return '{"dataset_id":' . $datasetId
            . ',"min_support":0.5,"min_confidence":0.75,"top_n":20}';
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

    /**
     * @param array<string, mixed>|false $row
     */
    private static function rowDescription(array|false $row): string
    {
        return $row === false ? 'row not found' : ((string)json_encode($row));
    }

    /**
     * @return array<string, mixed>|false
     */
    private static function loadRun(PDO $pdo, int $runId): array|false
    {
        $statement = $pdo->prepare(
            'SELECT id, dataset_id, min_support, min_confidence, candidates_generated, '
            . 'candidates_pruned, candidates_evaluated, frequent_itemsets, rules_count, max_k '
            . 'FROM experiment_runs WHERE id = :id'
        );
        $statement->execute(['id' => $runId]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{k: int, source: string, generated: int, pruned: int, evaluated: int, frequent: int}>
     */
    private static function loadLevels(PDO $pdo, int $runId): array
    {
        $statement = $pdo->prepare(
            'SELECT `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent` '
            . 'FROM `experiment_run_levels` WHERE `run_id` = :id ORDER BY `k` ASC'
        );
        $statement->execute(['id' => $runId]);

        return array_map(
            static fn(array $level): array => [
                'k' => (int)$level['k'],
                'source' => (string)$level['source'],
                'generated' => (int)$level['generated'],
                'pruned' => (int)$level['pruned'],
                'evaluated' => (int)$level['evaluated'],
                'frequent' => (int)$level['frequent'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param list<array{k: int, source: string, generated: int, pruned: int, evaluated: int, frequent: int}> $levels
     * @return list<array{k: int, source: string, generated: int, pruned: int, evaluated: int, frequent: int, pruning_ratio: float|null}>
     */
    private static function responseLevels(array $levels): array
    {
        return array_map(
            static fn(array $level): array => $level + [
                'pruning_ratio' => $level['generated'] > 0
                    ? round($level['pruned'] / $level['generated'], 6)
                    : null,
            ],
            $levels
        );
    }

    private static function runCount(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM experiment_runs')->fetchColumn();
    }

    private static function levelCount(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM experiment_run_levels')->fetchColumn();
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

    /**
     * @return array{PDO, MiningController}
     */
    private static function createInvalidUtf8SqliteController(): array
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
        $pdo->exec(
            'CREATE TABLE transactions ('
            . 'id INTEGER PRIMARY KEY, dataset_id INTEGER, transaction_key TEXT, ordinal INTEGER)'
        );
        $pdo->exec('CREATE TABLE transaction_items (transaction_id INTEGER, item_key TEXT)');
        $pdo->exec(
            'CREATE TABLE experiment_runs ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, dataset_id INTEGER, min_support TEXT, '
            . 'min_confidence TEXT, runtime_ms TEXT, rule_generation_runtime_ms TEXT, '
            . 'candidates_generated INTEGER, candidates_pruned INTEGER, candidates_evaluated INTEGER, '
            . 'frequent_itemsets INTEGER, rules_count INTEGER, max_k INTEGER)'
        );
        $pdo->exec(
            'CREATE TABLE experiment_run_levels ('
            . 'run_id INTEGER, k INTEGER, source TEXT, generated INTEGER, pruned INTEGER, '
            . 'evaluated INTEGER, frequent INTEGER)'
        );

        $dataset = $pdo->prepare(
            'INSERT INTO datasets '
            . '(id, name, format, source_filename, sha256, byte_size, transaction_count, unique_item_count, created_at) '
            . 'VALUES (1, :name, :format, :source_filename, :sha256, 1, 1, 1, :created_at)'
        );
        $dataset->execute([
            ':name' => "invalid-\xFF-name",
            ':format' => 'basket_csv',
            ':source_filename' => 'invalid.csv',
            ':sha256' => str_repeat('a', 64),
            ':created_at' => '2026-08-12 00:00:00',
        ]);
        $pdo->exec("INSERT INTO transactions (id, dataset_id, transaction_key, ordinal) VALUES (1, 1, '1', 1)");
        $pdo->exec("INSERT INTO transaction_items (transaction_id, item_key) VALUES (1, 'A')");

        $datasets = new DatasetRepository($pdo);

        return [$pdo, self::createController($pdo, $datasets)];
    }

    private static function clearRuns(PDO $pdo): void
    {
        SchemaTest::assertTestSafety();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $pdo->exec('TRUNCATE TABLE experiment_run_levels');
            $pdo->exec('TRUNCATE TABLE experiment_runs');
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
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
