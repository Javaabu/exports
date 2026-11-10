<?php

namespace Javaabu\Exports\Tests\TestSupport\Exports;

use RuntimeException;

class FailingExport extends PlainUsersExport
{
    public function rows(): iterable
    {
        yield ['ok'];

        throw new RuntimeException('The database went away');
    }
}
