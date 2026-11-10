<?php

namespace Javaabu\Exports\Tests\TestSupport\Policies;

class DenyAllPolicy
{
    public function before(): bool
    {
        return false;
    }
}
