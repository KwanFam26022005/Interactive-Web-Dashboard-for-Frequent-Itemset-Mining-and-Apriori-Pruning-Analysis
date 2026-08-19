<?php

declare(strict_types=1);

namespace App\Experiments;

use App\Dataset\CsvRecordDecoder;
use App\Dataset\DatasetValidationException;
use App\Dataset\ParserRegistry;
use InvalidArgumentException;
use RuntimeException;

class DatasetInspector
{
    private ParserRegistry $registry;

    public function __construct(?ParserRegistry $registry = null)
    {
        $this->registry = $registry ?? new ParserRegistry();
    }

    /**
     * Inspects a dataset file on disk and returns detailed physical and domain statistics.
     *
     * @param string $filePath Path to dataset file
     * @param string $profile Ingestion profile token (e.g. 'mushroom', 'basket_csv', 'basket_txt')
     * @return array{
     *     file_path: string,
     *     file_basename: string,
     *     raw_byte_size: int,
     *     raw_sha256: string,
     *     total_lines: int,
     *     blank_lines: int,
     *     data_lines: int,
     *     profile: string,
     *     observed_columns_min: int|null,
     *     observed_columns_max: int|null,
     *     column_consistency: bool,
     *     transaction_count: int,
     *     unique_item_count: int,
     *     warnings_count: int,
     *     warnings: list<string>
     * }
     * @throws InvalidArgumentException if file does not exist or profile is unsupported
     * @throws DatasetValidationException if parsing encounters dataset validation errors
     */
    public function inspect(string $filePath, string $profile): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Dataset file does not exist or is not readable: {$filePath}");
        }

        if (!$this->registry->hasParser($profile)) {
            throw new InvalidArgumentException("Unsupported ingestion profile: '{$profile}'");
        }

        $content = (string)file_get_contents($filePath);
        $rawByteSize = strlen($content);
        $rawSha256 = hash('sha256', $content);

        $lines = preg_split("/\r\n|\n|\r/", $content);
        if ($lines === false) {
            $lines = [];
        }

        $totalLines = count($lines);
        $blankLines = 0;
        $dataLines = 0;
        $colCounts = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $blankLines++;
                continue;
            }
            $dataLines++;

            if ($profile === 'mushroom' || $profile === 'basket_csv') {
                try {
                    $fields = CsvRecordDecoder::decode($line);
                    $colCounts[] = count($fields);
                } catch (\Throwable) {
                    // Fallback to simple comma split if decoder fails on inspection
                    $colCounts[] = substr_count($line, ',') + 1;
                }
            }
        }

        $colsMin = !empty($colCounts) ? min($colCounts) : null;
        $colsMax = !empty($colCounts) ? max($colCounts) : null;
        $columnConsistency = ($colsMin !== null && $colsMax !== null && $colsMin === $colsMax);

        // Parse through official parser registry
        $parser = $this->registry->getParser($profile);
        $parseResult = $parser->parse($content, basename($filePath));

        $transactions = $parseResult->getTransactions();
        $transactionCount = count($transactions);

        // Extract all unique canonical items
        $allItems = [];
        foreach ($transactions as $tx) {
            foreach ($tx->getItems() as $item) {
                $allItems[$item] = true;
            }
        }
        $uniqueItemCount = count($allItems);

        $warningMessages = [];
        foreach ($parseResult->getWarnings() as $warning) {
            $warningMessages[] = "[{$warning->getCode()}] Line {$warning->getLine()}: {$warning->getMessage()}";
        }

        return [
            'file_path' => $filePath,
            'file_basename' => basename($filePath),
            'raw_byte_size' => $rawByteSize,
            'raw_sha256' => strtolower($rawSha256),
            'total_lines' => $totalLines,
            'blank_lines' => $blankLines,
            'data_lines' => $dataLines,
            'profile' => $profile,
            'observed_columns_min' => $colsMin,
            'observed_columns_max' => $colsMax,
            'column_consistency' => $columnConsistency,
            'transaction_count' => $transactionCount,
            'unique_item_count' => $uniqueItemCount,
            'warnings_count' => count($warningMessages),
            'warnings' => $warningMessages,
        ];
    }
}
