<?php

namespace Javaabu\Exports\Concerns;

use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Writers\SpreadsheetWriter;
use RuntimeException;
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
        $stream = fopen($local_path, 'r');

        try {
            $stored = Storage::disk($disk ?? config('exports.disk'))->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($stored === false) {
            throw new RuntimeException("Could not write the export to {$path}.");
        }
    }
}
