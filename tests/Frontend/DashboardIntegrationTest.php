<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use App\Persistence\ConnectionFactory;
use App\Persistence\Migrator;
use App\Persistence\SchemaVerifier;
use App\Tests\Api\HttpTestClient;
use App\Tests\Unit\SchemaTest;
use PDO;
use Throwable;

/**
 * Phase 3F tests: AJAX contract, end-to-end dataset import & mining flow, XSS safety, and result limits.
 */
final class DashboardIntegrationTest
{
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
                'Dashboard integration starts from valid frozen schema',
                $schemaErrors === [],
                implode('; ', $schemaErrors)
            );
            self::clearTestTables($pdo);

            $client = HttpTestClient::start(
                dirname(__DIR__, 2) . '/public',
                self::serverEnvironment()
            );

            // Test 1: Empty dataset listing on initial bootstrap
            self::testInitialEmptyDatasets($client, $assert);

            // Test 2: Dataset import via multipart/form-data
            $datasetId = self::testDatasetImport($client, $assert);

            // Test 3: Dataset listing after import contains newly created dataset
            self::testDatasetListAfterImport($client, $datasetId, $assert);

            // Test 4: Mining with explicit JSON request
            self::testMiningExecution($client, $datasetId, $assert);

            // Test 5: Top-N truncation semantics
            self::testTopNTruncation($client, $datasetId, $assert);

            // Test 6: Hostile / XSS-laden dataset import and mining
            self::testHostileStringHandling($client, $assert);

            // Test 7: Parameter boundary and error contract
            self::testMiningErrorContracts($client, $datasetId, $assert);

            // Test 8: Repeated sequential mining runs
            self::testRepeatedMiningRuns($client, $datasetId, $assert);

        } catch (Throwable $throwable) {
            $assert(
                'Dashboard integration test completes without harness error',
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
                        'Dashboard integration leaves frozen schema valid and test tables clean',
                        $schemaErrors === [],
                        implode('; ', $schemaErrors)
                    );
                } catch (Throwable $throwable) {
                    $assert(
                        'Dashboard integration cleanup succeeds',
                        false,
                        get_class($throwable) . ': ' . $throwable->getMessage()
                    );
                }
            }

            self::restoreEnvironment('APP_ENV', $originalAppEnv);
            self::restoreEnvironment('DB_NAME', $originalDatabase);
        }

        return [
            'passed' => $passed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testInitialEmptyDatasets(HttpTestClient $client, callable $assert): void
    {
        $response = $client->request('GET', '/api/datasets.php');
        $assert('GET /api/datasets.php returns HTTP 200 on bootstrap', $response['status'] === 200);
        $assert('Initial datasets payload is an array', isset($response['json']['datasets']) && is_array($response['json']['datasets']));
        $assert('Initial datasets array is empty', count($response['json']['datasets']) === 0);
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testDatasetImport(HttpTestClient $client, callable $assert): int
    {
        $csvContent = "A,B,C\nA,B\nA,C\nA";

        // Regression Test 1: Direct API request with explicit empty name="" rejects with 422
        $emptyNameMultipart = HttpTestClient::multipart(
            [
                'format' => 'basket_csv',
                'name' => '',
            ],
            [
                [
                    'field' => 'file',
                    'filename' => 'empty_name.csv',
                    'content' => $csvContent,
                    'content_type' => 'text/csv',
                ],
            ]
        );
        $emptyNameResponse = $client->request(
            'POST',
            '/api/datasets.php',
            ['Content-Type' => $emptyNameMultipart['content_type']],
            $emptyNameMultipart['body']
        );
        $assert(
            'Direct API POST with explicit empty name="" rejects with HTTP 422',
            $emptyNameResponse['status'] === 422
        );
        $assert(
            'Empty name rejection code is DATASET_VALIDATION_FAILED or INVALID_DATASET_NAME',
            ($emptyNameResponse['json']['error']['code'] ?? '') === 'DATASET_VALIDATION_FAILED'
            || ($emptyNameResponse['json']['error']['code'] ?? '') === 'INVALID_DATASET_NAME'
        );

        // Regression Test 2: Case A — optional name field omitted from multipart -> defaults to source basename
        $omittedNameMultipart = HttpTestClient::multipart(
            [
                'format' => 'basket_csv',
            ],
            [
                [
                    'field' => 'file',
                    'filename' => 'default_basename.csv',
                    'content' => $csvContent,
                    'content_type' => 'text/csv',
                ],
            ]
        );
        $omittedNameResponse = $client->request(
            'POST',
            '/api/datasets.php',
            ['Content-Type' => $omittedNameMultipart['content_type']],
            $omittedNameMultipart['body']
        );
        $assert(
            'Case A: POST /api/datasets.php with omitted name returns HTTP 201',
            $omittedNameResponse['status'] === 201
        );
        $assert(
            'Case A: Omitted name defaults to source filename basename without extension',
            ($omittedNameResponse['json']['dataset']['name'] ?? '') === 'default_basename'
        );

        // Regression Test 3: Case B — explicit name supplied with whitespace -> trimmed and preserved
        $multipart = HttpTestClient::multipart(
            [
                'format' => 'basket_csv',
                'name' => '  Tiny Oracle Test Dataset  ',
            ],
            [
                [
                    'field' => 'file',
                    'filename' => 'tiny_oracle.csv',
                    'content' => $csvContent,
                    'content_type' => 'text/csv',
                ],
            ]
        );

        $response = $client->request(
            'POST',
            '/api/datasets.php',
            ['Content-Type' => $multipart['content_type']],
            $multipart['body']
        );

        $assert('Case B: POST /api/datasets.php with explicit name returns HTTP 201', $response['status'] === 201);
        $assert('Import response has dataset object', isset($response['json']['dataset']['id']));
        $assert('Case B: Explicit dataset name is trimmed and persisted correctly', ($response['json']['dataset']['name'] ?? '') === 'Tiny Oracle Test Dataset');
        $assert('Import response transaction_count is 4', ($response['json']['dataset']['transaction_count'] ?? 0) === 4);
        $assert('Import response unique_item_count is 3', ($response['json']['dataset']['unique_item_count'] ?? 0) === 3);
        $assert('Import response total_warnings is 0', ($response['json']['total_warnings'] ?? -1) === 0);

        return (int)$response['json']['dataset']['id'];
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testDatasetListAfterImport(HttpTestClient $client, int $datasetId, callable $assert): void
    {
        $response = $client->request('GET', '/api/datasets.php');
        $assert('GET /api/datasets.php after import returns 200', $response['status'] === 200);
        $datasets = $response['json']['datasets'] ?? [];
        $assert('Datasets list contains exactly 2 datasets', count($datasets) === 2);
        $assert('Dataset list newest item ID matches imported ID', (int)($datasets[0]['id'] ?? 0) === $datasetId);
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testMiningExecution(HttpTestClient $client, int $datasetId, callable $assert): void
    {
        $payload = [
            'dataset_id' => $datasetId,
            'min_support' => 0.5,
            'min_confidence' => 0.75,
            'top_n' => 20,
        ];

        $response = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $assert('POST /api/mining.php returns HTTP 200', $response['status'] === 200);

        $json = $response['json'];
        $assert('Mining response contains run_id as positive int', isset($json['run_id']) && is_int($json['run_id']) && $json['run_id'] > 0);
        $assert('Mining response contains dataset profile', isset($json['dataset']['name']));
        $assert('Mining response contains normalized parameters', isset($json['parameters']['min_support']) && $json['parameters']['min_support'] === 0.5);

        // Summary KPI verification
        $summary = $json['summary'] ?? [];
        $assert('KPI frequent_itemsets equals 5', ($summary['frequent_itemsets'] ?? 0) === 5);
        $assert('KPI rules_count equals 2', ($summary['rules_count'] ?? 0) === 2);
        $assert('KPI candidates_generated equals 7', ($summary['candidates_generated'] ?? 0) === 7);
        $assert('KPI candidates_pruned equals 1', ($summary['candidates_pruned'] ?? -1) === 1);
        $assert('KPI candidates_evaluated equals 6', ($summary['candidates_evaluated'] ?? 0) === 6);
        $assert('KPI max_k equals 2', ($summary['max_k'] ?? 0) === 2);
        $assert('KPI pruning_ratio is approx 0.142857', abs(($summary['pruning_ratio'] ?? -1.0) - (1.0 / 7.0)) < 0.0001);
        $assert('KPI runtime_ms is a non-negative float', isset($summary['runtime_ms']) && is_float($summary['runtime_ms']) && $summary['runtime_ms'] >= 0);

        // Itemsets verification
        $itemsets = $json['itemsets'] ?? [];
        $assert('Itemsets array contains 5 itemsets', count($itemsets) === 5);
        $assert('First itemset is {A} with support_count 4', ($itemsets[0]['items'] ?? []) === ['A'] && ($itemsets[0]['support_count'] ?? 0) === 4);

        // Rules verification
        $rules = $json['rules'] ?? [];
        $assert('Rules array contains 2 rules', count($rules) === 2);
        $assert('First rule has confidence 1.0', (float)($rules[0]['confidence'] ?? 0) === 1.0);

        // Heatmap verification
        $heatmap = $json['heatmap'] ?? [];
        $assert('Heatmap items contains 3 singletons [A, B, C]', ($heatmap['items'] ?? []) === ['A', 'B', 'C']);
        $assert('Heatmap values matrix is 3x3', isset($heatmap['values']) && count($heatmap['values']) === 3 && count($heatmap['values'][0]) === 3);

        // Levels verification (k=1, k=2, k=3)
        $levels = $json['levels'] ?? [];
        $assert('Levels array contains 3 levels (k=1, k=2, k=3)', count($levels) === 3);
        $assert('Level k=1 has 3 generated and 3 frequent', ($levels[0]['generated'] ?? 0) === 3 && ($levels[0]['frequent'] ?? 0) === 3);
        $assert('Level k=2 has 3 generated and 2 frequent', ($levels[1]['generated'] ?? 0) === 3 && ($levels[1]['frequent'] ?? 0) === 2);
        $assert('Level k=3 has 1 generated, 1 pruned, 0 frequent', ($levels[2]['generated'] ?? 0) === 1 && ($levels[2]['pruned'] ?? 0) === 1 && ($levels[2]['frequent'] ?? 0) === 0);
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testTopNTruncation(HttpTestClient $client, int $datasetId, callable $assert): void
    {
        $payload = [
            'dataset_id' => $datasetId,
            'min_support' => 0.5,
            'min_confidence' => 0.75,
            'top_n' => 1,
        ];

        $response = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $assert('POST /api/mining.php with top_n=1 returns 200', $response['status'] === 200);

        $json = $response['json'];
        $limits = $json['result_limits'] ?? [];
        $summary = $json['summary'] ?? [];

        $assert('summary.frequent_itemsets remains complete total 5 with top_n=1', ($summary['frequent_itemsets'] ?? 0) === 5);
        $assert('summary.rules_count remains complete total 2 with top_n=1', ($summary['rules_count'] ?? 0) === 2);
        $assert('result_limits.itemsets_truncated is true with top_n=1', ($limits['itemsets_truncated'] ?? false) === true);
        $assert('result_limits.itemsets_returned is 1 with top_n=1', ($limits['itemsets_returned'] ?? 0) === 1);
        $assert('result_limits.rules_truncated is true with top_n=1', ($limits['rules_truncated'] ?? false) === true);
        $assert('result_limits.rules_returned is 1 with top_n=1', ($limits['rules_returned'] ?? 0) === 1);
        $assert('Returned itemsets array length is 1', count($json['itemsets'] ?? []) === 1);
        $assert('Returned rules array length is 1', count($json['rules'] ?? []) === 1);
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testHostileStringHandling(HttpTestClient $client, callable $assert): void
    {
        $hostileItem1 = '<img src=x onerror=window.__fimXss=1>';
        $hostileItem2 = '<script>window.__fimXss=1</script>';
        $content = "{$hostileItem1},safe_item\n{$hostileItem1},{$hostileItem2}\n{$hostileItem1},safe_item\n";

        $multipart = HttpTestClient::multipart(
            [
                'format' => 'basket_csv',
                'name' => '<script>alert("xss")</script> Test',
            ],
            [
                [
                    'field' => 'file',
                    'filename' => 'hostile.csv',
                    'content' => $content,
                    'content_type' => 'text/csv',
                ],
            ]
        );

        $importRes = $client->request(
            'POST',
            '/api/datasets.php',
            ['Content-Type' => $multipart['content_type']],
            $multipart['body']
        );

        $assert('Hostile dataset import succeeds with 201', $importRes['status'] === 201);
        $hostileDatasetId = (int)$importRes['json']['dataset']['id'];

        $miningRes = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode([
                'dataset_id' => $hostileDatasetId,
                'min_support' => 0.3,
                'min_confidence' => 0.5,
                'top_n' => 10,
            ], JSON_THROW_ON_ERROR)
        );

        $assert('Mining on hostile dataset returns 200', $miningRes['status'] === 200);
        $items = $miningRes['json']['heatmap']['items'] ?? [];
        $assert('Heatmap items contains the verbatim hostile string safely encoded in JSON', in_array($hostileItem1, $items, true));
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testMiningErrorContracts(HttpTestClient $client, int $datasetId, callable $assert): void
    {
        // 1. Missing dataset_id (missing required JSON field) -> 400 INVALID_REQUEST
        $res1 = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode(['min_support' => 0.5, 'min_confidence' => 0.5, 'top_n' => 10], JSON_THROW_ON_ERROR)
        );
        $assert('Missing dataset_id maps to HTTP 400 INVALID_REQUEST', $res1['status'] === 400 && ($res1['json']['error']['code'] ?? '') === 'INVALID_REQUEST');

        // 2. Invalid dataset_id (-1) -> 422 INVALID_DATASET_ID
        $res2 = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode(['dataset_id' => -1, 'min_support' => 0.5, 'min_confidence' => 0.5, 'top_n' => 10], JSON_THROW_ON_ERROR)
        );
        $assert('Invalid negative dataset_id maps to HTTP 422 INVALID_DATASET_ID', $res2['status'] === 422 && ($res2['json']['error']['code'] ?? '') === 'INVALID_DATASET_ID');

        // 3. Out of range support (1.5) -> 422 INVALID_MIN_SUPPORT
        $res3 = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode(['dataset_id' => $datasetId, 'min_support' => 1.5, 'min_confidence' => 0.5, 'top_n' => 10], JSON_THROW_ON_ERROR)
        );
        $assert('Out of range min_support (1.5) maps to HTTP 422 INVALID_MIN_SUPPORT', $res3['status'] === 422 && ($res3['json']['error']['code'] ?? '') === 'INVALID_MIN_SUPPORT');

        // 4. Nonexistent dataset_id -> 404 DATASET_NOT_FOUND
        $res4 = $client->request(
            'POST',
            '/api/mining.php',
            ['Content-Type' => 'application/json'],
            json_encode(['dataset_id' => 999999, 'min_support' => 0.5, 'min_confidence' => 0.5, 'top_n' => 10], JSON_THROW_ON_ERROR)
        );
        $assert('Nonexistent dataset_id maps to HTTP 404 DATASET_NOT_FOUND', $res4['status'] === 404 && ($res4['json']['error']['code'] ?? '') === 'DATASET_NOT_FOUND');
    }

    /**
     * @param callable(string, bool, string=): void $assert
     */
    private static function testRepeatedMiningRuns(HttpTestClient $client, int $datasetId, callable $assert): void
    {
        $payload1 = json_encode(['dataset_id' => $datasetId, 'min_support' => 0.5, 'min_confidence' => 0.75, 'top_n' => 20], JSON_THROW_ON_ERROR);
        $payload2 = json_encode(['dataset_id' => $datasetId, 'min_support' => 0.25, 'min_confidence' => 0.5, 'top_n' => 20], JSON_THROW_ON_ERROR);

        $res1 = $client->request('POST', '/api/mining.php', ['Content-Type' => 'application/json'], $payload1);
        $res2 = $client->request('POST', '/api/mining.php', ['Content-Type' => 'application/json'], $payload2);

        $assert('First sequential mining run returns 200', $res1['status'] === 200);
        $assert('Second sequential mining run returns 200', $res2['status'] === 200);
        $assert('Sequential runs generate distinct incrementing run IDs', (int)$res2['json']['run_id'] > (int)$res1['json']['run_id']);
    }

    private static function createTestConnection(): PDO
    {
        return ConnectionFactory::create([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => 'fim_dashboard_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
        ]);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
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

    private static function clearTestTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE experiment_run_levels');
        $pdo->exec('TRUNCATE TABLE experiment_runs');
        $pdo->exec('TRUNCATE TABLE transaction_items');
        $pdo->exec('TRUNCATE TABLE transactions');
        $pdo->exec('TRUNCATE TABLE datasets');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function restoreEnvironment(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
        } else {
            putenv("{$key}={$value}");
        }
    }
}
