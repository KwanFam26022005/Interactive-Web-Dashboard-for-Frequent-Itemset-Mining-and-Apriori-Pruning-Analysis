<?php

declare(strict_types=1);

namespace App\Experiments;

use App\Dataset\CanonicalTransaction;
use App\Dataset\ParserRegistry;
use App\Mining\AprioriEngine;
use App\Mining\AprioriResult;
use App\Mining\AssociationRuleGenerator;
use App\Mining\MiningLimitExceededException;
use App\Mining\SupportThreshold;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MiningExperimentRunner
{
    private ParserRegistry $parserRegistry;
    private AprioriEngine $aprioriEngine;
    private AssociationRuleGenerator $ruleGenerator;

    public function __construct(
        ?ParserRegistry $parserRegistry = null,
        ?AprioriEngine $aprioriEngine = null,
        ?AssociationRuleGenerator $ruleGenerator = null
    ) {
        $this->parserRegistry = $parserRegistry ?? new ParserRegistry();
        $this->aprioriEngine = $aprioriEngine ?? new AprioriEngine();
        $this->ruleGenerator = $ruleGenerator ?? new AssociationRuleGenerator();
    }

    /**
     * Runs the experiment matrix according to mode, configuration, and safety rules.
     *
     * @param array{
     *     mode: string,
     *     dataset_path: string,
     *     config_path: string,
     *     manifest_dir?: string,
     *     profile?: string,
     *     output_dir?: string,
     *     prefix?: string,
     *     dry_run?: bool
     * } $options
     * @return array{
     *     mode: string,
     *     runs_count: int,
     *     levels_count: int,
     *     runs_file: string|null,
     *     levels_file: string|null,
     *     summary_stats: array<string, mixed>
     * }
     */
    public function execute(array $options): array
    {
        $mode = strtolower($options['mode'] ?? 'smoke');
        if (!in_array($mode, ['smoke', 'probe', 'formal'], true)) {
            throw new InvalidArgumentException("Invalid execution mode '{$mode}'. Must be 'smoke', 'probe', or 'formal'.");
        }

        $datasetPath = $options['dataset_path'] ?? '';
        if (!is_file($datasetPath) || !is_readable($datasetPath)) {
            throw new InvalidArgumentException("Dataset file not found or unreadable: '{$datasetPath}'");
        }

        $configPath = $options['config_path'] ?? '';
        if (!is_file($configPath) || !is_readable($configPath)) {
            throw new InvalidArgumentException("Config file not found or unreadable: '{$configPath}'");
        }

        $repoRoot = dirname(__DIR__, 2);
        $manifestDir = $options['manifest_dir'] ?? ($repoRoot . '/experiments/configs');
        $outputDir = $options['output_dir'] ?? ($mode === 'formal' ? $repoRoot . '/experiments/raw' : $repoRoot . '/experiments/generated');
        $prefix = $options['prefix'] ?? ($mode === 'smoke' ? 'smoke' : ($mode === 'probe' ? 'probe' : 'mushroom'));
        $dryRun = (bool)($options['dry_run'] ?? false);

        // 1. Output Directory Protection Policy
        if ($mode !== 'formal') {
            $rawDirNormalized = realpath($repoRoot . '/experiments/raw');
            $targetDirNormalized = realpath($outputDir) ?: $outputDir;
            if ($rawDirNormalized !== false && str_starts_with($targetDirNormalized, $rawDirNormalized)) {
                throw new RuntimeException("SAFETY VIOLATION: Mode '{$mode}' cannot output to formal raw directory '{$outputDir}'.");
            }
        }

        // 2. Load and validate experiment config
        $configErrors = ConfigValidator::validateExperimentConfig($configPath);
        if (!empty($configErrors)) {
            throw new RuntimeException("Invalid experiment configuration:\n - " . implode("\n - ", $configErrors));
        }
        $config = json_decode((string)file_get_contents($configPath), true);
        $profile = $options['profile'] ?? ($config['ingestion_profile'] ?? 'mushroom');

        // 3. Formal Mode Safety Gates
        $gitSha = LineageHelper::getGitHeadSha($repoRoot);
        $configSha = LineageHelper::hashFile($configPath) ?? 'UNAVAILABLE';
        $datasetSha = LineageHelper::hashFile($datasetPath) ?? 'UNAVAILABLE';
        $envManifestPath = $manifestDir . '/environment_manifest.json';
        $envSha = LineageHelper::hashFile($envManifestPath) ?? 'UNAVAILABLE';
        $skipWorktreeCheck = (bool)($options['skip_worktree_check'] ?? false);

        if ($mode === 'formal') {
            self::enforceFormalSafetyGates($repoRoot, $manifestDir, $datasetPath, $datasetSha, $gitSha, $envManifestPath, $configSha, $skipWorktreeCheck);
        }

        // 4. Ingest and parse dataset
        $parser = $this->parserRegistry->getParser($profile);
        $content = (string)file_get_contents($datasetPath);
        $parseResult = $parser->parse($content, basename($datasetPath));
        $transactions = $parseResult->getTransactions();
        $n = count($transactions);
        if ($n === 0) {
            throw new RuntimeException("Parsed dataset contains 0 transactions.");
        }

        $minSupportList = $config['min_support'];
        $minConfidence = (float)$config['min_confidence'];
        $confidenceUnits = (int)round($minConfidence * 1_000_000);
        $warmupIterations = (int)($config['warmup_iterations'] ?? 0);
        $formalRepetitions = (int)($config['formal_repetitions'] ?? 1);
        $timeoutSeconds = (float)($config['guards']['timeout_seconds'] ?? 30.0);
        $maxCandidates = (int)($config['guards']['max_candidates'] ?? 250000);
        $maxRules = (int)($config['guards']['max_rules'] ?? 50000);

        if ($dryRun) {
            return [
                'mode' => $mode,
                'runs_count' => 0,
                'levels_count' => 0,
                'runs_file' => null,
                'levels_file' => null,
                'summary_stats' => [
                    'dry_run' => true,
                    'transactions_count' => $n,
                    'profile' => $profile,
                    'support_count' => count($minSupportList),
                    'warmup_iterations' => $warmupIterations,
                    'formal_repetitions' => $formalRepetitions,
                    'git_sha' => $gitSha,
                    'config_sha' => $configSha,
                    'dataset_sha' => $datasetSha,
                ],
            ];
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // 5. Warmup Phase (Discarded timing/output)
        if ($warmupIterations > 0) {
            foreach ($minSupportList as $sup) {
                $supUnits = (int)round((float)$sup * 1_000_000);
                for ($w = 0; $w < $warmupIterations; $w++) {
                    try {
                        $aprioriRes = $this->aprioriEngine->run($transactions, $supUnits, $maxCandidates, $timeoutSeconds);
                        $this->ruleGenerator->generate($aprioriRes, $n, $confidenceUnits, $maxRules);
                    } catch (Throwable) {
                        // Discard warmup failures
                    }
                }
            }
        }

        // 6. Build execution schedule
        $schedule = [];
        foreach ($minSupportList as $sup) {
            $supFloat = (float)$sup;
            for ($r = 1; $r <= $formalRepetitions; $r++) {
                $schedule[] = [
                    'support' => $supFloat,
                    'repeat_index' => $r,
                ];
            }
        }

        // Deterministic shuffle if configured
        if (($config['run_order']['strategy'] ?? '') === 'deterministic_shuffle') {
            $seed = (int)($config['run_order']['seed'] ?? 42);
            self::deterministicShuffle($schedule, $seed);
        }

        // 7. Measurement Execution Loop
        $runsRows = [];
        $levelsRows = [];
        $obsCounter = 1;

        foreach ($schedule as $job) {
            $supFloat = $job['support'];
            $repeatIndex = $job['repeat_index'];
            $supUnits = (int)round($supFloat * 1_000_000);

            $obsId = sprintf('OBS-%05d', $obsCounter++);

            $miningStatus = 'PENDING';
            $ruleStatus = 'PENDING';
            $runtimeMs = null;
            $ruleRuntimeMs = null;
            $failureStage = null;
            $failureCode = null;
            $failureElapsedMs = null;
            $candidatesGenerated = null;
            $candidatesPruned = null;
            $candidatesEvaluated = null;
            $frequentItemsets = null;
            $rulesCount = null;
            $maxK = null;

            $aprioriResult = null;
            $startTimer = hrtime(true);

            // Phase 1: Apriori Core
            try {
                $aprioriResult = $this->aprioriEngine->run(
                    $transactions,
                    $supUnits,
                    $maxCandidates,
                    $timeoutSeconds
                );
                $miningStatus = 'COMPLETED';
                $runtimeMs = round($aprioriResult->getElapsedNanoseconds() / 1_000_000.0, 3);
                $candidatesGenerated = $aprioriResult->getCandidatesGeneratedTotal();
                $candidatesPruned = $aprioriResult->getCandidatesPrunedTotal();
                $candidatesEvaluated = $aprioriResult->getCandidatesEvaluatedTotal();
                $frequentItemsets = $aprioriResult->getFrequentItemsetsTotal();
                $maxK = $aprioriResult->getMaxK();

                // Record Level Observations
                foreach ($aprioriResult->getLevels() as $level) {
                    $k = $level->getK();
                    $kGen = $level->getGenerated();
                    $kPruned = $level->getPruned();
                    $kEval = $level->getEvaluated();
                    $kFreq = $level->getFrequent();

                    // Assert Invariants
                    if ($kGen !== ($kPruned + $kEval)) {
                        throw new RuntimeException("Invariant violation at Level k={$k}: generated ({$kGen}) != pruned ({$kPruned}) + evaluated ({$kEval})");
                    }
                    if ($kFreq > $kEval) {
                        throw new RuntimeException("Invariant violation at Level k={$k}: frequent ({$kFreq}) > evaluated ({$kEval})");
                    }

                    $kRatio = $kGen > 0 ? round($kPruned / $kGen, 6) : null;
                    $source = ($k === 1) ? 'singleton_scan' : 'join_prune';

                    $levelsRows[] = [
                        'observation_id' => $obsId,
                        'git_revision' => $gitSha ?? 'UNKNOWN',
                        'min_support' => $supFloat,
                        'repeat_index' => $repeatIndex,
                        'k' => $k,
                        'source' => $source,
                        'generated' => $kGen,
                        'pruned' => $kPruned,
                        'evaluated' => $kEval,
                        'frequent' => $kFreq,
                        'pruning_ratio' => $kRatio !== null ? sprintf('%.6f', $kRatio) : '',
                    ];
                }

            } catch (MiningLimitExceededException $e) {
                $miningStatus = 'MINING_LIMIT_EXCEEDED';
                $failureStage = 'APRIORI_CORE';
                $failureCode = $e->getCode() ?: 'LIMIT_EXCEEDED';
                $failureElapsedMs = round((hrtime(true) - $startTimer) / 1_000_000.0, 3);
            } catch (Throwable $e) {
                $miningStatus = 'FAILED';
                $failureStage = 'APRIORI_CORE';
                $failureCode = 'RUNTIME_ERROR: ' . $e->getMessage();
                $failureElapsedMs = round((hrtime(true) - $startTimer) / 1_000_000.0, 3);
            }

            // Phase 2: Association Rules (only if Apriori completed)
            if ($miningStatus === 'COMPLETED' && $aprioriResult instanceof AprioriResult) {
                $ruleTimer = hrtime(true);
                try {
                    $ruleRes = $this->ruleGenerator->generate(
                        $aprioriResult,
                        $n,
                        $confidenceUnits,
                        $maxRules
                    );
                    $ruleStatus = 'COMPLETED';
                    $ruleRuntimeMs = round($ruleRes->getElapsedNanoseconds() / 1_000_000.0, 3);
                    $rulesCount = $ruleRes->getRulesCount();
                } catch (MiningLimitExceededException $e) {
                    $ruleStatus = 'RULE_LIMIT_EXCEEDED';
                    $failureStage = 'RULE_GENERATION';
                    $failureCode = $e->getCode() ?: 'RULE_LIMIT_EXCEEDED';
                    $failureElapsedMs = round((hrtime(true) - $ruleTimer) / 1_000_000.0, 3);
                } catch (Throwable $e) {
                    $ruleStatus = 'FAILED';
                    $failureStage = 'RULE_GENERATION';
                    $failureCode = 'RULE_ERROR';
                    $failureElapsedMs = round((hrtime(true) - $ruleTimer) / 1_000_000.0, 3);
                }
            } else {
                $ruleStatus = 'SKIPPED';
            }

            $runsRows[] = [
                'observation_id' => $obsId,
                'git_revision' => $gitSha ?? 'UNKNOWN',
                'experiment_config_sha256' => $configSha,
                'dataset_sha256' => $datasetSha,
                'environment_manifest_sha256' => $envSha,
                'repeat_index' => $repeatIndex,
                'min_support' => $supFloat,
                'min_confidence' => $minConfidence,
                'mining_status' => $miningStatus,
                'rule_status' => $ruleStatus,
                'runtime_ms' => $runtimeMs !== null ? sprintf('%.3f', $runtimeMs) : '',
                'rule_generation_runtime_ms' => $ruleRuntimeMs !== null ? sprintf('%.3f', $ruleRuntimeMs) : '',
                'failure_stage' => $failureStage ?? '',
                'failure_code' => $failureCode ?? '',
                'failure_elapsed_ms' => $failureElapsedMs !== null ? sprintf('%.3f', $failureElapsedMs) : '',
                'candidates_generated' => $candidatesGenerated !== null ? (string)$candidatesGenerated : '',
                'candidates_pruned' => $candidatesPruned !== null ? (string)$candidatesPruned : '',
                'candidates_evaluated' => $candidatesEvaluated !== null ? (string)$candidatesEvaluated : '',
                'frequent_itemsets' => $frequentItemsets !== null ? (string)$frequentItemsets : '',
                'rules_count' => $rulesCount !== null ? (string)$rulesCount : '',
                'max_k' => $maxK !== null ? (string)$maxK : '',
            ];
        }

        // 8. Write Raw CSV Files
        $runsFile = $outputDir . '/' . $prefix . '_support_runs.csv';
        $levelsFile = $outputDir . '/' . $prefix . '_pruning_levels.csv';

        self::writeCsv($runsFile, [
            'observation_id',
            'git_revision',
            'experiment_config_sha256',
            'dataset_sha256',
            'environment_manifest_sha256',
            'repeat_index',
            'min_support',
            'min_confidence',
            'mining_status',
            'rule_status',
            'runtime_ms',
            'rule_generation_runtime_ms',
            'failure_stage',
            'failure_code',
            'failure_elapsed_ms',
            'candidates_generated',
            'candidates_pruned',
            'candidates_evaluated',
            'frequent_itemsets',
            'rules_count',
            'max_k',
        ], $runsRows);

        self::writeCsv($levelsFile, [
            'observation_id',
            'git_revision',
            'min_support',
            'repeat_index',
            'k',
            'source',
            'generated',
            'pruned',
            'evaluated',
            'frequent',
            'pruning_ratio',
        ], $levelsRows);

        return [
            'mode' => $mode,
            'runs_count' => count($runsRows),
            'levels_count' => count($levelsRows),
            'runs_file' => $runsFile,
            'levels_file' => $levelsFile,
            'summary_stats' => [
                'transactions_count' => $n,
                'observations_count' => count($runsRows),
                'levels_count' => count($levelsRows),
                'git_sha' => $gitSha,
                'config_sha' => $configSha,
                'dataset_sha' => $datasetSha,
            ],
        ];
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, string|int|float>> $rows
     */
    public static function writeCsv(string $filePath, array $headers, array $rows): void
    {
        $handle = @fopen($filePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Could not open file for writing: {$filePath}");
        }

        // Header
        fwrite($handle, implode(',', $headers) . "\n");

        // Rows
        foreach ($rows as $row) {
            $lineValues = [];
            foreach ($headers as $col) {
                $val = $row[$col] ?? '';
                $valStr = (string)$val;
                // Quote if contains comma, quote, or newline
                if (str_contains($valStr, ',') || str_contains($valStr, '"') || str_contains($valStr, "\n")) {
                    $valStr = '"' . str_replace('"', '""', $valStr) . '"';
                }
                $lineValues[] = $valStr;
            }
            fwrite($handle, implode(',', $lineValues) . "\n");
        }

        fclose($handle);
    }

    /**
     * Deterministic Fisher-Yates shuffle using an explicit linear congruential generator (LCG) seed.
     *
     * @param array<int, mixed> $array
     */
    private static function deterministicShuffle(array &$array, int $seed): void
    {
        $count = count($array);
        if ($count <= 1) {
            return;
        }

        // 32-bit LCG parameters (Numerical Recipes standard: m = 2^32, a = 1664525, c = 1013904223)
        $state = $seed & 0xFFFFFFFF;

        for ($i = $count - 1; $i > 0; $i--) {
            $state = (1664525 * $state + 1013904223) & 0xFFFFFFFF;
            $j = $state % ($i + 1);

            $tmp = $array[$i];
            $array[$i] = $array[$j];
            $array[$j] = $tmp;
        }
    }

    public static function enforceFormalSafetyGates(
        string $repoRoot,
        string $manifestDir,
        string $datasetPath,
        string $datasetSha,
        ?string $gitSha,
        string $envManifestPath,
        string $configSha,
        bool $skipWorktreeCheck = false
    ): void {
        // Gate 1: Git worktree must be clean
        if (!$skipWorktreeCheck && !LineageHelper::isGitWorktreeClean($repoRoot)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Git worktree is dirty. Commit or stash all changes before running formal experiments.");
        }

        // Gate 2: Full 40-character Git SHA required
        if (!$gitSha || strlen($gitSha) !== 40) {
            throw new RuntimeException("FORMAL GATE FAILURE: Full 40-character Git commit SHA could not be determined.");
        }

        // Gate 3: Dataset manifest must be VERIFIED_FROZEN and SHA must match
        $manifestPath = $manifestDir . '/dataset_manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Dataset manifest missing: {$manifestPath}");
        }
        $manifestData = json_decode((string)file_get_contents($manifestPath), true);
        $datasetFound = false;
        foreach ($manifestData['datasets'] ?? [] as $ds) {
            if (($ds['canonical_name'] ?? '') === 'Mushroom') {
                $datasetFound = true;
                if (($ds['status'] ?? '') !== 'VERIFIED_FROZEN') {
                    throw new RuntimeException("FORMAL GATE FAILURE: Mushroom dataset status is '{$ds['status']}'. Formal execution requires 'VERIFIED_FROZEN'.");
                }
                if (strtolower((string)$ds['raw_sha256']) !== strtolower($datasetSha)) {
                    throw new RuntimeException("FORMAL GATE FAILURE: Dataset SHA-256 mismatch. Manifest: {$ds['raw_sha256']}, Actual: {$datasetSha}");
                }
            }
        }
        if (!$datasetFound) {
            throw new RuntimeException("FORMAL GATE FAILURE: Mushroom dataset not registered in dataset manifest.");
        }

        // Gate 4: Environment manifest must be MEASURED and have matching provenance hashes
        if (!is_file($envManifestPath)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest missing: {$envManifestPath}");
        }
        $envData = json_decode((string)file_get_contents($envManifestPath), true);
        if (($envData['status'] ?? '') !== 'MEASURED') {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest status is '{$envData['status']}'. Formal execution requires 'MEASURED'.");
        }

        $recordedConfigSha = (string)($envData['provenance_hashes']['experiment_config_sha256'] ?? '');
        $recordedDatasetSha = (string)($envData['provenance_hashes']['dataset_sha256'] ?? '');

        if (!preg_match('/^[0-9a-f]{64}$/i', $recordedConfigSha)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest experiment_config_sha256 is invalid or placeholder: '{$recordedConfigSha}'");
        }
        if (!preg_match('/^[0-9a-f]{64}$/i', $recordedDatasetSha)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest dataset_sha256 is invalid or placeholder: '{$recordedDatasetSha}'");
        }

        if (strtolower($recordedConfigSha) !== strtolower($configSha)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest config SHA mismatch. Manifest: {$recordedConfigSha}, Actual: {$configSha}");
        }
        if (strtolower($recordedDatasetSha) !== strtolower($datasetSha)) {
            throw new RuntimeException("FORMAL GATE FAILURE: Environment manifest dataset SHA mismatch. Manifest: {$recordedDatasetSha}, Actual: {$datasetSha}");
        }
    }
}
