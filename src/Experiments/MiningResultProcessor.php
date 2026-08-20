<?php

declare(strict_types=1);

namespace App\Experiments;

use InvalidArgumentException;
use RuntimeException;

class MiningResultProcessor
{
    /**
     * Processes raw runs and levels CSV files, validates invariants, and produces summary CSVs.
     *
     * @param string $runsCsvPath Path to raw runs CSV
     * @param string $levelsCsvPath Path to raw levels CSV
     * @param string $outputDir Directory where processed CSVs should be written
     * @param string $prefix Prefix for generated files (e.g. 'mushroom' or 'smoke')
     * @param string|null $datasetName Explicit dataset name (derived from prefix/config if omitted)
     * @return array{
     *     support_summary_file: string,
     *     pruning_summary_file: string,
     *     distinct_supports: list<float>,
     *     total_runs: int,
     *     completed_runs: int
     * }
     */
    public function process(
        string $runsCsvPath,
        string $levelsCsvPath,
        string $outputDir,
        string $prefix = 'mushroom',
        ?string $datasetName = null
    ): array {
        if (!is_file($runsCsvPath) || !is_readable($runsCsvPath)) {
            throw new InvalidArgumentException("Runs CSV not found or unreadable: {$runsCsvPath}");
        }

        if (!is_file($levelsCsvPath) || !is_readable($levelsCsvPath)) {
            throw new InvalidArgumentException("Levels CSV not found or unreadable: {$levelsCsvPath}");
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $runs = self::readCsv($runsCsvPath);
        $levels = self::readCsv($levelsCsvPath);

        if (empty($runs)) {
            throw new RuntimeException("Runs CSV is empty.");
        }

        if ($datasetName !== null && trim($datasetName) !== '') {
            $resolvedDatasetName = trim($datasetName);
        } else {
            $prefixLower = strtolower($prefix);
            if ($prefixLower === 'mushroom') {
                $resolvedDatasetName = 'Mushroom';
            } elseif ($prefixLower === 'smoke' || $prefixLower === 'tiny_test' || $prefixLower === 'tiny') {
                $resolvedDatasetName = 'Tiny';
            } else {
                $resolvedDatasetName = ucfirst($prefix);
            }
        }

        // 1. Group levels by observation_id
        $levelsByObs = [];
        foreach ($levels as $lvl) {
            $obsId = (string)($lvl['observation_id'] ?? '');
            if ($obsId !== '') {
                $levelsByObs[$obsId][] = $lvl;
            }
        }

        // 2. Validate Invariants per Level & reconcile with Runs
        foreach ($levels as $lvl) {
            $obsId = (string)$lvl['observation_id'];
            $k = (int)$lvl['k'];
            $gen = (int)$lvl['generated'];
            $pruned = (int)$lvl['pruned'];
            $eval = (int)$lvl['evaluated'];
            $freq = (int)$lvl['frequent'];
            $source = (string)$lvl['source'];

            if ($gen !== ($pruned + $eval)) {
                throw new RuntimeException("Level invariant violation in {$obsId} (k={$k}): generated ({$gen}) != pruned ({$pruned}) + evaluated ({$eval})");
            }
            if ($freq > $eval) {
                throw new RuntimeException("Level invariant violation in {$obsId} (k={$k}): frequent ({$freq}) > evaluated ({$eval})");
            }
            if ($k === 1 && ($source !== 'singleton_scan' || $pruned !== 0 || $eval !== $gen)) {
                throw new RuntimeException("Level 1 semantic violation in {$obsId}: source='{$source}', pruned={$pruned}, evaluated={$eval}, generated={$gen}");
            }
            if ($k >= 2 && $source !== 'join_prune') {
                throw new RuntimeException("Level {$k} semantic violation in {$obsId}: expected source='join_prune', got '{$source}'");
            }
        }

        // 3. Reconcile level sums with run totals
        foreach ($runs as $run) {
            $obsId = (string)$run['observation_id'];
            $status = (string)$run['mining_status'];

            if ($status === 'COMPLETED') {
                $obsLevels = $levelsByObs[$obsId] ?? [];
                if (empty($obsLevels)) {
                    throw new RuntimeException("Completed run {$obsId} has no corresponding level rows.");
                }

                $sumGen = 0;
                $sumPruned = 0;
                $sumEval = 0;
                $sumFreq = 0;
                $maxKLevel = 0;

                foreach ($obsLevels as $l) {
                    $sumGen += (int)$l['generated'];
                    $sumPruned += (int)$l['pruned'];
                    $sumEval += (int)$l['evaluated'];
                    $sumFreq += (int)$l['frequent'];
                    $maxKLevel = max($maxKLevel, (int)$l['k']);
                }

                $runGen = (int)$run['candidates_generated'];
                $runPruned = (int)$run['candidates_pruned'];
                $runEval = (int)$run['candidates_evaluated'];
                $runFreq = (int)$run['frequent_itemsets'];
                $runMaxK = (int)$run['max_k'];

                if ($sumGen !== $runGen) {
                    throw new RuntimeException("Reconciliation failure in {$obsId}: level sum generated ({$sumGen}) != run candidates_generated ({$runGen})");
                }
                if ($sumPruned !== $runPruned) {
                    throw new RuntimeException("Reconciliation failure in {$obsId}: level sum pruned ({$sumPruned}) != run candidates_pruned ({$runPruned})");
                }
                if ($sumEval !== $runEval) {
                    throw new RuntimeException("Reconciliation failure in {$obsId}: level sum evaluated ({$sumEval}) != run candidates_evaluated ({$runEval})");
                }
                if ($sumFreq !== $runFreq) {
                    throw new RuntimeException("Reconciliation failure in {$obsId}: level sum frequent ({$sumFreq}) != run frequent_itemsets ({$runFreq})");
                }
            }
        }

        // 4. Group runs by min_support
        $runsBySupport = [];
        foreach ($runs as $run) {
            $sup = (float)$run['min_support'];
            $supKey = sprintf('%.6f', $sup);
            $runsBySupport[$supKey][] = $run;
        }

        // Sort support levels descending
        uksort($runsBySupport, function ($a, $b) {
            return (float)$b <=> (float)$a;
        });

        // 5. Check Deterministic Count Consistency & compute aggregates
        $supportSummaryRows = [];
        $pruningSummaryRows = [];
        $completedTotal = 0;
        $prevFrequent = -1;

        foreach ($runsBySupport as $supKey => $supportRuns) {
            $supFloat = (float)$supKey;
            $minConfidence = isset($supportRuns[0]['min_confidence']) && is_numeric($supportRuns[0]['min_confidence'])
                ? (float)$supportRuns[0]['min_confidence']
                : 0.0;
            $totalCount = count($supportRuns);
            $completedRuns = array_values(array_filter($supportRuns, fn($r) => ($r['mining_status'] ?? '') === 'COMPLETED'));
            $completedCount = count($completedRuns);
            $completedTotal += $completedCount;

            if ($completedCount === 0) {
                // All runs failed or exceeded limit
                $supportSummaryRows[] = [
                    'dataset_name' => $resolvedDatasetName,
                    'min_support' => $supFloat,
                    'min_confidence' => $minConfidence,
                    'n_repeats' => $totalCount,
                    'n_valid' => 0,
                    'median_runtime_ms' => '',
                    'iqr_runtime_ms' => '',
                    'median_rule_runtime_ms' => '',
                    'iqr_rule_runtime_ms' => '',
                    'candidates_generated' => '',
                    'candidates_pruned' => '',
                    'candidates_evaluated' => '',
                    'frequent_itemsets' => '',
                    'rules_count' => '',
                    'max_k' => '',
                    'pruning_ratio' => '',
                ];
                continue;
            }

            // Assert Deterministic Counts across completed repeats
            $ref = $completedRuns[0];
            $refGen = (int)$ref['candidates_generated'];
            $refPruned = (int)$ref['candidates_pruned'];
            $refEval = (int)$ref['candidates_evaluated'];
            $refFreq = (int)$ref['frequent_itemsets'];
            $refRules = ($ref['rule_status'] === 'COMPLETED' && $ref['rules_count'] !== '') ? (int)$ref['rules_count'] : null;
            $refMaxK = (int)$ref['max_k'];

            foreach ($completedRuns as $r) {
                if ((int)$r['candidates_generated'] !== $refGen ||
                    (int)$r['candidates_pruned'] !== $refPruned ||
                    (int)$r['candidates_evaluated'] !== $refEval ||
                    (int)$r['frequent_itemsets'] !== $refFreq ||
                    (int)$r['max_k'] !== $refMaxK) {
                    throw new RuntimeException("Non-deterministic mining counts detected across repeats at min_support {$supFloat}");
                }
                if ($refRules !== null && $r['rule_status'] === 'COMPLETED' && (int)$r['rules_count'] !== $refRules) {
                    throw new RuntimeException("Non-deterministic rule counts detected across repeats at min_support {$supFloat}");
                }
            }

            // Monotonicity check across descending support thresholds
            if ($prevFrequent !== -1 && $refFreq < $prevFrequent) {
                throw new RuntimeException("Monotonicity violation: min_support {$supFloat} produced fewer frequent itemsets ({$refFreq}) than higher support threshold ({$prevFrequent})");
            }
            $prevFrequent = $refFreq;

            // Extract timing arrays
            $miningRuntimes = array_map(fn($r) => (float)$r['runtime_ms'], $completedRuns);
            $ruleRuntimes = [];
            foreach ($completedRuns as $r) {
                if ($r['rule_status'] === 'COMPLETED' && $r['rule_generation_runtime_ms'] !== '') {
                    $ruleRuntimes[] = (float)$r['rule_generation_runtime_ms'];
                }
            }

            $medMining = self::calculateMedian($miningRuntimes);
            $iqrMining = self::calculateIqr($miningRuntimes);
            $medRule = !empty($ruleRuntimes) ? self::calculateMedian($ruleRuntimes) : null;
            $iqrRule = !empty($ruleRuntimes) ? self::calculateIqr($ruleRuntimes) : null;

            $ratio = $refGen > 0 ? round($refPruned / (float)$refGen, 6) : 0.0;

            $supportSummaryRows[] = [
                'dataset_name' => $resolvedDatasetName,
                'min_support' => $supFloat,
                'min_confidence' => $minConfidence,
                'n_repeats' => $totalCount,
                'n_valid' => $completedCount,
                'median_runtime_ms' => sprintf('%.3f', $medMining),
                'iqr_runtime_ms' => sprintf('%.3f', $iqrMining),
                'median_rule_runtime_ms' => $medRule !== null ? sprintf('%.3f', $medRule) : '',
                'iqr_rule_runtime_ms' => $iqrRule !== null ? sprintf('%.3f', $iqrRule) : '',
                'candidates_generated' => $refGen,
                'candidates_pruned' => $refPruned,
                'candidates_evaluated' => $refEval,
                'frequent_itemsets' => $refFreq,
                'rules_count' => $refRules ?? '',
                'max_k' => $refMaxK,
                'pruning_ratio' => sprintf('%.6f', $ratio),
            ];

            // Build deterministic per-level summary from first completed repeat
            $refObsId = (string)$ref['observation_id'];
            $refLevels = $levelsByObs[$refObsId] ?? [];
            foreach ($refLevels as $lvl) {
                $pruningSummaryRows[] = [
                    'dataset_name' => $resolvedDatasetName,
                    'min_support' => $supFloat,
                    'k' => (int)$lvl['k'],
                    'source' => (string)$lvl['source'],
                    'generated' => (int)$lvl['generated'],
                    'pruned' => (int)$lvl['pruned'],
                    'evaluated' => (int)$lvl['evaluated'],
                    'frequent' => (int)$lvl['frequent'],
                    'pruning_ratio' => (string)$lvl['pruning_ratio'],
                ];
            }
        }

        // 6. Write Processed CSVs
        $supportSummaryFile = $outputDir . '/' . $prefix . '_support_summary.csv';
        $pruningSummaryFile = $outputDir . '/' . $prefix . '_pruning_summary.csv';

        MiningExperimentRunner::writeCsv($supportSummaryFile, [
            'dataset_name',
            'min_support',
            'min_confidence',
            'n_repeats',
            'n_valid',
            'median_runtime_ms',
            'iqr_runtime_ms',
            'median_rule_runtime_ms',
            'iqr_rule_runtime_ms',
            'candidates_generated',
            'candidates_pruned',
            'candidates_evaluated',
            'frequent_itemsets',
            'rules_count',
            'max_k',
            'pruning_ratio',
        ], $supportSummaryRows);

        MiningExperimentRunner::writeCsv($pruningSummaryFile, [
            'dataset_name',
            'min_support',
            'k',
            'source',
            'generated',
            'pruned',
            'evaluated',
            'frequent',
            'pruning_ratio',
        ], $pruningSummaryRows);

        return [
            'support_summary_file' => $supportSummaryFile,
            'pruning_summary_file' => $pruningSummaryFile,
            'distinct_supports' => array_values(array_map('floatval', array_keys($runsBySupport))),
            'total_runs' => count($runs),
            'completed_runs' => $completedTotal,
        ];
    }

    /**
     * @param string $csvPath
     * @return list<array<string, string>>
     */
    public static function readCsv(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV file: {$csvPath}");
        }

        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            return [];
        }

