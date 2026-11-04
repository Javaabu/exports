<?php

namespace Javaabu\Exports\Tests\TestSupport\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Javaabu\Exports\Tests\TestSupport\Models\Admin;
use Javaabu\Exports\Tests\TestSupport\Models\Organization;
use Javaabu\Exports\Tests\TestSupport\Models\Role;
use Javaabu\Exports\Tests\TestSupport\Models\User;

class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom([
            __DIR__ . '/../database',
        ]);

        Relation::morphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'role' => Role::class,
            'organization' => Organization::class,
        ]);
    }
}
