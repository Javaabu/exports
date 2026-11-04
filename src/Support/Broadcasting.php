<?php

namespace Javaabu\Exports\Support;

use Laravel\Reverb\ReverbServiceProvider;

/**
 * Whether export progress is broadcast, and how often pages should poll otherwise
 */
class Broadcasting
{
    /**
     * `exports.broadcasting.enabled`: true, false, or 'auto' (when laravel/reverb is
     * installed and the default broadcaster isn't null or log)
     */
    public static function enabled(): bool
    {
        $enabled = config('exports.broadcasting.enabled', 'auto');

        if ($enabled !== 'auto') {
            return (bool) $enabled;
        }

        if (! class_exists(ReverbServiceProvider::class)) {
            return false;
        }

        return ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }

    /**
     * Milliseconds between progress checks for pages that poll
     */
    public static function pollInterval(): int
    {
        return max((int) config('exports.broadcasting.poll_interval', 3000), 500);
    }
}