        $headers = array_map('trim', $headers);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) === 1 && $data[0] === null) {
                continue;
            }
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = isset($data[$idx]) ? trim((string)$data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Calculates the sample median of an array of floats.
     *
     * @param list<float> $values
     * @return float
     */
    public static function calculateMedian(array $values): float
    {
        if (empty($values)) {
            throw new InvalidArgumentException("Cannot calculate median of empty array.");
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$mid];
        }

        return ($values[$mid - 1] + $values[$mid]) / 2.0;
    }

    /**
     * Calculates the sample Interquartile Range (IQR = Q3 - Q1) using standard median of halves.
     *
     * @param list<float> $values
     * @return float
     */
    public static function calculateIqr(array $values): float
    {
        if (empty($values)) {
            throw new InvalidArgumentException("Cannot calculate IQR of empty array.");
        }

        $count = count($values);
        if ($count === 1) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);

        if ($count % 2 === 0) {
            $lowerHalf = array_slice($values, 0, intdiv($count, 2));
            $upperHalf = array_slice($values, intdiv($count, 2));
        } else {
            $mid = intdiv($count, 2);
            $lowerHalf = array_slice($values, 0, $mid);
            $upperHalf = array_slice($values, $mid + 1);
        }

        $q1 = self::calculateMedian($lowerHalf);
        $q3 = self::calculateMedian($upperHalf);

        return max(0.0, $q3 - $q1);
    }
}
