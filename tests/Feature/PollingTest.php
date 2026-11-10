<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Support\Facades\Broadcast;
use Javaabu\Exports\Events\ExportProgressed;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Support\Broadcasting;
use Javaabu\Exports\Tests\Http\InteractsWithExports;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class PollingTest extends TestCase
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
    public function without_reverb_nothing_is_broadcast_and_pages_poll(): void
    {
        config()->set('exports.broadcasting.poll_interval', 1500);

        $this->assertFalse(Broadcasting::enabled());
        $this->assertEmpty(Broadcast::getChannels());

        $user = User::factory()->create();
        $user->givePermissionTo('view_exports');

        $export = $this->makeExport($user);

        $this->assertFalse((new ExportProgressed($export))->broadcastWhen());

        $this->actingAs($user)
            ->getJson("/exports/{$export->uuid}")
            ->assertJsonPath('data.broadcast_channel', null)
            ->assertJsonPath('data.progress', null)
            ->assertJsonPath('meta.broadcasting', false)
            ->assertJsonPath('meta.poll_interval', 1500);
    }
}
