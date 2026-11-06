<?php

namespace Javaabu\Exports;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Javaabu\Exports\Commands\PruneExportsCommand;
use Javaabu\Exports\Policies\ExportPolicy;
use Javaabu\Exports\Routing\ExportRoutes;
use Javaabu\Exports\Support\Broadcasting;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Package;
use Javaabu\Permissions\Permissions;

class ExportsServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/exports.php', 'exports');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'exports');

        $this->registerFrontendAssets();
        $this->registerRouteMacros();
        $this->registerPermissions();
        $this->registerBroadcastChannels();

        if ($this->app->runningInConsole()) {
            $this->registerMigrations();
            $this->registerPublishing();

            $this->commands([
                PruneExportsCommand::class,
            ]);
        }
    }

    /**
     * The Inertia pages (exports::Exports/Index), the components apps import through
     * the package's alias, and the progress script the Blade pages load.
     *
     * The Blade script imports no npm packages, so every npm dependency is for the
     * pages only, and Blade-only apps aren't asked to install React.
     */
    protected function registerFrontendAssets(): void
    {
        $this->registerAssets('exports', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->script('progress', 'js/progress.ts')
                ->js()
                ->pages()
                ->npm([
                    '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
                    '@javaabu/onu' => '^0.2.1',
                ], peer_dependencies: [
                    'react' => '^18.3 || ^19.0',
                    'react-dom' => '^18.3 || ^19.0',
                    '@inertiajs/react' => '^2.0 || ^3.0',
                ], for: NpmScopes::PAGES);
        });
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

    /**
     * Authorize the progress channels when broadcasting is on (Reverb installed, or forced)
     */
    protected function registerBroadcastChannels(): void
    {
        if (! Broadcasting::enabled()) {
            return;
        }

        Broadcasting::registerChannels();
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
