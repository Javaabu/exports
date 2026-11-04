<?php

namespace Javaabu\Exports\Tests\Http;

use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use Javaabu\Manifest\Facades\Manifest;
use PHPUnit\Framework\Attributes\Test;

class BladePagesTest extends TestCase
{
    use InteractsWithExports;

    protected function setUp(): void
    {
        parent::setUp();

        Manifest::fake();

        $this->setUpExports();
    }

    protected function tearDown(): void
    {
        Exports::forget();

        parent::tearDown();
    }

    #[Test]
    public function the_index_lists_exports_with_their_progress(): void
    {
        $user = User::factory()->create(['name' => 'އަހުމަދު']);
        $user->givePermissionTo(['view_exports', 'delete_exports', 'view_users']);

        $running = $this->makeExport($user, ['status' => ExportStatuses::PROCESSING, 'total_rows' => 200, 'processed_rows' => 50]);
        $done = $this->completedExport($user);

        $this->actingAs($user)
            ->get('/exports')
            ->assertOk()
            ->assertSee('Exports')
            ->assertSee('New export')
            ->assertSee('Start export')
            ->assertSee('Excel (XLSX)')
            ->assertSee('ބޭނުންތެރިން.csv')
            ->assertSee("data-export-row=\"{$running->uuid}\"", false)
            ->assertSee('data-export-finished="false"', false)
            ->assertSee('inline-size: 25%', false)
            ->assertSee('50 / 200')
            ->assertSee('Processing')
            ->assertSee(url("/exports/{$done->uuid}/download"), false)
            ->assertSee(url("/exports/{$running->uuid}/cancel"), false)
            ->assertSee('data-poll-interval="3000"', false)
            ->assertDontSee('Started by');

        Manifest::assertAssetRequired('exports::progress');
    }

    #[Test]
    public function the_index_shows_an_empty_state_and_hides_the_form_without_exports_to_start(): void
    {
        Exports::forget();

        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $this->actingAs($user)
            ->get('/exports')
            ->assertOk()
            ->assertSee('No exports yet')
            ->assertDontSee('Start export');
    }

    #[Test]
    public function users_who_see_others_get_the_owner_column_and_filter(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_others_exports');

        $this->makeExport(User::factory()->create(['name' => 'އައިޝާ']));

        $this->actingAs($user)
            ->get('/exports')
            ->assertSee('Started by')
            ->assertSee('އައިޝާ')
            ->assertSee('Only my exports')
            ->assertDontSee('Cancel this export?');
    }

    #[Test]
    public function the_pages_are_in_dhivehi(): void
    {
        app()->setLocale('dv');

        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'view_users']);

        $export = $this->completedExport($user);

        $this->actingAs($user)
            ->get('/exports')
            ->assertSee('އެކްސްޕޯޓްތައް')
            ->assertSee('އެކްސްޕޯޓް ފައްޓަވާ')
            ->assertSee('ނިމިއްޖެ');

        $this->actingAs($user)
            ->get("/exports/{$export->uuid}")
            ->assertOk()
            ->assertSee('އެކްސްޕޯޓްތަކަށް އެނބުރި')
            ->assertSee('ބޭނުންތެރިން.csv');
    }

    #[Test]
    public function the_pages_use_no_physical_direction_classes(): void
    {
        foreach (glob(__DIR__ . '/../../resources/views/exports/{,partials/}*.blade.php', GLOB_BRACE) as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(ml|mr|pl|pr|left|right|text-left|text-right|border-l|border-r|rounded-l|rounded-r)-/',
                file_get_contents($view),
                basename($view) . ' should use logical utilities (ms-, pe-, start-, text-start).',
            );
        }
    }

    #[Test]
    public function starting_an_export_from_the_page_redirects_with_a_message(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'view_users']);

        $this->actingAs($user)
            ->from('/exports')
            ->post('/exports', ['type' => 'users', 'format' => 'csv'])
            ->assertRedirect('/exports')
            ->assertSessionHas('success', 'Your export has started. We will let you know when it is ready.');
    }

    #[Test]
    public function cancelling_and_deleting_from_the_page_redirect_back(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'delete_exports']);

        $export = $this->makeExport($user);

        $this->actingAs($user)
            ->from("/exports/{$export->uuid}")
            ->post("/exports/{$export->uuid}/cancel")
            ->assertRedirect("/exports/{$export->uuid}")
            ->assertSessionHas('success', 'Export cancelled');

        $this->actingAs($user)
            ->from("/exports/{$export->uuid}")
            ->post("/exports/{$export->uuid}/cancel")
            ->assertSessionHas('error', 'This export can no longer be cancelled.');

        $this->actingAs($user)
            ->delete("/exports/{$export->uuid}")
            ->assertRedirect('/exports')
            ->assertSessionHas('success', 'Export deleted');
    }
}
