<?php

namespace Javaabu\Exports\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Models\Export;

/**
 * Whether export progress is broadcast (over Laravel Reverb), the channels it uses,
 * and how often pages poll when it isn't
 */
class Broadcasting
{
    /** The class whose presence means laravel/reverb is installed */
    public static string $reverb_class = 'Laravel\\Reverb\\ReverbServiceProvider';

    /**
     * `exports.broadcasting.enabled`: true, false, or 'auto' (when laravel/reverb is
     * installed and the default broadcaster isn't null or log)
     */
    public static function enabled(): bool
    {
        $enabled = config('exports.broadcasting.enabled', 'auto');

        if ($enabled !== 'auto') {
            return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        if (! class_exists(static::$reverb_class)) {
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

    public static function channelPrefix(): string
    {
        return (string) config('exports.broadcasting.channel', 'exports');
    }

    /**
     * Authorize the private channels: `exports.{uuid}` for whoever may view the export,
     * `exports.owner.{type}.{id}` for the owner
     */
    public static function registerChannels(): void
    {
        $prefix = static::channelPrefix();
        $options = array_filter(['guards' => config('exports.broadcasting.guards')]);

        Broadcast::channel("{$prefix}.owner.{type}.{id}", function (Model $user, string $type, string $id) {
            return static::encodeType($user->getMorphClass()) === $type
                && (string) $user->getKey() === $id;
        }, $options);

        Broadcast::channel("{$prefix}.{uuid}", function (Model $user, string $uuid) {
            $export = Exports::exportModel()::query()->where('uuid', $uuid)->first();

            return $export !== null && $user->can('view', $export);
        }, $options);
    }

    /**
     * A morph type that's safe in a channel name: `App\Models\User` → `App-Models-User`
     */
    public static function encodeType(string $type): string
    {
        return str_replace(['\\', '.'], ['-', '='], $type);
    }

    /**
     * What progress events send: enough for a page to update a row
     *
     * @return array<string, mixed>
     */
    public static function payload(Export $export): array
    {
        return [
            'id' => $export->uuid,
            'status' => $export->status->value,
            'status_label' => $export->status->label(),
            'status_color' => $export->status->color(),
            'progress' => $export->progress(),
            'total_rows' => $export->total_rows,
            'processed_rows' => $export->processed_rows,
            'is_finished' => $export->status->isFinished(),
            'is_downloadable' => $export->isDownloadable(),
            'error' => $export->error,
        ];
    }
}
