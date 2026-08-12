<?php

declare(strict_types=1);

namespace App\Dataset;

class ParserIssue
{
    private string $code;
    private int $line;
    private string $message;

    public function __construct(string $code, int $line, string $message)
    {
        $this->code = $code;
        $this->line = $line;
        $this->message = $message;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array{code: string, line: int, message: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'line' => $this->line,
            'message' => $this->message,
        ];
    }
}
