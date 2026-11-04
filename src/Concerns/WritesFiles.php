<?php

namespace Javaabu\Exports\Concerns;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Jobs\ProcessExport;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Writers\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes an exportable to a local file, a download response or a storage disk
 *
 * @mixin Exportable
 */
trait WritesFiles
{
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Write the export to a local path
     *
     * @return int the number of data rows written
     */
    public function writeTo(string $path, Formats|string|null $format = null): int
    {
        return app(SpreadsheetWriter::class)->write(
            $path,
            Formats::resolve($format, $path),
            $this->headings(),
            $this->rows(),
        );
    }

    /**
     * A download response for the export, written before the response is sent
     */
    public function download(?string $filename = null, Formats|string|null $format = null): BinaryFileResponse
    {
        $format = Formats::resolve($format, $filename);
        $path = TemporaryFile::path($format);

        $this->writeTo($path, $format);

        return response()
            ->download($path, $this->downloadName($filename, $format), ['Content-Type' => $format->mimeType()])
            ->deleteFileAfterSend();
    }

    /**
     * Write the export to a storage disk
     *
     * @return string the path on the disk
     */
    public function store(?string $path = null, ?string $disk = null, Formats|string|null $format = null): string
    {
        $format = Formats::resolve($format, $path);
        $path ??= trim((string) config('exports.directory', 'exports'), '/') . '/' . $this->downloadName(null, $format);
        $local_path = TemporaryFile::path($format);

        try {
            $this->writeTo($local_path, $format);

            $this->putOnDisk($local_path, $path, $disk);
        } finally {
            TemporaryFile::delete($local_path);
        }

        return $path;
    }

    /**
     * Write the export on the queue. Returns the Export record that tracks it; the user
     * (any model) is notified when the file is ready.
     *
     * The export object is serialized onto the queue: keep its state small (ids, filters,
     * a query builder) rather than loaded collections.
     */
    public function queue(
        ?Model $user = null,
        Formats|string|null $format = null,
        ?string $filename = null,
        ?string $disk = null,
    ): Export {
        $format = Formats::resolve($format, $filename);

        $export = Exports::newExportModel();

        $export->forceFill([
            'type' => Exports::typeOf($this),
            'export_class' => static::class,
            'name' => $this->label(),
            'format' => $format,
            'status' => ExportStatuses::PENDING,
            'disk' => $disk ?? config('exports.disk') ?? config('filesystems.default'),
            'filename' => $this->downloadName($filename, $format),
        ]);

        if ($user) {
            $export->user()->associate($user);
        }

        $export->save();

        $job_class = config('exports.queue.job', ProcessExport::class);

        dispatch(new $job_class($export, $this))
            ->onConnection(config('exports.queue.connection'))
            ->onQueue(config('exports.queue.queue'));

        return $export;
    }

    /**
     * The file name for a download: the given name, with the format's extension added if missing,
     * or the export's file name and the current time
     */
    public function downloadName(?string $filename, Formats $format): string
    {
        if (! $filename) {
            $filename = $this->filename() . '-' . now()->format('Y-m-d-His');
        }

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === $format->extension()) {
            return $filename;
        }

        return "{$filename}.{$format->extension()}";
    }

    protected function putOnDisk(string $local_path, string $path, ?string $disk): void
    {
        TemporaryFile::moveToDisk($local_path, $path, $disk ?? config('exports.disk'));
    }
}
