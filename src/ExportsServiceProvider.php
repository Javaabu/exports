<?php

namespace Javaabu\Exports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Javaabu\Exports\Commands\PruneExportsCommand;
use Javaabu\Exports\Policies\ExportPolicy;
use Javaabu\Exports\Routing\ExportRoutes;
use Javaabu\Permissions\Permissions;

class ExportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/exports.php', 'exports');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'exports');

        $this->registerRouteMacros();
        $this->registerPermissions();

        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishing();

            $this->commands([
                PruneExportsCommand::class,
            ]);
        }
    }

    protected function registerRouteMacros(): void
    {
        Route::macro('exports', function (array $options = []) {
            ExportRoutes::register($this, $options);
        });
    }

    /**
     * view_exports, view_others_exports, delete_exports, delete_others_exports, and the
     * default policy, which the app can override
     */
    protected function registerPermissions(): void
    {
        if (! config('exports.register_permissions', true)) {
            return;
        }

        Permissions::register('exports', [
            'view_exports' => 'View own exports',
            'view_others_exports' => 'View other users\' exports',
            'delete_exports' => 'Delete own exports',
            'delete_others_exports' => 'Delete other users\' exports',
        ], label: 'Exports');

        Permissions::policy(Exports::exportModel(), config('exports.policy', ExportPolicy::class));
    }

    protected function registerMigrations(): void
    {
        if (Exports::$runs_migrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/exports.php' => config_path('exports.php'),
        ], 'exports-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'exports-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/exports'),
        ], 'exports-views');
    }
}
