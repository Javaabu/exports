<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Manifest;
use PHPUnit\Framework\Attributes\Test;

class NpmRequirementsTest extends TestCase
{
    protected const array PAGE_PACKAGES = ['@javaabu/forms', '@javaabu/onu', 'react', 'react-dom', '@inertiajs/react'];

    protected string $app_path;

    protected string $original_base_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app_path = sys_get_temp_dir() . '/exports-npm-app-' . getmypid();
        $this->original_base_path = $this->app->basePath();

        File::ensureDirectoryExists($this->app_path);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->original_base_path);

        File::deleteDirectory($this->app_path);

        parent::tearDown();
    }

    #[Test]
    public function every_npm_dependency_is_for_the_pages(): void
    {
        $package = app(Manifest::class)->package('exports');

        $this->assertEquals([], $package->npmDependencies(NpmScopes::ASSETS));
        $this->assertEquals([], $package->npmPeerDependencies(NpmScopes::ASSETS));
        $this->assertEquals(['@javaabu/forms', '@javaabu/onu'], array_keys($package->npmDependencies(NpmScopes::PAGES)));
        $this->assertEquals(['react', 'react-dom', '@inertiajs/react'], array_keys($package->npmPeerDependencies(NpmScopes::PAGES)));
    }

    #[Test]
    public function a_blade_only_app_needs_no_react_packages_for_exports(): void
    {
        $this->makeApp(['vite' => '7.1.0', 'laravel-vite-plugin' => '2.0.0']);

        $rows = $this->rowsNeededByExports();

        // javaabu/forms' own Blade assets are checked too; only look at what exports asks for
        $this->assertEquals(['laravel-vite-plugin', 'vite'], array_keys($rows));
        $this->assertEquals(['ok', 'ok'], array_column($rows, 'status'));

        $all_names = array_column($this->check(), 'name');

        foreach (['react', 'react-dom', '@inertiajs/react', '@javaabu/forms'] as $react_package) {
            $this->assertNotContains($react_package, $all_names);
        }
    }

    #[Test]
    public function an_inertia_app_is_asked_for_the_pages_packages(): void
    {
        $this->makeApp(['vite' => '7.1.0', 'laravel-vite-plugin' => '2.0.0', '@inertiajs/react' => '2.3.0']);

        $rows = $this->rowsNeededByExports();

        foreach (self::PAGE_PACKAGES as $page_package) {
            $this->assertArrayHasKey($page_package, $rows, "{$page_package} should be checked for an Inertia app");
            $this->assertContains('pages', $rows[$page_package]['scopes']);
        }

        $this->assertEquals('ok', $rows['@inertiajs/react']['status']);
        $this->assertEquals('missing', $rows['react']['status']);
    }

    /**
     * An app folder with a package.json and these packages installed in node_modules
     *
     * @param  array<string, string>  $installed
     */
    protected function makeApp(array $installed): void
    {
        File::put("{$this->app_path}/package.json", json_encode([
            'private' => true,
            'devDependencies' => array_map(fn (string $version) => "^{$version}", $installed),
        ]));

        foreach ($installed as $name => $version) {
            File::ensureDirectoryExists("{$this->app_path}/node_modules/{$name}");
            File::put("{$this->app_path}/node_modules/{$name}/package.json", json_encode(['name' => $name, 'version' => $version]));
        }

        $this->app->setBasePath($this->app_path);
    }

    /**
     * The rows of `manifest:check --json`
     *
     * @return array<int, array<string, mixed>>
     */
    protected function check(): array
    {
        Artisan::call('manifest:check', ['--json' => true]);

        return json_decode(Artisan::output(), true) ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rowsNeededByExports(): array
    {
        return collect($this->check())
            ->filter(fn (array $row) => in_array('exports', $row['needed_by'], true))
            ->keyBy('name')
            ->sortKeys()
            ->all();
    }
}
