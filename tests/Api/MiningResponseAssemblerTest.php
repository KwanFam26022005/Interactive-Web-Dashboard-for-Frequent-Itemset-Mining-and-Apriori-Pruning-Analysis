<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Dataset\BasketCsvParser;
use App\Dataset\CanonicalTransaction;
use App\Http\JsonResponder;
use App\Http\MiningResponseAssembler;
use App\Mining\AprioriEngine;
use App\Mining\AprioriResult;
use App\Mining\AssociationRuleGenerator;
use App\Mining\HeatmapBuilder;
use App\Mining\HeatmapResult;
use App\Mining\LevelMetrics;
use App\Mining\RuleGenerationResult;
use App\Persistence\DatasetRecord;
use RuntimeException;
use Throwable;

final class MiningResponseAssemblerTest
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

        self::testTinyExactResponse($assert);
        self::testTopN($assert);
        self::testNumericItems($assert);
        self::testZeroGeneratedRatios($assert);
        self::testInvariantFailure($assert);

        return ['passed' => $passed, 'failed' => $failed, 'results' => $results];
    }

    private static function testTinyExactResponse(callable $assert): void
    {
        [$dataset, $apriori, $rules, $heatmap] = self::tinyDomain();
        $payload = (new MiningResponseAssembler())->assemble(
            17,
            $dataset,
            500_000,
            750_000,
            20,
            $apriori,
            $rules,
            $heatmap
        );

        $assert(
            'Mining assembler emits the exact frozen top-level key order',
            array_keys($payload) === [
                'run_id',
                'dataset',
                'parameters',
                'summary',
                'levels',
                'itemsets',
                'rules',
                'heatmap',
                'result_limits',
            ],
            self::describe($payload)
        );
        $assert(
            'Mining assembler emits the exact dataset and normalized parameter subsets',
            $payload['run_id'] === 17
                && $payload['dataset'] === [
                    'id' => 9,
                    'name' => 'Tiny oracle',
                    'transaction_count' => 4,
                    'unique_item_count' => 3,
                ]
                && $payload['parameters'] === [
                    'min_support' => 0.5,
                    'min_confidence' => 0.75,
                    'top_n' => 20,
                ],
            self::describe($payload)
        );
        $assert(
            'Mining assembler emits complete exact tiny summary and rounded runtimes',
            $payload['summary'] === [
                'frequent_itemsets' => 5,
                'rules_count' => 2,
                'runtime_ms' => 1.235,
                'rule_generation_runtime_ms' => 0.046,
                'max_k' => 2,
                'candidates_generated' => 7,
                'candidates_pruned' => 1,
                'candidates_evaluated' => 6,
                'pruning_ratio' => 0.142857,
            ],
            self::describe($payload['summary'])
        );
        $assert(
            'Mining assembler emits exact ascending levels with exact ratios',
            $payload['levels'] === [
                [
                    'k' => 1,
                    'source' => 'singleton_scan',
                    'generated' => 3,
                    'pruned' => 0,
                    'evaluated' => 3,
                    'frequent' => 3,
                    'pruning_ratio' => 0.0,
                ],
                [
                    'k' => 2,
                    'source' => 'join_prune',
                    'generated' => 3,
                    'pruned' => 0,
                    'evaluated' => 3,
                    'frequent' => 2,
                    'pruning_ratio' => 0.0,
                ],
                [
                    'k' => 3,
                    'source' => 'join_prune',
                    'generated' => 1,
                    'pruned' => 1,
                    'evaluated' => 0,
                    'frequent' => 0,
                    'pruning_ratio' => 1.0,
                ],
            ],
            self::describe($payload['levels'])
        );
        $assert(
            'Mining assembler independently sorts display itemsets by support, k, and canonical order',
            $payload['itemsets'] === [
                ['items' => ['A'], 'k' => 1, 'support_count' => 4, 'support' => 1.0],
                ['items' => ['A', 'B'], 'k' => 2, 'support_count' => 2, 'support' => 0.5],
                ['items' => ['A', 'C'], 'k' => 2, 'support_count' => 2, 'support' => 0.5],
                ['items' => ['B'], 'k' => 1, 'support_count' => 2, 'support' => 0.5],
                ['items' => ['C'], 'k' => 1, 'support_count' => 2, 'support' => 0.5],
            ],
            self::describe($payload['itemsets'])
        );
        $assert(
            'Mining assembler preserves the frozen complete rule order and rounded metrics',
            $payload['rules'] === [
                [
                    'antecedent' => ['B'],
                    'consequent' => ['A'],
                    'support_count' => 2,
                    'support' => 0.5,
                    'confidence' => 1.0,
                    'lift' => 1.0,
                ],
                [
                    'antecedent' => ['C'],
                    'consequent' => ['A'],
                    'support_count' => 2,
                    'support' => 0.5,
                    'confidence' => 1.0,
                    'lift' => 1.0,
                ],
            ],
            self::describe($payload['rules'])
        );
        $assert(
            'Mining assembler emits exact full-dataset heatmap and result limits',
            $payload['heatmap'] === [
                'metric' => 'support_count',
                'items' => ['A', 'B', 'C'],
                'values' => [[4, 2, 2], [2, 2, 1], [2, 1, 2]],
            ]
                && $payload['result_limits'] === [
                    'itemsets_returned' => 5,
                    'itemsets_truncated' => false,
                    'rules_returned' => 2,
                    'rules_truncated' => false,
                    'heatmap_items_returned' => 3,
                    'heatmap_items_truncated' => false,
                ],
            self::describe($payload)
        );

        $encodable = true;
        try {
            (new JsonResponder())->assertEncodable($payload);
        } catch (Throwable) {
            $encodable = false;
        }
        $assert('Complete assembled tiny response is safely JSON serializable', $encodable);
    }

    private static function testTopN(callable $assert): void
    {
        [$dataset, $apriori, $rules, $unused] = self::tinyDomain();
        $transactions = self::tinyTransactions();
        $heatmap = (new HeatmapBuilder())->build($transactions, 1);
        $payload = (new MiningResponseAssembler())->assemble(
            0,
            $dataset,
            500_000,
            750_000,
            1,
            $apriori,
            $rules,
            $heatmap
        );

        $assert(
            'top_n=1 truncates only display arrays and heatmap selection',
            $payload['summary']['frequent_itemsets'] === 5
                && $payload['summary']['rules_count'] === 2
                && $payload['itemsets'] === [
                    ['items' => ['A'], 'k' => 1, 'support_count' => 4, 'support' => 1.0],
                ]
                && $payload['rules'][0]['antecedent'] === ['B']
                && $payload['heatmap'] === [
                    'metric' => 'support_count',
                    'items' => ['A'],
                    'values' => [[4]],
                ]
                && $payload['result_limits'] === [
                    'itemsets_returned' => 1,
                    'itemsets_truncated' => true,
                    'rules_returned' => 1,
                    'rules_truncated' => true,
                    'heatmap_items_returned' => 1,
                    'heatmap_items_truncated' => true,
                ],
            self::describe($payload)
        );
    }

    private static function testNumericItems(callable $assert): void
    {
        $items = ['1', '01', '001', '1.0', '+1', '0', '-1'];
        $transactions = [CanonicalTransaction::fromRawItems(1, $items)];
        $rawApriori = (new AprioriEngine())->run($transactions, 1_000_000);
        $apriori = self::withAprioriElapsed($rawApriori, 0);
        $rules = new RuleGenerationResult([], 0, 0);
        $heatmap = (new HeatmapBuilder())->build($transactions, 25);
        $dataset = self::dataset(10, 'Numeric strings', 1, 7);

        $payload = (new MiningResponseAssembler())->assemble(
            1,
            $dataset,
            1_000_000,
            1_000_000,
            100,
            $apriori,
            $rules,
            $heatmap
        );
        $returned = $payload['heatmap']['items'];

        $allStrings = array_reduce(
            $returned,
            static fn(bool $carry, mixed $item): bool => $carry && is_string($item),
            true
        );
        $assert(
            'Assembler preserves all numeric-looking heatmap items as distinct JSON strings',
            $allStrings
                && $returned === ['+1', '-1', '0', '001', '01', '1', '1.0']
                && count(array_unique($returned, SORT_STRING)) === 7,
            self::describe($returned)
        );
    }

    private static function testZeroGeneratedRatios(callable $assert): void
    {
        $apriori = new AprioriResult(
            1,
            [],
            [],
            [new LevelMetrics(1, 'singleton_scan', 0, 0, 0, 0)],
            0,
            0
        );
        $payload = (new MiningResponseAssembler())->assemble(
            0,
            self::dataset(11, 'Zero generated', 1, 0),
            1,
            0,
            20,
            $apriori,
            new RuleGenerationResult([], 0, 0),
            new HeatmapResult([], [], 1)
        );

        $assert(
            'Zero generated candidate denominators serialize as null, never numeric zero',
            $payload['summary']['pruning_ratio'] === null
                && $payload['levels'][0]['pruning_ratio'] === null,
            self::describe($payload)
        );
    }

    private static function testInvariantFailure(callable $assert): void
    {
        [$dataset, $apriori, $rules, $heatmap] = self::tinyDomain();
        $caught = false;
        try {
            (new MiningResponseAssembler())->assemble(
                0,
                $dataset,
                500_001,
                750_000,
                20,
                $apriori,
                $rules,
                $heatmap
            );
        } catch (RuntimeException) {
            $caught = true;
        }
        $assert('Assembler rejects a result whose required count disagrees with the request', $caught);
    }

    /**
     * @return array{DatasetRecord, AprioriResult, RuleGenerationResult, HeatmapResult}
     */
    private static function tinyDomain(): array
    {
        $transactions = self::tinyTransactions();
        $rawApriori = (new AprioriEngine())->run($transactions, 500_000);
        $apriori = self::withAprioriElapsed($rawApriori, 1_234_567);
        $rawRules = (new AssociationRuleGenerator())->generate($apriori, 4, 750_000);
        $rules = new RuleGenerationResult($rawRules->getRules(), $rawRules->getRulesCount(), 45_678);
        $heatmap = (new HeatmapBuilder())->build($transactions, 20);

        return [self::dataset(9, 'Tiny oracle', 4, 3), $apriori, $rules, $heatmap];
    }

    /** @return list<CanonicalTransaction> */
    private static function tinyTransactions(): array
    {
        $content = file_get_contents(dirname(__DIR__) . '/fixtures/tiny.csv');
        if (!is_string($content)) {
            throw new RuntimeException('tiny.csv could not be read.');
        }

        return (new BasketCsvParser())->parse($content, 'tiny.csv')->getTransactions();
    }

    private static function withAprioriElapsed(AprioriResult $result, int $elapsed): AprioriResult
    {
        return new AprioriResult(
            $result->getRequiredCount(),
            $result->getFrequentItemsets(),
            $result->getSupportMap(),
            $result->getLevels(),
            $result->getMaxK(),
            $elapsed
        );
    }

    private static function dataset(int $id, string $name, int $transactions, int $uniqueItems): DatasetRecord
    {
        return new DatasetRecord(
            $id,
            $name,
            'basket_csv',
            'fixture.csv',
            str_repeat('a', 64),
            15,
            $transactions,
            $uniqueItems,
            '2026-08-12 00:00:00'
        );
    }

    private static function describe(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'not encodable';
    }
}
