<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Mining\AprioriResult;
use App\Mining\RuleGenerationResult;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PDO persistence boundary for successful, complete mining run summaries.
 */
final class ExperimentRunRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Persists a complete run and every reported level as one transaction.
     */
    public function saveCompleted(
        int $datasetId,
        int $minSupportUnits,
        int $minConfidenceUnits,
        AprioriResult $aprioriResult,
        RuleGenerationResult $ruleResult
    ): int {
        if ($datasetId <= 0) {
            throw new InvalidArgumentException('datasetId must be a positive integer.');
        }
        if ($minSupportUnits <= 0 || $minSupportUnits > 1_000_000) {
            throw new InvalidArgumentException('minSupportUnits must be in the range 1..1000000.');
        }
        if ($minConfidenceUnits < 0 || $minConfidenceUnits > 1_000_000) {
            throw new InvalidArgumentException('minConfidenceUnits must be in the range 0..1000000.');
        }

        $transactionStarted = false;

        try {
            $this->pdo->beginTransaction();
            $transactionStarted = true;

            $runStatement = $this->pdo->prepare(
                'INSERT INTO experiment_runs '
                . '(dataset_id, min_support, min_confidence, runtime_ms, rule_generation_runtime_ms, '
                . 'candidates_generated, candidates_pruned, candidates_evaluated, frequent_itemsets, rules_count, max_k) '
                . 'VALUES (:dataset_id, :min_support, :min_confidence, :runtime_ms, :rule_generation_runtime_ms, '
                . ':candidates_generated, :candidates_pruned, :candidates_evaluated, :frequent_itemsets, :rules_count, :max_k)'
            );
            $runStatement->bindValue(':dataset_id', $datasetId, PDO::PARAM_INT);
            $runStatement->bindValue(':min_support', self::millionthsToDecimal($minSupportUnits), PDO::PARAM_STR);
            $runStatement->bindValue(':min_confidence', self::millionthsToDecimal($minConfidenceUnits), PDO::PARAM_STR);
            $runStatement->bindValue(':runtime_ms', self::nanosecondsToMillisecondsDecimal($aprioriResult->getElapsedNanoseconds()), PDO::PARAM_STR);
            $runStatement->bindValue(':rule_generation_runtime_ms', self::nanosecondsToMillisecondsDecimal($ruleResult->getElapsedNanoseconds()), PDO::PARAM_STR);
            $runStatement->bindValue(':candidates_generated', $aprioriResult->getCandidatesGeneratedTotal(), PDO::PARAM_INT);
            $runStatement->bindValue(':candidates_pruned', $aprioriResult->getCandidatesPrunedTotal(), PDO::PARAM_INT);
            $runStatement->bindValue(':candidates_evaluated', $aprioriResult->getCandidatesEvaluatedTotal(), PDO::PARAM_INT);
            $runStatement->bindValue(':frequent_itemsets', $aprioriResult->getFrequentItemsetsTotal(), PDO::PARAM_INT);
            $runStatement->bindValue(':rules_count', $ruleResult->getRulesCount(), PDO::PARAM_INT);
            $runStatement->bindValue(':max_k', $aprioriResult->getMaxK(), PDO::PARAM_INT);
            $runStatement->execute();

            $runId = $this->lastInsertId();

            $levelStatement = $this->pdo->prepare(
                'INSERT INTO `experiment_run_levels` '
                . '(`run_id`, `k`, `source`, `generated`, `pruned`, `evaluated`, `frequent`) '
                . 'VALUES (:run_id, :k, :source, :generated, :pruned, :evaluated, :frequent)'
            );

            foreach ($aprioriResult->getLevels() as $level) {
                $levelStatement->bindValue(':run_id', $runId, PDO::PARAM_INT);
                $levelStatement->bindValue(':k', $level->getK(), PDO::PARAM_INT);
                $levelStatement->bindValue(':source', $level->getSource(), PDO::PARAM_STR);
                $levelStatement->bindValue(':generated', $level->getGenerated(), PDO::PARAM_INT);
                $levelStatement->bindValue(':pruned', $level->getPruned(), PDO::PARAM_INT);
                $levelStatement->bindValue(':evaluated', $level->getEvaluated(), PDO::PARAM_INT);
                $levelStatement->bindValue(':frequent', $level->getFrequent(), PDO::PARAM_INT);
                $levelStatement->execute();
            }

            $this->pdo->commit();

            return $runId;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                $this->rollbackIfActive();
            }
            throw $throwable;
        }
    }

    private static function millionthsToDecimal(int $units): string
    {
        return intdiv($units, 1_000_000)
            . '.'
            . str_pad((string)($units % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private static function nanosecondsToMillisecondsDecimal(int $nanoseconds): string
    {
        if ($nanoseconds < 0) {
            throw new InvalidArgumentException('Runtime nanoseconds must be non-negative.');
        }

        // One thousand nanoseconds is one thousandth of a millisecond.
        $thousandths = intdiv($nanoseconds, 1_000);
        if (($nanoseconds % 1_000) >= 500) {
            $thousandths++;
        }

        return intdiv($thousandths, 1_000)
            . '.'
            . str_pad((string)($thousandths % 1_000), 3, '0', STR_PAD_LEFT);
    }

    private function lastInsertId(): int
    {
        $value = $this->pdo->lastInsertId();
        if ($value === '' || !ctype_digit($value)) {
            throw new RuntimeException('Unable to determine inserted experiment run ID.');
        }

        $id = (int)$value;
        if ($id <= 0) {
            throw new RuntimeException('Inserted experiment run ID must be positive.');
        }

        return $id;
    }

    private function rollbackIfActive(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }

        try {
            $this->pdo->rollBack();
        } catch (Throwable) {
            // Preserve the original write failure for callers.
        }
    }
}
