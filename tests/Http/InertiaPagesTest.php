<?php

namespace Javaabu\Exports\Tests\Http;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class InertiaPagesTest extends TestCase
{
    use InteractsWithExports;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('exports.views', 'inertia');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExports();
    }

    protected function tearDown(): void
    {
        Exports::forget();

        parent::tearDown();
    }

    #[Test]
    public function the_index_renders_the_inertia_page(): void
    {
        app()->setLocale('dv');

        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'view_users']);

        $export = $this->makeExport($user, ['status' => ExportStatuses::PROCESSING, 'total_rows' => 10, 'processed_rows' => 5]);

        $this->actingAs($user)
            ->get('/exports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('exports::Exports/Index')
                ->where('title', 'އެކްސްޕޯޓްތައް')
                ->where('exports.data.0.id', $export->uuid)
                ->where('exports.data.0.progress', 50)
                ->where('exports.data.0.status_color', 'info')
                ->where('available.0.type', 'users')
                ->where('formats.0.value', 'xlsx')
                ->where('statuses.0.value', 'pending')
                ->where('sees_others', false)
                ->where('broadcasting', false)
                ->where('poll_interval', 3000)
                ->where('routes.store', 'exports.store')
                ->where('urls.store', url('/exports'))
                ->where('strings.start', 'އެކްސްޕޯޓް ފައްޓަވާ')
                ->has('exports.meta.last_page'));
    }

    #[Test]
    public function the_show_page_renders_the_inertia_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $export = $this->completedExport($user);

        $this->actingAs($user)
            ->get("/exports/{$export->uuid}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('exports::Exports/Show')
                ->where('export.id', $export->uuid)
                ->where('export.is_downloadable', true)
                ->where('export.links.download', url("/exports/{$export->uuid}/download"))
                ->where('strings.back', 'Back to exports')
                ->where('title', 'Users'));
    }

    #[Test]
    public function the_pages_exist_where_manifest_resolves_them(): void
    {
        $this->assertFileExists(__DIR__ . '/../../resources/js/Pages/Exports/Index.tsx');
        $this->assertFileExists(__DIR__ . '/../../resources/js/Pages/Exports/Show.tsx');
    }
}
