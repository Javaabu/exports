<?php

namespace Javaabu\Exports\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Javaabu\Exports\Enums\Formats;
use RuntimeException;

/**
 * Paths for files written locally before they're sent or moved to a disk
 */
class TemporaryFile
{
    public static function path(Formats $format): string
    {
        $directory = rtrim(config('exports.temporary_directory') ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'export-' . Str::random(32) . '.' . $format->extension();
    }

    /**
     * Stream a local file to a disk
     *
     * @return int|null the size of the file in bytes
     */
    public static function moveToDisk(string $local_path, string $path, ?string $disk): ?int
    {
        $size = filesize($local_path) ?: null;
        $stream = fopen($local_path, 'r');

        try {
            $stored = Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($stored === false) {
            throw new RuntimeException("Could not write the export to {$path}.");
        }

        return $size;
    }

    public static function delete(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
