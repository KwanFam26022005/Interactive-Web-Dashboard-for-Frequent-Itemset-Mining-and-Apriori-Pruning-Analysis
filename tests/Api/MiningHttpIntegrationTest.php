<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Persistence\ConnectionFactory;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Unit\SchemaTest;
use PDO;
use RuntimeException;
use Throwable;

final class MiningHttpIntegrationTest
{
    private const DATASET_ENDPOINT = '/api/datasets.php';
    private const MINING_ENDPOINT = '/api/mining.php';

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
            self::clearTestTables($pdo);
            $schemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'Mining HTTP integration starts from the frozen valid test schema',
                $schemaErrors === [],
                implode('; ', $schemaErrors)
            );

            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment()
            );

            $tinyId = self::importDataset(
                $client,
                'tiny.csv',
                self::tinyBytes(),
                'basket_csv',
                'Tiny HTTP oracle'
            );
            $assert('Tiny fixture is imported through the real Dataset API', $tinyId > 0);

            self::testTransportAndShapeValidation($client, $pdo, $tinyId, $assert);
            self::testTinyOracleAndPersistence($client, $pdo, $tinyId, $assert);
            self::testTopNOracleAndPersistence($client, $pdo, $tinyId, $assert);
            self::testThresholdBoundaries($client, $pdo, $tinyId, $assert);
            self::testNumericOracle($client, $assert);
            self::testGuardrails($pdo, $tinyId, $assert);
            self::testControlledInternalFailure($pdo, $tinyId, $assert);

            $schemaErrors = SchemaVerifier::verify($pdo);
            $assert(
                'Mining HTTP scenarios retain the frozen schema',
                $schemaErrors === [],
                implode('; ', $schemaErrors)
            );
        } catch (Throwable $throwable) {
            $assert(
                'Mining HTTP integration test completes without harness error',
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
                        'Mining HTTP integration leaves the test schema valid and every table clean',
                        $schemaErrors === [] && self::allTableRows($pdo) === 0,
                        implode('; ', $schemaErrors)
                    );
                } catch (Throwable $throwable) {
                    $assert(
                        'Mining HTTP integration final database cleanup succeeds',
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

    private static function testTransportAndShapeValidation(
        HttpTestClient $client,
        PDO $pdo,
        int $tinyId,
        callable $assert
    ): void {
        self::clearRuns($pdo);
        $validBody = self::miningBody($tinyId, '0.5', '0.75', '20');

        $method = $client->request('GET', self::MINING_ENDPOINT);
        $assert(
            'Real mining endpoint rejects GET with 405 and exact Allow POST',
            self::isError($method, 405, 'METHOD_NOT_ALLOWED')
                && self::headerValues($method, 'allow') === ['POST'],
            self::responseSummary($method)
        );

        $query = self::jsonRequest($client, $validBody, self::MINING_ENDPOINT . '?debug=1');
        $assert(
            'Real mining endpoint rejects query parameters',
            self::isError($query, 400, 'INVALID_REQUEST'),
            self::responseSummary($query)
        );

        $noContentType = $client->request('POST', self::MINING_ENDPOINT, [], $validBody);
        $wrongContentType = $client->request(
            'POST',
            self::MINING_ENDPOINT,
            ['Content-Type' => 'text/plain'],
            $validBody
        );
        $assert(
            'Real mining endpoint rejects missing and wrong content types',
            self::isError($noContentType, 415, 'UNSUPPORTED_MEDIA_TYPE')
                && self::isError($wrongContentType, 415, 'UNSUPPORTED_MEDIA_TYPE'),
            self::responseSummary($wrongContentType)
        );

        foreach (['{', 'NaN', 'Infinity', '-Infinity'] as $body) {
            $response = self::jsonRequest($client, $body);
            $assert(
                "Real mining endpoint maps malformed token {$body} to INVALID_JSON",
                self::isError($response, 400, 'INVALID_JSON'),
                self::responseSummary($response)
            );
        }

        foreach (['[]', '"scalar"', '1', 'true', 'false', 'null'] as $body) {
            $response = self::jsonRequest($client, $body);
            $assert(
                "Real mining endpoint rejects non-object JSON root {$body}",
                self::isError($response, 400, 'INVALID_REQUEST'),
                self::responseSummary($response)
            );
        }

        $shapeCases = [
            '{}' => 'missing required fields',
            '{"dataset_id":' . $tinyId . ',"min_support":0.5}' => 'missing min_confidence',
            '{"dataset_id":' . $tinyId . ',"min_support":0.5,"min_confidence":0.75,"extra":1}' => 'unknown field',
            '{"dataset_id":' . $tinyId . ',"dataset_id":' . $tinyId . ',"min_support":0.5,"min_confidence":0.75}' => 'duplicate field',
        ];
        foreach ($shapeCases as $body => $label) {
            $response = self::jsonRequest($client, $body);
            $assert(
                "Real mining endpoint rejects {$label}",
                self::isError($response, 400, 'INVALID_REQUEST'),
                self::responseSummary($response)
            );
        }

        foreach (['0', '-1', '1.0', '"1"', 'true', 'false', 'null', '[]', '{}'] as $token) {
            $response = self::jsonRequest(
                $client,
                '{"dataset_id":' . $token . ',"min_support":0.5,"min_confidence":0.75}'
            );
            $assert(
                "Real mining endpoint rejects dataset_id token {$token}",
                self::isError($response, 422, 'INVALID_DATASET_ID'),
                self::responseSummary($response)
            );
        }

        foreach (['0', '-1', '101', '1.0', '"1"', 'true', 'false', 'null', '[]', '{}'] as $token) {
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, '0.5', '0.75', $token)
            );
            $assert(
                "Real mining endpoint rejects top_n token {$token}",
                self::isError($response, 422, 'INVALID_TOP_N'),
                self::responseSummary($response)
            );
        }

        $missingDataset = self::jsonRequest(
            $client,
            self::miningBody(2_147_483_647, '0.5', '0.75', '20')
        );
        $assert(
            'Real mining endpoint maps a valid missing dataset to 404',
            self::isError($missingDataset, 404, 'DATASET_NOT_FOUND'),
            self::responseSummary($missingDataset)
        );
        $assert(
            'All transport, shape, scalar, and missing-dataset failures persist no completed run',
            self::runCount($pdo) === 0 && self::levelCount($pdo) === 0
        );
    }

    private static function testTinyOracleAndPersistence(
        HttpTestClient $client,
        PDO $pdo,
        int $tinyId,
        callable $assert
    ): void {
        self::clearRuns($pdo);
        $response = self::jsonRequest(
            $client,
            self::miningBody($tinyId, '0.5', '0.75', '20'),
            self::MINING_ENDPOINT,
            'Application/JSON; charset=UTF-8'
        );
        $payload = $response['json'];
        $runId = is_array($payload) ? ($payload['run_id'] ?? null) : null;

        $assert(
            'Real tiny mining returns 200, UTF-8 JSON, and exact top-level structure',
            $response['status'] === 200
                && self::headerValues($response, 'content-type') === ['application/json; charset=UTF-8']
                && is_array($payload)
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
            self::responseSummary($response)
        );
        $assert(
            'Real tiny response has exact dataset and normalized numeric parameters',
            ($payload['dataset'] ?? null) === [
                'id' => $tinyId,
                'name' => 'Tiny HTTP oracle',
                'transaction_count' => 4,
                'unique_item_count' => 3,
            ]
                && ($payload['parameters'] ?? null) === [
                    'min_support' => 0.5,
                    'min_confidence' => 0.75,
                    'top_n' => 20,
                ],
            self::responseSummary($response)
        );

        $summary = $payload['summary'] ?? null;
        $assert(
            'Real tiny response matches every deterministic complete summary oracle',
            is_array($summary)
                && array_keys($summary) === [
                    'frequent_itemsets',
                    'rules_count',
                    'runtime_ms',
                    'rule_generation_runtime_ms',
                    'max_k',
                    'candidates_generated',
                    'candidates_pruned',
                    'candidates_evaluated',
                    'pruning_ratio',
                ]
                && $summary['frequent_itemsets'] === 5
                && $summary['rules_count'] === 2
                && is_numeric($summary['runtime_ms'])
                && $summary['runtime_ms'] >= 0
                && is_numeric($summary['rule_generation_runtime_ms'])
                && $summary['rule_generation_runtime_ms'] >= 0
                && $summary['max_k'] === 2
                && $summary['candidates_generated'] === 7
                && $summary['candidates_pruned'] === 1
                && $summary['candidates_evaluated'] === 6
                && $summary['pruning_ratio'] === 0.142857,
            self::responseSummary($response)
        );

        $expectedLevels = [
            ['k' => 1, 'source' => 'singleton_scan', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 3, 'pruning_ratio' => 0],
            ['k' => 2, 'source' => 'join_prune', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 2, 'pruning_ratio' => 0],
            ['k' => 3, 'source' => 'join_prune', 'generated' => 1, 'pruned' => 1, 'evaluated' => 0, 'frequent' => 0, 'pruning_ratio' => 1],
        ];
        $assert(
            'Real tiny response has exact ascending level oracle',
            ($payload['levels'] ?? null) === $expectedLevels,
            self::responseSummary($response)
        );

        $expectedItemsets = [
            ['items' => ['A'], 'k' => 1, 'support_count' => 4, 'support' => 1],
            ['items' => ['A', 'B'], 'k' => 2, 'support_count' => 2, 'support' => 0.5],
            ['items' => ['A', 'C'], 'k' => 2, 'support_count' => 2, 'support' => 0.5],
            ['items' => ['B'], 'k' => 1, 'support_count' => 2, 'support' => 0.5],
            ['items' => ['C'], 'k' => 1, 'support_count' => 2, 'support' => 0.5],
        ];
        $assert(
            'Real tiny display itemsets follow support-desc, k-desc, canonical ordering',
            ($payload['itemsets'] ?? null) === $expectedItemsets,
            self::responseSummary($response)
        );

        $expectedRules = [
            ['antecedent' => ['B'], 'consequent' => ['A'], 'support_count' => 2, 'support' => 0.5, 'confidence' => 1, 'lift' => 1],
            ['antecedent' => ['C'], 'consequent' => ['A'], 'support_count' => 2, 'support' => 0.5, 'confidence' => 1, 'lift' => 1],
        ];
        $assert(
            'Real tiny response has exact frozen rule oracle',
            ($payload['rules'] ?? null) === $expectedRules,
            self::responseSummary($response)
        );
        $assert(
            'Real tiny response has exact full-dataset heatmap and untruncated limits',
            ($payload['heatmap'] ?? null) === [
                'metric' => 'support_count',
                'items' => ['A', 'B', 'C'],
                'values' => [[4, 2, 2], [2, 2, 1], [2, 1, 2]],
            ]
                && ($payload['result_limits'] ?? null) === [
                    'itemsets_returned' => 5,
                    'itemsets_truncated' => false,
                    'rules_returned' => 2,
                    'rules_truncated' => false,
                    'heatmap_items_returned' => 3,
                    'heatmap_items_truncated' => false,
                ],
            self::responseSummary($response)
        );

        $stored = is_int($runId) ? self::loadRun($pdo, $runId) : false;
        $assert(
            'Real tiny success persists exactly one completed run with exact thresholds and complete totals',
            self::runCount($pdo) === 1
                && is_array($stored)
                && (int)$stored['id'] === $runId
                && (int)$stored['dataset_id'] === $tinyId
                && (string)$stored['min_support'] === '0.500000'
                && (string)$stored['min_confidence'] === '0.750000'
                && (int)$stored['candidates_generated'] === 7
                && (int)$stored['candidates_pruned'] === 1
                && (int)$stored['candidates_evaluated'] === 6
                && (int)$stored['frequent_itemsets'] === 5
                && (int)$stored['rules_count'] === 2
                && (int)$stored['max_k'] === 2,
            self::rowSummary($stored)
        );
        $assert(
            'Real tiny success persists every complete exact level under the returned run_id',
            is_int($runId) && self::loadLevels($pdo, $runId) === [
                ['k' => 1, 'source' => 'singleton_scan', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 3],
                ['k' => 2, 'source' => 'join_prune', 'generated' => 3, 'pruned' => 0, 'evaluated' => 3, 'frequent' => 2],
                ['k' => 3, 'source' => 'join_prune', 'generated' => 1, 'pruned' => 1, 'evaluated' => 0, 'frequent' => 0],
            ]
        );
    }

    private static function testTopNOracleAndPersistence(
        HttpTestClient $client,
        PDO $pdo,
        int $tinyId,
        callable $assert
    ): void {
        $before = self::runCount($pdo);
        $response = self::jsonRequest(
            $client,
            self::miningBody($tinyId, '0.5', '0.75', '1')
        );
        $payload = $response['json'];
        $runId = is_array($payload) ? ($payload['run_id'] ?? null) : null;
        $stored = is_int($runId) ? self::loadRun($pdo, $runId) : false;

        $assert(
            'Real top_n=1 leaves complete summary while truncating all three display selections',
            $response['status'] === 200
                && ($payload['summary']['frequent_itemsets'] ?? null) === 5
                && ($payload['summary']['rules_count'] ?? null) === 2
                && count($payload['itemsets'] ?? []) === 1
                && count($payload['rules'] ?? []) === 1
                && ($payload['heatmap'] ?? null) === [
                    'metric' => 'support_count',
                    'items' => ['A'],
                    'values' => [[4]],
                ]
                && ($payload['result_limits'] ?? null) === [
                    'itemsets_returned' => 1,
                    'itemsets_truncated' => true,
                    'rules_returned' => 1,
                    'rules_truncated' => true,
                    'heatmap_items_returned' => 1,
                    'heatmap_items_truncated' => true,
                ],
            self::responseSummary($response)
        );
        $assert(
            'Real top_n=1 persists a new run with complete totals and all levels',
            self::runCount($pdo) === $before + 1
                && is_array($stored)
                && (int)$stored['frequent_itemsets'] === 5
                && (int)$stored['rules_count'] === 2
                && is_int($runId)
                && count(self::loadLevels($pdo, $runId)) === 3,
            self::rowSummary($stored)
        );
    }

    private static function testThresholdBoundaries(
        HttpTestClient $client,
        PDO $pdo,
        int $tinyId,
        callable $assert
    ): void {
        $acceptedSupport = ['0.000001', '0.123456', '1', '1e-6', '10e-7'];
        foreach ($acceptedSupport as $token) {
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, $token, '0.75', '1')
            );
            $assert(
                "Real mining accepts exact min_support boundary {$token}",
                $response['status'] === 200,
                self::responseSummary($response)
            );
        }

        $acceptedConfidence = ['0', '1', '0.123456', '1e-6', '10e-7'];
        foreach ($acceptedConfidence as $token) {
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, '0.5', $token, '1')
            );
            $assert(
                "Real mining accepts exact min_confidence boundary {$token}",
                $response['status'] === 200,
                self::responseSummary($response)
            );
        }

        $beforeInvalid = self::runCount($pdo);
        foreach (['0', '1.000001', '2', '0.0000001', '0.1234567', '1e-7'] as $token) {
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, $token, '0.75', '1')
            );
            $assert(
                "Real mining rejects invalid min_support boundary {$token}",
                self::isError($response, 422, 'INVALID_MIN_SUPPORT'),
                self::responseSummary($response)
            );
        }

        foreach (['-0.000001', '1.000001', '2', '0.0000001', '0.1234567', '1e-7'] as $token) {
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, '0.5', $token, '1')
            );
            $assert(
                "Real mining rejects invalid min_confidence boundary {$token}",
                self::isError($response, 422, 'INVALID_MIN_CONFIDENCE'),
                self::responseSummary($response)
            );
        }

        foreach (['"0.5"', 'true', 'false', 'null', '[]', '{}'] as $token) {
            $support = self::jsonRequest(
                $client,
                self::miningBody($tinyId, $token, '0.75', '1')
            );
            $confidence = self::jsonRequest(
                $client,
                self::miningBody($tinyId, '0.5', $token, '1')
            );
            $assert(
                "Real mining rejects non-number threshold token {$token} for both fields",
                self::isError($support, 422, 'INVALID_MIN_SUPPORT')
                    && self::isError($confidence, 422, 'INVALID_MIN_CONFIDENCE'),
                self::responseSummary($support)
            );
        }
        $assert(
            'Every invalid threshold request adds zero completed run rows',
            self::runCount($pdo) === $beforeInvalid
        );
    }

    private static function testNumericOracle(HttpTestClient $client, callable $assert): void
    {
        $numericItems = ['1', '01', '001', '1.0', '+1', '0', '-1'];
        $numericId = self::importDataset(
            $client,
            'numeric.txt',
            implode(' ', $numericItems),
            'basket_txt',
            'Numeric HTTP oracle'
        );
        $response = self::jsonRequest(
            $client,
            self::miningBody($numericId, '1', '1', '20')
        );
        $payload = $response['json'];
        $heatmapItems = is_array($payload) ? ($payload['heatmap']['items'] ?? null) : null;
        $allStrings = is_array($heatmapItems);
        if ($allStrings) {
            foreach ($heatmapItems as $item) {
                $allStrings = $allStrings && is_string($item);
            }
        }

        $assert(
            'Real numeric basket_txt import mines successfully without numeric-key TypeError',
            $numericId > 0 && $response['status'] === 200,
            self::responseSummary($response)
        );
        $assert(
            'Real numeric mining heatmap preserves every numeric-looking item as a distinct string',
            $allStrings
                && $heatmapItems === ['+1', '-1', '0', '001', '01', '1', '1.0']
                && count(array_unique($heatmapItems, SORT_STRING)) === 7,
            self::responseSummary($response)
        );
        $assert(
            'Real numeric mining never exposes encoded internal identities',
            !str_contains($response['body'], '\\u0000')
                && !str_contains($response['body'], '00000001')
                && self::nestedItemValuesAreStrings($payload),
            self::responseSummary($response)
        );
    }

    private static function testGuardrails(PDO $pdo, int $tinyId, callable $assert): void
    {
        self::clearRuns($pdo);
        $candidateClient = null;
        $ruleClient = null;

        try {
            $candidateClient = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment(['MINING_MAX_CANDIDATES' => '1'])
            );
            $candidate = self::jsonRequest(
                $candidateClient,
                self::miningBody($tinyId, '0.5', '0.75', '20')
            );
            $assert(
                'Real candidate computation guardrail maps to safe 503 MINING_LIMIT_EXCEEDED',
                self::isError($candidate, 503, 'MINING_LIMIT_EXCEEDED')
                    && array_keys($candidate['json']) === ['error'],
                self::responseSummary($candidate)
            );
            $assert(
                'Real candidate guardrail returns no partial result and persists no run or level',
                !str_contains($candidate['body'], 'itemsets')
                    && !str_contains($candidate['body'], 'heatmap')
                    && self::runCount($pdo) === 0
                    && self::levelCount($pdo) === 0
            );

            $ruleClient = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment(['MINING_MAX_RULES' => '1'])
            );
            $rule = self::jsonRequest(
                $ruleClient,
                self::miningBody($tinyId, '0.5', '0', '20')
            );
            $assert(
                'Real rule-count guardrail maps to safe 503 MINING_LIMIT_EXCEEDED',
                self::isError($rule, 503, 'MINING_LIMIT_EXCEEDED'),
                self::responseSummary($rule)
            );
            $assert(
                'Real rule-count guardrail persists no run or level',
                self::runCount($pdo) === 0 && self::levelCount($pdo) === 0
            );
        } finally {
            if ($candidateClient instanceof HttpTestClient) {
                $candidateClient->stop();
            }
            if ($ruleClient instanceof HttpTestClient) {
                $ruleClient->stop();
            }
        }
    }

    private static function testControlledInternalFailure(PDO $pdo, int $tinyId, callable $assert): void
    {
        $before = self::runCount($pdo);
        $client = null;

        try {
            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment([
                    'DB_PORT' => '1',
                    'DB_PASSWORD' => 'server-secret-password',
                ])
            );
            $response = self::jsonRequest(
                $client,
                self::miningBody($tinyId, '0.5', '0.75', '20')
            );
            $lowerBody = strtolower($response['body']);
            $forbidden = [
                'server-secret-password',
                'mysql:',
                'select ',
                'insert ',
                'pdoexception',
                'stack trace',
                'd:\\projects',
                'd:/projects',
                'repository',
            ];
            $safe = true;
            foreach ($forbidden as $needle) {
                $safe = $safe && !str_contains($lowerBody, $needle);
            }

            $assert(
                'Controlled real server bootstrap failure maps to exact generic 500 INTERNAL_ERROR',
                self::isError($response, 500, 'INTERNAL_ERROR'),
                self::responseSummary($response)
            );
            $assert(
                'Controlled 500 response discloses no password, DSN, SQL, path, stack, or Throwable class',
                $safe,
                self::responseSummary($response)
            );
            $assert(
                'Controlled server failure adds no completed run',
                self::runCount($pdo) === $before
            );
        } finally {
            if ($client instanceof HttpTestClient) {
                $client->stop();
            }
        }
    }

    private static function importDataset(
        HttpTestClient $client,
        string $filename,
        string $content,
        string $format,
        string $name
    ): int {
        $multipart = HttpTestClient::multipart(
            ['format' => $format, 'name' => $name],
            [[
                'field' => 'file',
                'filename' => $filename,
                'content' => $content,
                'content_type' => 'application/octet-stream',
            ]]
        );
        $response = $client->request(
            'POST',
            self::DATASET_ENDPOINT,
            ['Content-Type' => $multipart['content_type']],
            $multipart['body']
        );
        $id = $response['json']['dataset']['id'] ?? null;
        if ($response['status'] !== 201 || !is_int($id) || $id < 1) {
            throw new RuntimeException('Dataset import failed: ' . self::responseSummary($response));
        }

        return $id;
    }

    /**
     * @return array{status: int, headers: array<string, list<string>>, body: string, json: mixed, json_object: mixed}
     */
    private static function jsonRequest(
        HttpTestClient $client,
        string $body,
        string $target = self::MINING_ENDPOINT,
        string $contentType = 'application/json'
    ): array {
        return $client->request(
            'POST',
            $target,
            ['Content-Type' => $contentType],
            $body
        );
    }

    private static function miningBody(int $datasetId, string $support, string $confidence, string $topN): string
    {
        return '{"dataset_id":' . $datasetId
            . ',"min_support":' . $support
            . ',"min_confidence":' . $confidence
            . ',"top_n":' . $topN . '}';
    }

    /**
     * @param array{status: int, headers: array<string, list<string>>, body: string, json: mixed, json_object: mixed} $response
     */
    private static function isError(array $response, int $status, string $code): bool
    {
        $error = is_array($response['json']) ? ($response['json']['error'] ?? null) : null;
        $objectError = is_object($response['json_object'])
            && isset($response['json_object']->error)
            && is_object($response['json_object']->error)
            ? $response['json_object']->error
            : null;

        return $response['status'] === $status
            && is_array($response['json'])
            && array_keys($response['json']) === ['error']
            && is_array($error)
            && array_keys($error) === ['code', 'message', 'details']
            && $error['code'] === $code
            && is_string($error['message'])
            && $error['message'] !== ''
            && $objectError !== null
            && isset($objectError->details)
            && is_object($objectError->details)
            && get_object_vars($objectError->details) === []
            && self::headerValues($response, 'content-type') === ['application/json; charset=UTF-8'];
    }

    private static function nestedItemValuesAreStrings(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        foreach ($payload['itemsets'] ?? [] as $itemset) {
            foreach ($itemset['items'] ?? [] as $item) {
                if (!is_string($item)) {
                    return false;
                }
            }
        }
        foreach ($payload['rules'] ?? [] as $rule) {
            foreach (array_merge($rule['antecedent'] ?? [], $rule['consequent'] ?? []) as $item) {
                if (!is_string($item)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array{headers: array<string, list<string>>} $response
     * @return list<string>
     */
    private static function headerValues(array $response, string $name): array
    {
        return $response['headers'][strtolower($name)] ?? [];
    }

    /** @return array<string, mixed>|false */
    private static function loadRun(PDO $pdo, int $runId): array|false
    {
        $statement = $pdo->prepare(
            'SELECT `id`, `dataset_id`, `min_support`, `min_confidence`, '
            . '`candidates_generated`, `candidates_pruned`, `candidates_evaluated`, '
            . '`frequent_itemsets`, `rules_count`, `max_k` '
            . 'FROM `experiment_runs` WHERE `id` = :id'
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
            static fn(array $row): array => [
                'k' => (int)$row['k'],
                'source' => (string)$row['source'],
                'generated' => (int)$row['generated'],
                'pruned' => (int)$row['pruned'],
                'evaluated' => (int)$row['evaluated'],
                'frequent' => (int)$row['frequent'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private static function runCount(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM `experiment_runs`')->fetchColumn();
    }

    private static function levelCount(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM `experiment_run_levels`')->fetchColumn();
    }

    private static function allTableRows(PDO $pdo): int
    {
        $total = 0;
        foreach (['experiment_run_levels', 'experiment_runs', 'transaction_items', 'transactions', 'datasets'] as $table) {
            $total += (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }

        return $total;
    }

    private static function clearRuns(PDO $pdo): void
    {
        SchemaTest::assertTestSafety();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $pdo->exec('TRUNCATE TABLE `experiment_run_levels`');
            $pdo->exec('TRUNCATE TABLE `experiment_runs`');
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function clearTestTables(PDO $pdo): void
    {
        SchemaTest::assertTestSafety();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['experiment_run_levels', 'experiment_runs', 'transaction_items', 'transactions', 'datasets'] as $table) {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
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

    /** @param array<string, string> $overrides @return array<string, string> */
    private static function serverEnvironment(array $overrides = []): array
    {
        return array_replace([
            'APP_ENV' => 'test',
            'APP_DEBUG' => 'false',
            'DB_HOST' => (string)(getenv('DB_HOST') ?: '127.0.0.1'),
            'DB_PORT' => (string)(getenv('DB_PORT') ?: '3306'),
            'DB_NAME' => 'fim_dashboard_test',
            'DB_USER' => (string)(getenv('DB_USER') ?: 'root'),
            'DB_PASSWORD' => (string)(getenv('DB_PASSWORD') ?: ''),
            'UPLOAD_MAX_BYTES' => '10485760',
            'MINING_TIMEOUT_SECONDS' => '30',
            'MINING_MAX_CANDIDATES' => '250000',
            'MINING_MAX_RULES' => '50000',
        ], $overrides);
    }

    private static function tinyBytes(): string
    {
        $bytes = file_get_contents(dirname(__DIR__) . '/fixtures/tiny.csv');
        if (!is_string($bytes)) {
            throw new RuntimeException('tiny.csv could not be read.');
        }

        return $bytes;
    }

    /** @param array{status: int, body: string} $response */
    private static function responseSummary(array $response): string
    {
        $body = strlen($response['body']) > 700
            ? substr($response['body'], 0, 700) . '...'
            : $response['body'];

        return "status={$response['status']}, body={$body}";
    }

    /** @param array<string, mixed>|false $row */
    private static function rowSummary(array|false $row): string
    {
        return $row === false ? 'row not found' : (json_encode($row) ?: 'row not encodable');
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
