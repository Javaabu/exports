<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Events\ExportCancelled;
use Javaabu\Exports\Events\ExportCompleted;
use Javaabu\Exports\Events\ExportFailed;
use Javaabu\Exports\Events\ExportProgressed;
use Javaabu\Exports\Events\ExportStarted;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Jobs\ProcessExport;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Notifications\ExportCompletedNotification;
use Javaabu\Exports\Notifications\ExportFailedNotification;
use Javaabu\Exports\Tests\Concerns\SeedsUsers;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\FailingExport;
use Javaabu\Exports\Tests\TestSupport\Exports\OwnedUsersExport;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\Organization;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use Javaabu\Exports\Writers\SpreadsheetWriter;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class QueuedExportsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exports');

        config()->set('exports.disk', 'exports');
    }

    protected function tearDown(): void
    {
        Exports::forget();

        parent::tearDown();
    }

    #[Test]
    public function it_creates_a_pending_export_and_dispatches_the_job(): void
    {
        Queue::fake();

        Exports::register('users', UsersExport::class);

        $user = User::factory()->create();

        $export = UsersExport::make()->queue($user, Formats::CSV, 'ބޭނުންތެރިން');

        $this->assertDatabaseHas('exports', [
            'id' => $export->id,
            'type' => 'users',
            'export_class' => UsersExport::class,
            'name' => 'Users',
            'format' => 'csv',
            'status' => 'pending',
            'disk' => 'exports',
            'filename' => 'ބޭނުންތެރިން.csv',
            'user_type' => 'user',
            'user_id' => $user->id,
        ]);

        $this->assertTrue($export->isOwnedBy($user));
        $this->assertNotEmpty($export->uuid);

        Queue::assertPushed(ProcessExport::class, fn (ProcessExport $job) => $job->export->is($export));
    }

    #[Test]
    public function it_pushes_the_job_to_the_configured_queue(): void
    {
        Queue::fake();

        config()->set('exports.queue.connection', 'redis');
        config()->set('exports.queue.queue', 'exports');

        UsersExport::make()->queue();

        Queue::assertPushedOn('exports', ProcessExport::class, fn (ProcessExport $job) => $job->connection === 'redis');
    }

    #[Test]
    public function it_writes_the_file_to_the_disk_and_notifies_the_user(): void
    {
        Notification::fake();
        Event::fake([ExportStarted::class, ExportProgressed::class, ExportCompleted::class]);

        config()->set('exports.queue.progress_every', 10);

        $user = User::factory()->create(['name' => 'އަހުމަދު']);
        $this->seedUsers(24);

        $export = UsersExport::make()->queue($user, 'xlsx', 'users')->fresh();

        $this->assertEquals(ExportStatuses::COMPLETED, $export->status);
        $this->assertEquals(25, $export->total_rows);
        $this->assertEquals(25, $export->processed_rows);
        $this->assertEquals(100, $export->progress());
        $this->assertEquals("exports/{$export->uuid}/users.xlsx", $export->path);
        $this->assertGreaterThan(0, $export->size);
        $this->assertNotNull($export->started_at);
        $this->assertNotNull($export->completed_at);
        $this->assertEquals(now()->addDays(7)->toDateString(), $export->expires_at->toDateString());

        Storage::disk('exports')->assertExists($export->path);

        $rows = $this->readSpreadsheet(Storage::disk('exports')->path($export->path));
        $this->assertCount(26, $rows);
        $this->assertEquals('އަހުމަދު', $rows[1][2]);

        Event::assertDispatchedTimes(ExportStarted::class, 1);
        Event::assertDispatchedTimes(ExportProgressed::class, 2);
        Event::assertDispatchedTimes(ExportCompleted::class, 1);

        Notification::assertSentTo($user, ExportCompletedNotification::class);
    }

    #[Test]
    public function it_runs_a_serialized_query_on_the_queue(): void
    {
        $admin = User::factory()->create(['name' => 'ބ', 'is_admin' => true]);
        $admin->organizations()->sync([
            Organization::factory()->create(['name' => 'ޖާވާބު'])->id,
            Organization::factory()->create(['name' => 'ސިފައިން'])->id,
        ]);

        User::factory()->create(['name' => 'އ', 'is_admin' => true]);
        User::factory()->create(['name' => 'ދ', 'is_admin' => false]);

        $query = User::query()
            ->where('is_admin', true)
            ->orderBy('name', 'desc')
            ->with(['organizations' => fn ($query) => $query->where('name', 'ޖާވާބު')]);

        $export = UsersExport::make($query);
        $restored = unserialize(serialize($export));

        $this->assertEquals(iterator_to_array($export->rows(), false), iterator_to_array($restored->rows(), false));

        $names = array_column(iterator_to_array($restored->rows(), false), 2);
        $this->assertEquals(['އ', 'ބ'], $names);
        $this->assertEquals('ޖާވާބު', iterator_to_array($restored->rows(), false)[1][11]);
        $this->assertEquals(2, $restored->totalRows());
    }

    #[Test]
    public function it_restores_models_held_by_an_export(): void
    {
        $user = User::factory()->create(['name' => 'އައިޝާ']);

        $export = new OwnedUsersExport($user);

        $serialized = serialize($export);

        $this->assertStringNotContainsString('އައިޝާ', $serialized);
        $this->assertTrue(unserialize($serialized)->owner->is($user));
    }

    #[Test]
    public function it_marks_the_export_failed_and_notifies_the_user(): void
    {
        Notification::fake();
        Event::fake([ExportFailed::class]);

        $user = User::factory()->create();

        try {
            FailingExport::make()->queue($user);

            $this->fail('The sync queue should rethrow the exception.');
        } catch (RuntimeException $exception) {
            $this->assertEquals('The database went away', $exception->getMessage());
        }

        $export = Export::first();

        $this->assertEquals(ExportStatuses::FAILED, $export->status);
        $this->assertEquals('The database went away', $export->error);
        $this->assertNotNull($export->failed_at);
        $this->assertNull($export->path);
        $this->assertEmpty(Storage::disk('exports')->allFiles());

        Event::assertDispatched(ExportFailed::class);
        Notification::assertSentTo($user, ExportFailedNotification::class);
    }

    #[Test]
    public function it_stops_when_the_export_is_cancelled(): void
    {
        Notification::fake();

        config()->set('exports.queue.progress_every', 5);

        $this->seedUsers(30);

        Event::listen(ExportProgressed::class, function (ExportProgressed $event) {
            Export::query()->whereKey($event->export->id)->first()->cancel();
        });

        $export = UsersExport::make()->queue(User::first())->fresh();

        $this->assertEquals(ExportStatuses::CANCELLED, $export->status);
        $this->assertEquals(5, $export->processed_rows);
        $this->assertNotNull($export->cancelled_at);
        $this->assertNull($export->path);
        $this->assertEmpty(Storage::disk('exports')->allFiles());

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_skips_exports_cancelled_before_the_job_runs(): void
    {
        Queue::fake();
        Event::fake([ExportCancelled::class, ExportStarted::class]);

        $export = UsersExport::make()->queue();

        $this->assertTrue($export->cancel());
        $this->assertFalse($export->cancel());

        (new ProcessExport($export, UsersExport::make()))->handle(app(SpreadsheetWriter::class));

        $this->assertEquals(ExportStatuses::CANCELLED, $export->fresh()->status);

        Event::assertDispatchedTimes(ExportCancelled::class, 1);
        Event::assertNotDispatched(ExportStarted::class);
    }

    #[Test]
    public function it_does_not_notify_when_notifications_are_off(): void
    {
        Notification::fake();

        config()->set('exports.notifications.enabled', false);

        UsersExport::make()->queue(User::factory()->create());

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_completed_notification_has_a_signed_download_link(): void
    {
        Route::get('exports/{export}/signed', fn () => 'ok')->name('exports.signed-download');
        Route::getRoutes()->refreshNameLookups();

        $user = User::factory()->create();

        $export = UsersExport::make()->queue($user, 'csv', 'users')->fresh();

        $mail = (new ExportCompletedNotification($export))->toMail($user);

        $this->assertEquals('Your Users export is ready', $mail->subject);
        $this->assertStringContainsString("exports/{$export->uuid}/signed?expires=", $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);

        $this->assertEquals($export->uuid, (new ExportCompletedNotification($export))->toArray($user)['export']);
    }

    #[Test]
    public function the_notifications_are_translated(): void
    {
        app()->setLocale('dv');
        app('translator')->load('*', '*', 'dv');
        app('translator')->addLines(['*.Users' => 'ޔޫޒަރުން'], 'dv');

        $user = User::factory()->create();

        $export = UsersExport::make()->queue($user, 'csv')->fresh();

        $mail = (new ExportCompletedNotification($export))->toMail($user);

        $this->assertEquals('ތިބާގެ ޔޫޒަރުން އެކްސްޕޯޓް ތައްޔާރުވެއްޖެ', $mail->subject);
        $this->assertNull($mail->actionUrl);

        $export->markFailed('x');

        $failed = (new ExportFailedNotification($export))->toMail($user);

        $this->assertEquals('ތިބާގެ ޔޫޒަރުން އެކްސްޕޯޓް ނާކާމިޔާބުވެއްޖެ', $failed->subject);
    }
}
