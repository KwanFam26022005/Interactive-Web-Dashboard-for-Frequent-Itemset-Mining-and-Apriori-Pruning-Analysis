<?php

declare(strict_types=1);

namespace App\Dataset;

use InvalidArgumentException;

class ParserRegistry
{
    /** @var array<string, DatasetParserInterface> */
    private array $parsers;

    public function __construct(?array $parsers = null)
    {
        if ($parsers !== null) {
            $this->parsers = $parsers;
        } else {
            $this->parsers = [
                'basket_csv' => new BasketCsvParser(),
                'basket_txt' => new BasketTextParser(),
                'mushroom' => new MushroomParser(),
            ];
        }
    }

    public function hasParser(string $formatToken): bool
    {
        return isset($this->parsers[$formatToken]);
    }

    public function getParser(string $formatToken): DatasetParserInterface
    {
        if (!isset($this->parsers[$formatToken])) {
            throw new InvalidArgumentException("Unsupported format token '{$formatToken}'.");
        }
        return $this->parsers[$formatToken];
    }

    /**
     * Validates that the source filename extension matches the profile for the chosen format token.
     *
     * @throws DatasetValidationException if extension profile mismatches
     */
    public function validateExtension(string $formatToken, string $sourceFilename): void
    {
        $parser = $this->getParser($formatToken);
        $safeBasename = basename($sourceFilename);
        $ext = strtolower(pathinfo($safeBasename, PATHINFO_EXTENSION));

        $allowed = $parser->getAllowedExtensions();
        if (!in_array($ext, $allowed, true)) {
            $allowedStr = implode(', ', array_map(fn($e) => ".{$e}", $allowed));
            $issue = new ParserIssue(
                'PROFILE_MISMATCH',
                0,
                "Source filename extension '.{$ext}' is not supported for format profile '{$formatToken}'. Allowed extensions: {$allowedStr}."
            );
            throw new DatasetValidationException([$issue], 1);
        }
    }

    /**
     * Helper to validate extension and parse file content.
     *
     * @throws DatasetValidationException
     */
    public function parseFormat(string $formatToken, string $content, string $sourceFilename): ParseResult
    {
        $this->validateExtension($formatToken, $sourceFilename);
        $parser = $this->getParser($formatToken);
        return $parser->parse($content, $sourceFilename);
    }
}
