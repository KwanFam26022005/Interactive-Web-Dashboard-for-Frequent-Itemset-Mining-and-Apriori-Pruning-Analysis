<?php

declare(strict_types=1);

namespace App\Mining;

use RuntimeException;

class MiningLimitExceededException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
