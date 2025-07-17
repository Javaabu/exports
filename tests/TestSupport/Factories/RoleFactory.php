<?php

namespace Javaabu\Exports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Javaabu\Exports\Tests\TestSupport\Models\Role;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
