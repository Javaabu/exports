<?php

namespace Javaabu\Exports\Tests\TestSupport\Policies;

use Javaabu\Permissions\Policies\ResourcePolicy;

class UserPolicy extends ResourcePolicy
{
    protected string $resource = 'users';
}
