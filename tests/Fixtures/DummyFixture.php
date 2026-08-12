<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

class DummyFixture
{
    public function getValue(): string
    {
        return 'fixture_ok';
    }
}
