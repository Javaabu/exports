<?php

namespace Javaabu\Exports\Tests\Http;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Models\Admin;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use Javaabu\Exports\Tests\TestSupport\Policies\DenyAllPolicy;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;

class ExportsControllerTest extends TestCase
{
    use InteractsWithExports;

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
    public function guests_cannot_see_exports(): void
    {
        $this->getJson('/exports')->assertUnauthorized();
    }

    #[Test]
    public function it_needs_a_permission_to_list_exports(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/exports')->assertForbidden();

        $user->givePermissionTo('view_exports');

        $this->actingAs($user)->getJson('/exports')->assertOk();
    }

    #[Test]
    public function users_see_only_their_own_exports(): void
    {
        $ahmed = User::factory()->create(['name' => 'އަހުމަދު']);
        $aisha = User::factory()->create(['name' => 'އައިޝާ']);

        $ahmed->givePermissionTo('view_exports');

        $own = $this->makeExport($ahmed, ['status' => ExportStatuses::PROCESSING, 'total_rows' => 200, 'processed_rows' => 50]);
        $this->makeExport($aisha);

        $this->actingAs($ahmed)
            ->getJson('/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->uuid)
            ->assertJsonPath('data.0.status', 'processing')
            ->assertJsonPath('data.0.status_label', 'Processing')
            ->assertJsonPath('data.0.progress', 25)
            ->assertJsonPath('data.0.user.name', 'އަހުމަދު')
            ->assertJsonPath('data.0.links.show', url("/exports/{$own->uuid}"))
            ->assertJsonPath('data.0.links.download', null)
            ->assertJsonPath('data.0.can.cancel', false)
            ->assertJsonPath('meta.broadcasting', false)
            ->assertJsonPath('meta.poll_interval', 3000)
            ->assertJsonPath('meta.routes.index', 'exports.index');
    }

    #[Test]
    public function users_who_view_others_see_everyones_exports(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();

        $ahmed->givePermissionTo('view_others_exports');

        $this->makeExport($ahmed);
        $this->makeExport($aisha, ['status' => ExportStatuses::FAILED, 'error' => 'Oops']);

        $this->actingAs($ahmed)->getJson('/exports')->assertJsonCount(2, 'data');
        $this->actingAs($ahmed)->getJson('/exports?mine=1')->assertJsonCount(1, 'data');
        $this->actingAs($ahmed)
            ->getJson('/exports?status=failed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error', 'Oops');
    }

    #[Test]
    public function it_starts_a_registered_export(): void
    {
        $user = User::factory()->create(['name' => 'އަހުމަދު']);
        $user->givePermissionTo(['view_exports', 'view_users']);

        $response = $this->actingAs($user)
            ->postJson('/exports', ['type' => 'users', 'format' => 'csv', 'filename' => '../ބޭނުންތެރިން/ލިސްޓް'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.filename', 'ބޭނުންތެރިން-ލިސްޓް.csv');

        $export = Export::where('uuid', $response->json('data.id'))->first();

        $this->assertTrue($export->isOwnedBy($user));
        $this->assertTrue($export->fileExists());
    }

    #[Test]
    public function it_needs_permission_to_run_the_export_itself(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $this->actingAs($user)
            ->postJson('/exports', ['type' => 'users'])
            ->assertForbidden();

        $this->assertEquals(0, Export::count());
    }

    #[Test]
    public function it_validates_the_type_and_format(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'view_users']);

        $this->actingAs($user)
            ->postJson('/exports', ['type' => 'secrets', 'format' => 'pdf'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'format']);
    }

    #[Test]
    public function it_shows_an_export_to_its_owner(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();

        $ahmed->givePermissionTo('view_exports');
        $aisha->givePermissionTo('view_exports');

        $export = $this->makeExport($ahmed);

        $this->actingAs($ahmed)->getJson("/exports/{$export->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $export->uuid)
            ->assertJsonPath('meta.routes.download', 'exports.download');

        $this->actingAs($aisha)->getJson("/exports/{$export->uuid}")->assertForbidden();

        $aisha->givePermissionTo('view_others_exports');

        $this->actingAs($aisha)->getJson("/exports/{$export->uuid}")->assertOk();

        $this->actingAs($aisha)->getJson('/exports/not-a-uuid')->assertNotFound();
    }

    #[Test]
    public function it_downloads_completed_exports(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $export = $this->completedExport($user);

        $response = $this->actingAs($user)->get("/exports/{$export->uuid}/download");

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('އަހުމަދު', $response->streamedContent());

        $pending = $this->makeExport($user);

        $this->actingAs($user)->get("/exports/{$pending->uuid}/download")->assertNotFound();

        $this->travel(8)->days();

        $this->actingAs($user)->get("/exports/{$export->uuid}/download")->assertGone();
    }

    #[Test]
    public function it_needs_permission_to_download_someone_elses_export(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();
        $aisha->givePermissionTo('view_exports');

        $export = $this->completedExport($ahmed);

        $this->actingAs($aisha)->get("/exports/{$export->uuid}/download")->assertForbidden();
    }

    #[Test]
    public function the_signed_link_downloads_without_logging_in(): void
    {
        $export = $this->completedExport(User::factory()->create());

        $url = $export->temporaryDownloadUrl();

        $this->assertStringStartsWith(url("/exports/{$export->uuid}/signed-download"), $url);

        $this->get($url)->assertOk();
        $this->get("{$url}x")->assertForbidden();
        $this->get(url("/exports/{$export->uuid}/signed-download"))->assertForbidden();

        $this->travel(25)->hours();

        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function the_signed_link_lasts_no_longer_than_the_file(): void
    {
        $export = $this->completedExport(User::factory()->create());
        $export->forceFill(['expires_at' => now()->addHour()])->save();

        $url = $export->temporaryDownloadUrl();

        $this->travel(2)->hours();

        $this->get($url)->assertForbidden();
        $this->get(URL::temporarySignedRoute('exports.signed-download', now()->addDay(), ['export' => $export->uuid]))->assertGone();
    }

    #[Test]
    public function users_cancel_their_own_running_exports(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $export = $this->makeExport($user, ['status' => ExportStatuses::PROCESSING]);

        $this->actingAs($user)->postJson("/exports/{$export->uuid}/cancel")->assertForbidden();

        $user->givePermissionTo('delete_exports');

        $this->actingAs($user)
            ->postJson("/exports/{$export->uuid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($user)
            ->postJson("/exports/{$export->uuid}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This export can no longer be cancelled.');
    }

    #[Test]
    public function deleting_an_export_deletes_its_file(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();
        $ahmed->givePermissionTo('delete_exports');
        $aisha->givePermissionTo('delete_exports');

        $export = $this->completedExport($ahmed);

        $this->actingAs($aisha)->deleteJson("/exports/{$export->uuid}")->assertForbidden();

        $this->actingAs($ahmed)->deleteJson("/exports/{$export->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
        $this->assertFalse($export->fileExists());
    }

    #[Test]
    public function admins_use_their_own_guard_and_routes(): void
    {
        $admin = Admin::factory()->create(['name' => 'މުދިރު']);
        $user = User::factory()->create();
        $user->givePermissionTo(['view_exports', 'view_others_exports']);

        $users_export = $this->makeExport($user);

        $this->actingAs($user)->getJson('/admin/exports')->assertUnauthorized();

        $this->actingAs($admin, 'web_admin')->getJson('/admin/exports')->assertForbidden();

        $admin->givePermissionTo('view_exports');

        $admins_export = $this->makeExport($admin);

        $this->actingAs($admin, 'web_admin')
            ->getJson('/admin/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $admins_export->uuid)
            ->assertJsonPath('data.0.user.type', 'admin')
            ->assertJsonPath('data.0.links.show', url("/admin/exports/{$admins_export->uuid}"))
            ->assertJsonPath('meta.routes.show', 'admin.exports.show');

        $this->actingAs($admin, 'web_admin')->getJson("/admin/exports/{$users_export->uuid}")->assertForbidden();
    }

    #[Test]
    public function admins_start_exports_owned_by_the_admin(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['view_exports', 'view_users']);

        $response = $this->actingAs($admin, 'web_admin')
            ->postJson('/admin/exports', ['type' => 'users', 'format' => 'xlsx'])
            ->assertCreated();

        $export = Export::where('uuid', $response->json('data.id'))->first();

        $this->assertEquals('admin', $export->user_type);
        $this->assertEquals($admin->id, $export->user_id);
    }

    protected function usesDenyAllPolicy($app): void
    {
        $app['config']->set('permissions.policies', [Export::class => DenyAllPolicy::class]);
    }

    #[Test]
    #[DefineEnvironment('usesDenyAllPolicy')]
    public function the_app_can_override_the_policy(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $this->actingAs($user)->getJson('/exports')->assertForbidden();
    }

    #[Test]
    public function the_app_can_override_the_policy_with_the_gate(): void
    {
        Gate::policy(Export::class, DenyAllPolicy::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $this->actingAs($user)->getJson('/exports')->assertForbidden();
    }

    #[Test]
    public function it_registers_the_default_permissions(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'view_exports', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'view_others_exports', 'guard_name' => 'web_admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'delete_exports', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'delete_others_exports', 'guard_name' => 'web_admin']);
    }
}
