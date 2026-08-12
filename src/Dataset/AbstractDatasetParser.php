<?php

declare(strict_types=1);

namespace App\Dataset;

abstract class AbstractDatasetParser implements DatasetParserInterface
{
    /**
     * Common file pre-validation and preparation:
     * - Valid UTF-8 check
     * - Strip leading UTF-8 BOM if present at offset 0
     * - Empty and blank-only checks
     * Returns physical line list (1-indexed line numbers).
     *
     * @return array<int, string> Map of 1-indexed line number => line string
     * @throws DatasetValidationException if UTF-8 is invalid, empty upload, or blank-only upload
     */
    protected function prepareLines(string $content): array
    {
        if ($content === '') {
            $issue = new ParserIssue('EMPTY_UPLOAD', 0, "Uploaded file content is empty.");
            throw new DatasetValidationException([$issue], 1);
        }

        // Validate UTF-8
        if (!preg_match('//u', $content)) {
            $issue = new ParserIssue('INVALID_UTF8', 0, "Uploaded file is not valid UTF-8 encoded.");
            throw new DatasetValidationException([$issue], 1);
        }

        // Strip leading UTF-8 BOM if present at offset 0
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if ($content === '' || trim($content, " \t\n\r\x0B\x0C") === '') {
            $issue = new ParserIssue('BLANK_ONLY_UPLOAD', 0, "Uploaded file contains only blank content.");
            throw new DatasetValidationException([$issue], 1);
        }

        // Split into physical lines preserving line numbers
        // Normalize CRLF / CR to LF before splitting
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $rawLines = explode("\n", $normalized);

        $lines = [];
        foreach ($rawLines as $idx => $line) {
            $lines[$idx + 1] = $line;
        }

        return $lines;
    }

    protected function isBlankLine(string $line): bool
    {
        return trim($line, " \t\r\n\x0B\x0C") === '';
    }
}
