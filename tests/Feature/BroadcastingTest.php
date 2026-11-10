<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Broadcasting\BroadcastManager;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Events\ExportProgressed;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Support\Broadcasting;
use Javaabu\Exports\Tests\Concerns\SeedsUsers;
use Javaabu\Exports\Tests\Http\InteractsWithExports;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Broadcasting\MemoryBroadcaster;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\Admin;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class BroadcastingTest extends TestCase
{
    use InteractsWithExports;
    use SeedsUsers;

    protected MemoryBroadcaster $broadcaster;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $broadcaster = $this->broadcaster = new MemoryBroadcaster;

        $app->make(BroadcastManager::class)->extend('memory', fn () => $broadcaster);

        $app['config']->set('broadcasting.connections.memory', ['driver' => 'memory']);
        $app['config']->set('broadcasting.default', 'memory');
        $app['config']->set('exports.broadcasting.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExports();
    }

    protected function tearDown(): void
    {
        Exports::forget();
        Broadcasting::$reverb_class = 'Laravel\\Reverb\\ReverbServiceProvider';

        parent::tearDown();
    }

    #[Test]
    public function it_broadcasts_progress_on_the_export_and_owner_channels(): void
    {
        config()->set('exports.queue.progress_every', 10);

        $user = User::factory()->create();
        $this->seedUsers(19);

        $export = UsersExport::make()->queue($user, 'csv');

        $events = array_column($this->broadcaster->broadcasts, 'event');

        $this->assertEquals(['export.started', 'export.progressed', 'export.progressed', 'export.completed'], $events);

        $completed = end($this->broadcaster->broadcasts);

        $this->assertEquals([
            "private-exports.{$export->uuid}",
            "private-exports.owner.user.{$user->id}",
        ], $completed['channels']);

        $this->assertEquals($export->uuid, $completed['payload']['id']);
        $this->assertEquals('completed', $completed['payload']['status']);
        $this->assertEquals(100, $completed['payload']['progress']);
        $this->assertTrue($completed['payload']['is_downloadable']);
    }

    #[Test]
    public function it_broadcasts_cancellation(): void
    {
        $export = $this->makeExport(User::factory()->create(), ['status' => ExportStatuses::PROCESSING]);

        $export->cancel();

        $this->assertEquals('export.cancelled', $this->broadcaster->broadcasts[0]['event']);
    }

    #[Test]
    public function it_authorizes_the_export_channel_with_the_policy(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();
        $ahmed->givePermissionTo('view_exports');
        $aisha->givePermissionTo('view_exports');

        $export = $this->makeExport($ahmed);

        $this->assertTrue($this->broadcaster->authorize("exports.{$export->uuid}", $ahmed));
        $this->assertFalse($this->broadcaster->authorize("exports.{$export->uuid}", $aisha));
        $this->assertFalse($this->broadcaster->authorize('exports.missing', $ahmed));
    }

    #[Test]
    public function it_authorizes_the_owner_channel_for_the_owner_only(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create(['id' => $user->id]);

        $this->assertTrue($this->broadcaster->authorize("exports.owner.user.{$user->id}", $user));
        $this->assertFalse($this->broadcaster->authorize("exports.owner.user.{$user->id}", $admin));
        $this->assertTrue($this->broadcaster->authorize("exports.owner.admin.{$admin->id}", $admin));
    }

    #[Test]
    public function the_api_tells_pages_which_channel_to_listen_on(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $export = $this->makeExport($user);

        $this->actingAs($user)
            ->getJson("/exports/{$export->uuid}")
            ->assertJsonPath('data.broadcast_channel', "exports.{$export->uuid}")
            ->assertJsonPath('meta.broadcasting', true);
    }

    #[Test]
    public function it_detects_reverb(): void
    {
        config()->set('exports.broadcasting.enabled', 'auto');
        config()->set('broadcasting.default', 'reverb');

        $this->assertTrue(Broadcasting::enabled());

        config()->set('broadcasting.default', 'null');
        $this->assertFalse(Broadcasting::enabled());

        config()->set('broadcasting.default', 'log');
        $this->assertFalse(Broadcasting::enabled());

        config()->set('broadcasting.default', 'reverb');
        Broadcasting::$reverb_class = 'Missing\\ReverbServiceProvider';
        $this->assertFalse(Broadcasting::enabled());

        config()->set('exports.broadcasting.enabled', true);
        $this->assertTrue(Broadcasting::enabled());

        config()->set('exports.broadcasting.enabled', 'false');
        $this->assertFalse(Broadcasting::enabled());
    }

    #[Test]
    public function it_encodes_class_morph_types_for_channel_names(): void
    {
        $this->assertEquals('App-Models-User', Broadcasting::encodeType('App\\Models\\User'));

        $export = $this->makeExport(null);

        $this->assertNull($export->ownerBroadcastChannel());
        $this->assertEquals(["private-exports.{$export->uuid}"], array_map('strval', (new ExportProgressed($export))->broadcastOn()));
    }
}
