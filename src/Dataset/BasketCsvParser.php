<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class BasketCsvParser extends AbstractDatasetParser
{
    public function getFormatToken(): string
    {
        return 'basket_csv';
    }

    public function getAllowedExtensions(): array
    {
        return ['csv'];
    }

    public function parse(string $content, string $sourceFilename): ParseResult
    {
        $lines = $this->prepareLines($content);

        $transactions = [];
        $warnings = [];
        $errors = [];
        $ordinal = 1;

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
                $rawItems = [];
                $hasEmptyField = false;

                foreach ($fields as $field) {
                    if ($field === '' || trim($field, " \t\n\r\x0B\x0C") === '') {
                        $errors[] = new ParserIssue(
                            'EMPTY_FIELD',
                            $lineNum,
                            "Empty CSV field encountered at line {$lineNum}."
                        );
                        $hasEmptyField = true;
                        break;
                    }
                    $rawItems[] = $field;
                }

                if ($hasEmptyField) {
                    continue;
                }

                $transaction = CanonicalTransaction::fromRawItems($ordinal, $rawItems, $warnings, $lineNum);
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
