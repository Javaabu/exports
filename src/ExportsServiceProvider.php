<?php

namespace Javaabu\Exports;

use Illuminate\Support\ServiceProvider;
use Javaabu\Exports\Commands\PruneExportsCommand;

class ExportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/exports.php', 'exports');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishing();

            $this->commands([
                PruneExportsCommand::class,
            ]);
        }
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
    }
}
