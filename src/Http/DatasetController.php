<?php

declare(strict_types=1);

namespace App\Http;

use App\Dataset\DatasetImportResult;
use App\Dataset\DatasetImportService;
use App\Dataset\DatasetValidationException;
use App\Dataset\ParserIssue;
use App\Persistence\DatasetRecord;
use App\Persistence\DatasetRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Orchestrates dataset list, detail, and import requests.
 */
final class DatasetController
{
    private const ALLOWED_METHODS = ['GET', 'POST'];

    public function __construct(
        private readonly RequestValidator $validator,
        private readonly DatasetRepository $datasets,
        private readonly DatasetImportService $imports
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function handle(
        string $method,
        ?string $contentType,
        array $query,
        array $post,
        array $files,
        ?string $contentLength = null
    ): ApiResponse {
        try {
            $this->validator->assertMethod($method, self::ALLOWED_METHODS);

            if ($method === 'GET') {
                return $this->handleGet($query);
            }

            return $this->handlePost($contentType, $contentLength, $query, $post, $files);
        } catch (DatasetValidationException $exception) {
            return $this->datasetValidationError($exception);
        } catch (ApiException $exception) {
            return ApiResponse::error(
                $exception->getStatus(),
                $exception->getApiCode(),
                $exception->getMessage(),
                $exception->getDetails(),
                $exception->getHeaders()
            );
        } catch (Throwable) {
            return self::internalError();
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function handleGet(array $query): ApiResponse
    {
        $id = $this->validator->validateDatasetQuery($query);

        if ($id === null) {
            $serialized = [];
            foreach ($this->datasets->listNewestFirst() as $dataset) {
                $serialized[] = self::serializeDataset($dataset);
            }

            return ApiResponse::success(200, ['datasets' => $serialized]);
        }

        $dataset = $this->datasets->findById($id);
        if ($dataset === null) {
            return ApiResponse::error(404, 'DATASET_NOT_FOUND', 'Dataset not found.');
        }

        return ApiResponse::success(200, ['dataset' => self::serializeDataset($dataset)]);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    private function handlePost(
        ?string $contentType,
        ?string $contentLength,
        array $query,
        array $post,
        array $files
    ): ApiResponse
    {
        $this->validator->assertNoQueryParameters($query);
        $this->validator->assertContentType($contentType, 'multipart/form-data');
        $this->validator->assertPhpPostBodyAccepted($contentLength);
        $upload = $this->validator->validateDatasetImport($post, $files);

        $content = self::readUploadedContent($upload['tmp_name']);
        $result = $this->imports->import(
            $content,
            $upload['source_filename'],
            $upload['format'],
            $upload['name']
        );

        return ApiResponse::success(201, self::serializeImportResult($result));
    }

    private static function readUploadedContent(string $temporaryPath): string
    {
        $content = @file_get_contents($temporaryPath);
        if ($content === false) {
            throw new ApiException(400, 'UPLOAD_FAILED', 'Uploaded file could not be read.');
        }

        return $content;
    }

    /**
     * @return array{
     *   dataset: array<string, int|string>,
     *   warnings: list<array{code: string, line: int, message: string}>,
     *   total_warnings: int
     * }
     */
    private static function serializeImportResult(DatasetImportResult $result): array
    {
        $warnings = [];
        foreach ($result->getWarnings() as $warning) {
            $warnings[] = self::serializeIssue($warning);
        }

        return [
            'dataset' => self::serializeDataset($result->getDataset()),
            'warnings' => $warnings,
            'total_warnings' => $result->getTotalWarningCount(),
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   format: string,
     *   source_filename: string,
     *   sha256: string,
     *   byte_size: int,
     *   transaction_count: int,
     *   unique_item_count: int,
     *   created_at: string
     * }
     */
    private static function serializeDataset(DatasetRecord $dataset): array
    {
        return [
            'id' => $dataset->getId(),
            'name' => $dataset->getName(),
            'format' => $dataset->getFormat(),
            'source_filename' => $dataset->getSourceFilename(),
            'sha256' => $dataset->getSha256(),
            'byte_size' => $dataset->getByteSize(),
            'transaction_count' => $dataset->getTransactionCount(),
            'unique_item_count' => $dataset->getUniqueItemCount(),
            'created_at' => self::serializeCreatedAt($dataset->getCreatedAt()),
        ];
    }

    private static function serializeCreatedAt(string $createdAt): string
    {
        $utc = new DateTimeZone('UTC');
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $createdAt, $utc);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $timestamp === false
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $timestamp->format('Y-m-d H:i:s') !== $createdAt
        ) {
            throw new \RuntimeException('Invalid internal dataset timestamp.');
        }

        return $timestamp->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array{code: string, line: int, message: string}
     */
    private static function serializeIssue(ParserIssue $issue): array
    {
        return [
            'code' => $issue->getCode(),
            'line' => $issue->getLine(),
            'message' => $issue->getMessage(),
        ];
    }

    private function datasetValidationError(DatasetValidationException $exception): ApiResponse
    {
        $issues = [];
        foreach ($exception->getIssues() as $issue) {
            $issues[] = self::serializeIssue($issue);
        }

        $firstCode = $issues[0]['code'] ?? '';
        $details = (object)[
            'issues' => $issues,
            'total_issues' => $exception->getTotalIssueCount(),
        ];

        if ($firstCode === 'UPLOAD_TOO_LARGE') {
            return ApiResponse::error(
                413,
                'UPLOAD_TOO_LARGE',
                'Uploaded dataset exceeds the maximum allowed size.',
                $details
            );
        }

        if ($firstCode === 'UNSUPPORTED_FORMAT' || $firstCode === 'PROFILE_MISMATCH') {
            return ApiResponse::error(
                415,
                'UNSUPPORTED_DATASET_FORMAT',
                'The declared dataset format is not supported.',
                $details
            );
        }

        return ApiResponse::error(
            422,
            'DATASET_VALIDATION_FAILED',
            'Dataset validation failed.',
            $details
        );
    }

    private static function internalError(): ApiResponse
    {
        return ApiResponse::error(500, 'INTERNAL_ERROR', 'An internal server error occurred.');
    }
}
