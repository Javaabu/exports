<?php

namespace Javaabu\Exports\Support;

use Illuminate\Support\Str;
use Javaabu\Exports\Enums\Formats;

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

    public static function delete(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
