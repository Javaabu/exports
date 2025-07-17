<?php

namespace Javaabu\Exports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Javaabu\Exports\Tests\TestSupport\Models\Organization;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
