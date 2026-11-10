# Queued exports, the Export model and live progress

```php
$export = UsersExport::make($query)->queue($request->user(), format: 'csv', filename: 'ބޭނުންތެރިން');
```

- Creates a `Javaabu\Exports\Models\Export` (`status` pending) and dispatches `Javaabu\Exports\Jobs\ProcessExport` on `exports.queue.connection` / `exports.queue.queue`.
- The job writes the whole file, saving `processed_rows` every `exports.queue.progress_every` rows, stops if the export is cancelled, stores it at `exports/{uuid}/{filename}` on the disk, and notifies the user (`Notifiable` needed) with a signed link.
- Files expire after `exports.expire_after_days`; `exports:prune` deletes them.
- The file is written in the locale active when `queue()` was called.

```php
$export->progress();          // 0–100 or null
$export->status;              // ExportStatuses::PENDING|PROCESSING|COMPLETED|FAILED|CANCELLED
$export->isDownloadable();
$export->download();          // streamed response
$export->cancel();
Export::query()->ownedBy($user)->latest()->get();
```

Events in `Javaabu\Exports\Events`: `ExportStarted`, `ExportProgressed`, `ExportCompleted`, `ExportFailed`, `ExportCancelled`, each with `$export`.

## Live progress

With laravel/reverb installed and broadcasting on, the events broadcast as `export.progressed` etc. on `private-exports.{uuid}` and `private-exports.owner.{type}.{id}`. Listen with a leading dot:

```ts
useEcho(`exports.${id}`, ['.export.progressed', '.export.completed'], (payload) => update(payload));
```

Without Reverb, poll `GET /exports/{uuid}` (JSON) every `meta.poll_interval` ms until `is_finished`.

## Testing

```php
Queue::fake();
UsersExport::make()->queue($user);
Queue::assertPushed(ProcessExport::class);

Storage::fake('local');
$export = UsersExport::make()->queue($user)->fresh(); // sync queue runs it
Storage::disk('local')->assertExists($export->path);
```
