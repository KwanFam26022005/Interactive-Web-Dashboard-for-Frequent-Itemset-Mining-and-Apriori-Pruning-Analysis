<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class BasketTextParser extends AbstractDatasetParser
{
    public function getFormatToken(): string
    {
        return 'basket_txt';
    }

    public function getAllowedExtensions(): array
    {
        return ['txt', 'dat'];
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
                // Split on 1 or more ASCII whitespace characters
                $tokens = preg_split('/[ \t\n\r\x0B\x0C]+/', trim($line, " \t\n\r\x0B\x0C"));
                if ($tokens === false || count($tokens) === 0 || ($tokens[0] === '' && count($tokens) === 1)) {
                    $errors[] = new ParserIssue(
                        'EMPTY_RECORD',
                        $lineNum,
                        "Record at line {$lineNum} produced no items after ASCII whitespace tokenization."
                    );
                    continue;
                }

                $transaction = CanonicalTransaction::fromRawItems($ordinal, $tokens, $warnings, $lineNum);
                $transactions[] = $transaction;
                $ordinal++;
            } catch (InvalidArgumentException $e) {
                $errors[] = new ParserIssue('INVALID_ITEM', $lineNum, $e->getMessage());
            }
        }

        if (count($errors) > 0) {
            throw new DatasetValidationException($errors);
        }

        return new ParseResult($transactions, $warnings);
    }
}
