<?php

declare(strict_types=1);

namespace App\Http;

use App\Mining\AprioriEngine;
use App\Mining\AssociationRuleGenerator;
use App\Mining\HeatmapBuilder;
use App\Mining\MiningLimitExceededException;
use App\Persistence\DatasetRepository;
use App\Persistence\ExperimentRunRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Orchestrates one synchronous mining request over a persisted dataset.
 */
final class MiningController
{
    private const MAX_CANDIDATES = 250_000;
    private const MAX_DEADLINE_SECONDS = 30;
    private const MAX_RULES = 50_000;

    public function __construct(
        private readonly RequestValidator $validator,
        private readonly DatasetRepository $datasets,
        private readonly AprioriEngine $apriori,
        private readonly AssociationRuleGenerator $rules,
        private readonly HeatmapBuilder $heatmaps,
        private readonly ExperimentRunRepository $runs,
        private readonly MiningResponseAssembler $assembler,
        private readonly JsonResponder $responder,
        private readonly int $candidateLimit,
        private readonly int $deadlineSeconds,
        private readonly int $ruleLimit
    ) {
        self::assertLimit($this->candidateLimit, self::MAX_CANDIDATES, 'candidateLimit');
        self::assertLimit($this->deadlineSeconds, self::MAX_DEADLINE_SECONDS, 'deadlineSeconds');
        self::assertLimit($this->ruleLimit, self::MAX_RULES, 'ruleLimit');
    }

    /**
     * @param array<string, mixed> $query
     */
    public function handle(
        string $method,
        ?string $contentType,
        array $query,
        string $rawBody
    ): ApiResponse {
        try {
            $this->validator->assertMethod($method, ['POST']);
            $this->validator->assertNoQueryParameters($query);
            $this->validator->assertContentType($contentType, 'application/json');
            $request = $this->validator->validateMiningJson($rawBody);

            $dataset = $this->datasets->findById($request['dataset_id']);
            if ($dataset === null) {
                return ApiResponse::error(404, 'DATASET_NOT_FOUND', 'Dataset not found.');
            }

            $transactions = $this->datasets->loadTransactions($dataset->getId());
            $aprioriResult = $this->apriori->run(
                $transactions,
                $request['support_units'],
                $this->candidateLimit,
                (float)$this->deadlineSeconds
            );
            $ruleResult = $this->rules->generate(
                $aprioriResult,
                $dataset->getTransactionCount(),
                $request['confidence_units'],
                $this->ruleLimit
            );
            $heatmapResult = $this->heatmaps->build(
                $transactions,
                min($request['top_n'], 25)
            );

            $payload = $this->assembler->assemble(
                0,
                $dataset,
                $request['support_units'],
                $request['confidence_units'],
                $request['top_n'],
                $aprioriResult,
                $ruleResult,
                $heatmapResult
            );

            // No completed run may survive an unexpectedly non-serializable response.
            $this->responder->assertEncodable($payload);

            $runId = $this->runs->saveCompleted(
                $dataset->getId(),
                $request['support_units'],
                $request['confidence_units'],
                $aprioriResult,
                $ruleResult
            );
            $payload['run_id'] = $runId;
            $this->responder->assertEncodable($payload);

            return ApiResponse::success(200, $payload);
        } catch (ApiException $exception) {
            return ApiResponse::error(
                $exception->getStatus(),
                $exception->getApiCode(),
                $exception->getMessage(),
                $exception->getDetails(),
                $exception->getHeaders()
            );
        } catch (MiningLimitExceededException) {
            return ApiResponse::error(
                503,
                'MINING_LIMIT_EXCEEDED',
                'Mining computation exceeded a configured limit.'
            );
        } catch (Throwable) {
            return ApiResponse::error(500, 'INTERNAL_ERROR', 'An internal server error occurred.');
        }
    }

    private static function assertLimit(int $value, int $maximum, string $name): void
    {
        if ($value <= 0 || $value > $maximum) {
            throw new InvalidArgumentException("{$name} must be between 1 and {$maximum}.");
        }
    }
}
