<?php

namespace Javaabu\Exports\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Events\ExportCancelled;
use Javaabu\Exports\Support\Broadcasting;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A queued export: who asked for it, how far it got, and where its file is
 *
 * @property int $id
 * @property string $uuid
 * @property ?string $user_type
 * @property mixed $user_id
 * @property ?string $type
 * @property string $export_class
 * @property string $name
 * @property Formats $format
 * @property ExportStatuses $status
 * @property ?int $total_rows
 * @property int $processed_rows
 * @property ?string $disk
 * @property ?string $path
 * @property ?string $filename
 * @property ?int $size
 * @property ?string $error
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $failed_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Model $user
 */
class Export extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'processed_rows' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ExportStatuses::class,
            'format' => Formats::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Export $export) {
            $export->uuid ??= (string) Str::uuid();
            $export->status ??= ExportStatuses::PENDING;
        });

        static::deleting(function (Export $export) {
            $export->deleteFile();
        });
    }

    public function getTable(): string
    {
        return config('exports.table', parent::getTable());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Exports started by the user
     */
    public function scopeOwnedBy(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey());
    }

    /**
     * Exports whose file has expired, or that never finished and are older than the
     * expiry period
     */
    public function scopeExpired(Builder $query, ?DateTimeInterface $now = null): void
    {
        $now ??= now();

        $query->where(function (Builder $query) use ($now) {
            $query->where('expires_at', '<=', $now)
                ->orWhere(function (Builder $query) use ($now) {
                    $query->whereNull('expires_at')
                        ->where('created_at', '<=', Carbon::instance($now)->subDays(static::expiresAfterDays()));
                });
        });
    }

    public function scopeStatus(Builder $query, ExportStatuses|string $status): void
    {
        $query->where('status', $status instanceof ExportStatuses ? $status->value : $status);
    }

    public function isOwnedBy(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->user_type === $user->getMorphClass()
            && (string) $this->user_id === (string) $user->getKey();
    }

    /**
     * Progress from 0 to 100, or null while the number of rows is unknown
     */
    public function progress(): ?int
    {
        if ($this->status === ExportStatuses::COMPLETED) {
            return 100;
        }

        if (! $this->total_rows) {
            return $this->total_rows === 0 ? 100 : null;
        }

        return (int) min(floor($this->processed_rows / $this->total_rows * 100), 100);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the file can be downloaded: completed, stored and not expired
     */
    public function isDownloadable(): bool
    {
        return $this->status === ExportStatuses::COMPLETED
            && $this->path
            && ! $this->hasExpired();
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    /**
     * Whether the export was cancelled, checked against the database
     */
    public function wasCancelled(): bool
    {
        $status = static::query()->whereKey($this->getKey())->toBase()->value('status');

        return $status === ExportStatuses::CANCELLED->value;
    }

    /**
     * Stop a pending or processing export. The job stops at the next chunk.
     */
    public function cancel(): bool
    {
        if (! $this->isCancellable()) {
            return false;
        }

        $this->forceFill([
            'status' => ExportStatuses::CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        event(new ExportCancelled($this));

        return true;
    }

    public function markProcessing(?int $total_rows): void
    {
        $this->forceFill([
            'status' => ExportStatuses::PROCESSING,
            'total_rows' => $total_rows,
            'processed_rows' => 0,
            'started_at' => now(),
            'error' => null,
        ])->save();
    }

    public function recordProgress(int $processed_rows): void
    {
        $this->forceFill(['processed_rows' => $processed_rows])->save();
    }

    public function markCompleted(string $disk, string $path, string $filename, int $rows, ?int $size): void
    {
        $this->forceFill([
            'status' => ExportStatuses::COMPLETED,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'processed_rows' => $rows,
            'total_rows' => $rows,
            'size' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addDays(static::expiresAfterDays()),
        ])->save();
    }

    public function markFailed(Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->forceFill([
            'status' => ExportStatuses::FAILED,
            'error' => Str::limit($message, 1000),
            'failed_at' => now(),
        ])->save();
    }

    /**
     * Stream the file to the browser
     */
    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->filename, [
            'Content-Type' => $this->format->mimeType(),
        ]);
    }

    public function fileExists(): bool
    {
        return $this->path && Storage::disk($this->disk)->exists($this->path);
    }

    public function deleteFile(): void
    {
        if (! $this->path) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }

    /**
     * A signed link to download the file without logging in, for notifications.
     * It expires after `exports.signed_url_minutes`, or when the file expires if sooner.
     * Null when the export isn't downloadable or the signed route isn't registered.
     */
    public function temporaryDownloadUrl(?DateTimeInterface $expires_at = null): ?string
    {
        if (! $this->isDownloadable()) {
            return null;
        }

        $route = config('exports.routes.signed_download', 'exports.signed-download');

        if (! Route::has($route)) {
            return null;
        }

        $expires_at ??= now()->addMinutes((int) config('exports.signed_url_minutes', 1440));

        if ($this->expires_at && $this->expires_at->lessThan($expires_at)) {
            $expires_at = $this->expires_at;
        }

        return URL::temporarySignedRoute($route, $expires_at, ['export' => $this->uuid]);
    }

    /**
     * The private broadcast channel for this export's progress
     */
    public function broadcastChannel(): string
    {
        return Broadcasting::channelPrefix() . ".{$this->uuid}";
    }

    /**
     * The private broadcast channel for every export of this export's owner
     */
    public function ownerBroadcastChannel(): ?string
    {
        if (! $this->user_type || $this->user_id === null) {
            return null;
        }

        return Broadcasting::channelPrefix() . '.owner.' . Broadcasting::encodeType($this->user_type) . ".{$this->user_id}";
    }

    public static function expiresAfterDays(): int
    {
        return max((int) config('exports.expire_after_days', 7), 1);
    }
}
