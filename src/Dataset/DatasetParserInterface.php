<?php

declare(strict_types=1);

namespace App\Dataset;

interface DatasetParserInterface
{
    public function getFormatToken(): string;

    /**
     * Allowed lowercase filename extensions for profile matching (e.g. ['csv']).
     *
     * @return list<string>
     */
    public function getAllowedExtensions(): array;

    /**
     * Parses uploaded raw file bytes and source filename into a ParseResult.
     *
     * @throws DatasetValidationException if dataset is empty, blank-only, or contains malformed records.
     */
    public function parse(string $content, string $sourceFilename): ParseResult;
}
