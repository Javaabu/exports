<?php

namespace Javaabu\Exports\Tests\Http;

use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Http\CustomExportsController;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ControllerFromConfigTest extends TestCase
{
    use InteractsWithExports;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('exports.controllers.exports', CustomExportsController::class);
    }

    #[Test]
    public function the_config_replaces_the_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/exports')
            ->assertJson(['custom' => true]);
    }
}
