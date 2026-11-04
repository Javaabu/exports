<?php

namespace Javaabu\Exports;

use Illuminate\Support\ServiceProvider;

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
            $this->registerPublishing();
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/exports.php' => config_path('exports.php'),
        ], 'exports-config');
    }
}
