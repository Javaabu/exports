<?php

namespace Javaabu\Exports\Tests\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use Javaabu\Exports\Tests\TestSupport\Policies\UserPolicy;
use Javaabu\Permissions\Permissions;

trait InteractsWithExports
{
    use RefreshDatabase;

    protected function setUpExports(): void
    {
        Storage::fake('exports');

        config()->set('exports.disk', 'exports');

        Permissions::resource('users', 'users', ['view']);
        Permissions::policy(User::class, UserPolicy::class);

        Exports::register('users', UsersExport::class);

        $this->artisan('permissions:sync');
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->exports();
            $router->exports(['guard' => 'web_admin', 'prefix' => 'admin/exports', 'name' => 'admin.exports']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeExport(?Model $user, array $attributes = []): Export
    {
        $export = new Export;

        $export->forceFill(array_merge([
            'type' => 'users',
            'export_class' => UsersExport::class,
            'name' => 'Users',
            'format' => Formats::CSV,
            'status' => ExportStatuses::PENDING,
            'disk' => 'exports',
            'filename' => 'ބޭނުންތެރިން.csv',
        ], $attributes));

        if ($user) {
            $export->user()->associate($user);
        }

        $export->save();

        return $export;
    }

    protected function completedExport(?Model $user): Export
    {
        $export = $this->makeExport($user);

        $path = "exports/{$export->uuid}/ބޭނުންތެރިން.csv";
        Storage::disk('exports')->put($path, "Name\nއަހުމަދު\n");

        $export->markCompleted('exports', $path, 'ބޭނުންތެރިން.csv', 1, 23);

        return $export;
    }
}
