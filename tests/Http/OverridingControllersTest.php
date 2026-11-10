<?php

namespace Javaabu\Exports\Tests\Http;

use Javaabu\Exports\Http\Controllers\ExportsController;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Http\CustomExportsController;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class OverridingControllersTest extends TestCase
{
    use InteractsWithExports;

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->exports(['prefix' => 'option', 'name' => 'option', 'controller' => CustomExportsController::class]);
            $router->exports(['prefix' => 'default', 'name' => 'default']);
            $router->exports(['prefix' => 'unsigned', 'name' => 'unsigned', 'signed' => false]);
        });
    }

    #[Test]
    public function the_route_option_replaces_the_controller(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/option')
            ->assertOk()
            ->assertJson(['custom' => true]);
    }

    #[Test]
    public function the_container_replaces_the_controller(): void
    {
        $this->app->bind(ExportsController::class, CustomExportsController::class);

        $this->actingAs(User::factory()->create())
            ->getJson('/default')
            ->assertJson(['custom' => true]);
    }

    #[Test]
    public function the_signed_route_can_be_left_out(): void
    {
        $this->assertTrue(app('router')->has('default.signed-download'));
        $this->assertFalse(app('router')->has('unsigned.signed-download'));
        $this->assertTrue(app('router')->has('unsigned.download'));
    }
}
