<?php

namespace Javaabu\Exports\Tests\TestSupport\Exports;

use Javaabu\Exports\ModelExport;
use Javaabu\Exports\Tests\TestSupport\Models\User;

class UsersExport extends ModelExport
{

    public function modelClass(): string
    {
        return User::class;
    }
}
