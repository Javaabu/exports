<?php

namespace Javaabu\Exports\Jobs;

use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Events\ExportCompleted;
use Javaabu\Exports\Events\ExportFailed;
use Javaabu\Exports\Events\ExportProgressed;
use Javaabu\Exports\Events\ExportStarted;
use Javaabu\Exports\Exceptions\ExportCancelledException;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Writers\SpreadsheetWriter;
use Throwable;

/**
 * Writes a queued export to its disk, recording progress as it goes
 *
 * One job writes the whole file: XLSX and ODS files can't be appended to from parallel
 * jobs. Rows are streamed, progress is saved every `exports.queue.progress_every` rows,
 * and a cancelled export stops at the next of those checks. Retrying starts again.
 */
class ProcessExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Export $export,
        public Exportable $exportable,
    ) {
        $this->timeout = (int) config('exports.queue.timeout', 3600);
    }

    public function handle(SpreadsheetWriter $writer): void
    {
        $export = $this->export;

        $export->refresh();

        if ($export->status !== ExportStatuses::PENDING) {
            return;
        }

        $export->markProcessing($this->exportable->totalRows());

        event(new ExportStarted($export));

        $local_path = TemporaryFile::path($export->format);

        try {
            $rows = $writer->write(
                $local_path,
                $export->format,
                $this->exportable->headings(),
                $this->trackProgress($this->exportable->rows()),
            );

            if ($export->wasCancelled()) {
                return;
            }

            $path = $this->storagePath();
            $size = TemporaryFile::moveToDisk($local_path, $path, $export->disk);

            $export->markCompleted($export->disk, $path, $export->filename, $rows, $size);
        } catch (ExportCancelledException) {
            return;
        } finally {
            TemporaryFile::delete($local_path);
        }

        event(new ExportCompleted($export));

        $this->notifyUser('completed');
    }

    public function failed(?Throwable $exception): void
    {
        $export = $this->export->fresh();

        if (! $export) {
            return;
        }

        if ($export->status->isFinished()) {
            return;
        }

        $this->export = $export;

        $export->markFailed($exception ?? __('The export failed.'));

        event(new ExportFailed($export));

        $this->notifyUser('failed');
    }

    /**
     * Pass the rows through, saving progress and checking for cancellation as they go
     *
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return Generator<int, array<int, mixed>>
     */
    protected function trackProgress(iterable $rows): Generator
    {
        $every = $this->progressEvery();
        $count = 0;

        foreach ($rows as $row) {
            yield $row;

            $count++;

            if ($count % $every !== 0) {
                continue;
            }

            if ($this->export->wasCancelled()) {
                throw new ExportCancelledException;
            }

            $this->export->recordProgress($count);

            event(new ExportProgressed($this->export));
        }
    }

    protected function progressEvery(): int
    {
        $every = config('exports.queue.progress_every') ?: config('exports.chunk_size', 1000);

        return max((int) $every, 1);
    }

    /**
     * `exports/{uuid}/{filename}`, so names never collide
     */
    protected function storagePath(): string
    {
        $directory = trim((string) config('exports.directory', 'exports'), '/');

        return ltrim("{$directory}/{$this->export->uuid}/{$this->export->filename}", '/');
    }

    protected function notifyUser(string $type): void
    {
        if (! config('exports.notifications.enabled', true)) {
            return;
        }

        $notification = config("exports.notifications.{$type}");

        if (! $notification) {
            return;
        }

        $user = $this->export->user;

        if (! $user || ! method_exists($user, 'notify')) {
            return;
        }

        $user->notify(new $notification($this->export));
    }
}
