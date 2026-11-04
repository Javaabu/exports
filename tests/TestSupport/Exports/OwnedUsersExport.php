<?php

namespace Javaabu\Exports\Tests\TestSupport\Exports;

use Javaabu\Exports\Tests\TestSupport\Models\User;

class OwnedUsersExport extends UsersExport
{
    public function __construct(
        public User $owner,
    ) {
        parent::__construct();
    }
}
