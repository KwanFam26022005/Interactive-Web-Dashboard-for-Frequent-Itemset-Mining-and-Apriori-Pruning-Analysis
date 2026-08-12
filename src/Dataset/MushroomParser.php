<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class MushroomParser extends AbstractDatasetParser
{
    public function getFormatToken(): string
    {
        return 'mushroom';
    }

    public function getAllowedExtensions(): array
    {
        return ['csv', 'data'];
    }

    public function parse(string $content, string $sourceFilename): ParseResult
    {
        $lines = $this->prepareLines($content);

        $transactions = [];
        $warnings = [];
        $errors = [];
        $ordinal = 1;
        $fixedFieldCount = null;

        foreach ($lines as $lineNum => $line) {
            if ($this->isBlankLine($line)) {
                $warnings[] = new ParserIssue(
                    'BLANK_RECORD_SKIPPED',
                    $lineNum,
                    "Blank physical record skipped at line {$lineNum}."
                );
                continue;
            }

            try {
                $fields = $this->parseCsvFields($line, $lineNum);
                $fieldCount = count($fields);

                if ($fixedFieldCount === null) {
                    $fixedFieldCount = $fieldCount;
                } else if ($fieldCount !== $fixedFieldCount) {
                    $errors[] = new ParserIssue(
                        'INCONSISTENT_FIELD_COUNT',
                        $lineNum,
                        "Mushroom record at line {$lineNum} has {$fieldCount} fields, expected {$fixedFieldCount}."
                    );
                    continue;
                }

                $mappedItems = [];
                $hasEmptyField = false;

                foreach ($fields as $idx => $field) {
                    $trimmed = trim($field, " \t\n\r\x0B\x0C");
                    if ($trimmed === '') {
                        $errors[] = new ParserIssue(
                            'EMPTY_FIELD',
                            $lineNum,
                            "Empty categorical field at line {$lineNum}, column " . ($idx + 1) . "."
                        );
                        $hasEmptyField = true;
                        break;
                    }

                    // Positional prefix mapping: c{col}=val
                    $colIndex = $idx + 1;
                    $mappedItems[] = "c{$colIndex}={$trimmed}";
                }

                if ($hasEmptyField) {
                    continue;
                }

                $transaction = CanonicalTransaction::fromRawItems($ordinal, $mappedItems, $warnings, $lineNum);
                $transactions[] = $transaction;
                $ordinal++;
            } catch (InvalidArgumentException $e) {
                $code = str_contains($e->getMessage(), 'Malformed') ? 'MALFORMED_CSV' : 'INVALID_ITEM';
                $errors[] = new ParserIssue($code, $lineNum, $e->getMessage());
            }
        }

        if (count($errors) > 0) {
            throw new DatasetValidationException($errors);
        }

        return new ParseResult($transactions, $warnings);
    }

    /**
     * @return list<string>
     */
    private function parseCsvFields(string $line, int $lineNum): array
    {
        if (substr_count($line, '"') % 2 !== 0) {
            throw new InvalidArgumentException("Malformed CSV record at line {$lineNum}: Unbalanced quotes.");
        }

        $fields = str_getcsv($line, ',', '"', '\\');
        if ($fields === false || (count($fields) === 1 && $fields[0] === null)) {
            throw new InvalidArgumentException("Malformed CSV record at line {$lineNum}.");
        }

        return $fields;
    }
}
