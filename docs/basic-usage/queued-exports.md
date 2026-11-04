---
title: Queued exports
sidebar_position: 3
---

Big exports run on the queue. The user gets a notification with a download link when the file is ready, and can follow progress on the [exports page](exports-page-and-api.md).

# Queueing

```php
use App\Exports\UsersExport;

public function store(Request $request)
{
    $this->authorize('viewAny', User::class);

    $export = UsersExport::make(User::query()->where('is_active', true))
        ->queue($request->user(), format: 'xlsx', filename: 'active-users');

    return redirect()->route('exports.show', $export);
}
```

```php
queue(?Model $user = null, Formats|string|null $format = null, ?string $filename = null, ?string $disk = null): Export
```

`queue()` creates a pending `Javaabu\Exports\Models\Export` and dispatches `Javaabu\Exports\Jobs\ProcessExport` on `exports.queue.connection` and `exports.queue.queue`. The user can be any model, from any guard. The export is serialized onto the queue: a query you passed in is kept as SQL, bindings, order and eager loads (closures included), and models held by the export become identifiers. Keep the rest of its state small: ids and filters, not loaded collections. The file is written in the locale that was active when `queue()` was called.

# What the job does

1. Skips the export if it was cancelled before the job ran.
2. Marks it `processing` and counts the rows (`totalRows()`).
3. Streams the rows to a local file, saving `processed_rows` every `exports.queue.progress_every` rows (the chunk size by default) and checking whether it was cancelled.
4. Moves the file to `exports/{uuid}/{filename}` on the export's disk.
5. Marks it `completed`, with `expires_at` set `exports.expire_after_days` days ahead.
6. Notifies the user.

One job writes the whole file: XLSX and ODS files can't be appended to by parallel jobs, and reading in chunks already keeps memory flat. It runs once (`$tries = 1`) with a timeout of `exports.queue.timeout` seconds (3600). If it fails, the export is marked `failed` with the error message, and the user is notified.

To change the job, extend `ProcessExport` and set `exports.queue.job`.

# The Export model

| Attribute | |
| --- | --- |
| `uuid` | Used in URLs (`getRouteKeyName()`) |
| `user` | Who started it (morph: `user_type`, `user_id`) |
| `type` | Its name in `Exports::register()`, if registered |
| `export_class`, `name` | The export class and its `label()` |
| `format` | A `Formats` case |
| `status` | An `ExportStatuses` case: `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `CANCELLED` |
| `total_rows`, `processed_rows` | Progress |
| `disk`, `path`, `filename`, `size` | The file |
| `error` | Why it failed |
| `started_at`, `completed_at`, `failed_at`, `cancelled_at`, `expires_at` | |

```php
use Javaabu\Exports\Models\Export;

$export->progress();               // 0–100, or null while the row count is unknown
$export->isDownloadable();         // completed, stored and not expired
$export->download();               // a streamed download response
$export->temporaryDownloadUrl();   // a signed link, or null
$export->cancel();                 // false if it already finished
$export->isOwnedBy($user);

Export::query()->ownedBy($user)->status(ExportStatuses::COMPLETED)->latest()->get();
```

Deleting an export deletes its file. To use your own model, extend `Export` and set `exports.models.export`; to rename the table, set `exports.table`.

# Cancelling

```php
$export->cancel();
```

A pending export never starts. A processing one stops at its next progress check, and its temporary file is deleted. Users cancel from the exports page or `POST /exports/{export}/cancel`.

# Notifications

When the export completes, the user gets `ExportCompletedNotification`, with a signed link to download the file without logging in. When it fails, they get `ExportFailedNotification`. The user model needs Laravel's `Notifiable` trait; otherwise nothing is sent.

```php
// config/exports.php
'notifications' => [
    'enabled' => true,
    'channels' => ['mail'],          // e.g. ['mail', 'database']
    'completed' => \Javaabu\Exports\Notifications\ExportCompletedNotification::class,
    'failed' => \Javaabu\Exports\Notifications\ExportFailedNotification::class, // null to turn off
],
```

The signed link uses the `exports.signed-download` route, which [`Route::exports()`](exports-page-and-api.md) registers. It lasts `exports.signed_url_minutes` (a day), or until the file expires if that's sooner. If you name the routes differently, set `exports.routes.signed_download`; if the route doesn't exist, the email has no button. Notifications use the user's preferred locale, so Dhivehi users get Dhivehi emails.

# Events

| Event | When |
| --- | --- |
| `ExportStarted` | The job started |
| `ExportProgressed` | Another `progress_every` rows were written |
| `ExportCompleted` | The file is on the disk |
| `ExportFailed` | The job failed |
| `ExportCancelled` | `cancel()` was called |

They're in `Javaabu\Exports\Events` and each has a public `$export`. They're also broadcast when [live progress](../advanced-usage/live-progress.md) is on.

# Expiry and pruning

Files expire `exports.expire_after_days` (7) days after they complete. Expired files can't be downloaded. Schedule the prune command to delete them:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('exports:prune')->daily();
```

```bash
php artisan exports:prune            # expired exports, and unfinished ones older than the expiry period
php artisan exports:prune --days=2   # also everything created more than 2 days ago
php artisan exports:prune --pretend  # list them without deleting
```

# Testing

Fake the queue to assert an export was queued, or let the `sync` queue run it:

```php
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Jobs\ProcessExport;

Queue::fake();

$this->actingAs($user)->post('/users/export');

Queue::assertPushed(ProcessExport::class);
```

```php
Storage::fake('local');

$export = UsersExport::make()->queue($user)->fresh();

$this->assertTrue($export->isDownloadable());
Storage::disk('local')->assertExists($export->path);
```
