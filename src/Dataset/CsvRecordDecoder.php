<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class CsvRecordDecoder
{
    /**
     * Decodes a single physical line CSV record into fields.
     * Enforces strict CSV grammar:
     * - Fields may be unquoted or enclosed in double quotes ("...")
     * - Quoted fields may contain commas
     * - Literal quotes inside quoted fields must be doubled ("")
     * - After a closing quote, only a comma or end of record is valid
     * - Unquoted fields must not contain quotes
     * - Backslash is not a quote escape mechanism
     * - Embedded physical newlines (\n or \r) inside quoted fields are unsupported and rejected
     *
     * @return list<string> Decoded field strings
     * @throws InvalidArgumentException if CSV record is malformed
     */
    public static function decode(string $record): array
    {
        $len = strlen($record);
        $i = 0;
        $fields = [];
        $expectingField = true;

        while ($i < $len) {
            $expectingField = false;

            if ($record[$i] === '"') {
                // Quoted field
                $i++; // skip opening quote
                $fieldContent = '';
                $closed = false;

                while ($i < $len) {
                    $ch = $record[$i];
                    if ($ch === "\n" || $ch === "\r") {
                        throw new InvalidArgumentException("Malformed CSV record: Embedded newlines inside quoted fields are unsupported.");
                    }
                    if ($ch === '"') {
                        // Check if doubled quote escape ""
                        if ($i + 1 < $len && $record[$i + 1] === '"') {
                            $fieldContent .= '"';
                            $i += 2;
                        } else {
                            // Closing quote reached
                            $closed = true;
                            $i++; // skip closing quote
                            break;
                        }
                    } else {
                        $fieldContent .= $ch;
                        $i++;
                    }
                }

                if (!$closed) {
                    throw new InvalidArgumentException("Malformed CSV record: Unclosed quote.");
                }

                $fields[] = $fieldContent;

                if ($i < $len) {
                    if ($record[$i] === ',') {
                        $i++; // move past comma
                        $expectingField = true;
                    } else {
                        throw new InvalidArgumentException("Malformed CSV record: Unexpected character after closing quote.");
                    }
                }
            } else {
                // Unquoted field
                $fieldContent = '';
                while ($i < $len) {
                    $ch = $record[$i];
                    if ($ch === '"') {
                        throw new InvalidArgumentException("Malformed CSV record: Unescaped quote inside unquoted field.");
                    }
                    if ($ch === ',') {
                        break;
                    }
                    $fieldContent .= $ch;
                    $i++;
                }

                $fields[] = $fieldContent;

                if ($i < $len && $record[$i] === ',') {
                    $i++; // move past comma
                    $expectingField = true;
                }
            }
        }

        if ($expectingField && count($fields) > 0) {
            $fields[] = '';
        }

        return $fields;
    }
}
