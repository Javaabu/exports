<?php

namespace Javaabu\Exports\Tests\TestSupport\Exports;

use Javaabu\Exports\ModelExport;
use Javaabu\Exports\Tests\TestSupport\Models\Island;

class IslandsExport extends ModelExport
{
    public function modelClass(): string
    {
        return Island::class;
    }
}
