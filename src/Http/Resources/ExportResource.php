<?php

namespace Javaabu\Exports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Routing\ExportRoutes;
use Javaabu\Exports\Support\Broadcasting;

/**
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $prefix = ExportRoutes::namePrefix($request);

        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'progress' => $this->progress(),
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'filename' => $this->filename,
            'size' => $this->size,
            'error' => $this->when($this->status === ExportStatuses::FAILED, fn () => $this->error),
            'is_finished' => $this->status->isFinished(),
            'is_downloadable' => $this->isDownloadable(),
            'is_cancellable' => $this->isCancellable(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'type' => $this->user_type,
                'id' => $this->user_id,
                'name' => $this->user->getAttribute('name'),
            ] : null),
            'can' => [
                'download' => (bool) $user?->can('download', $this->resource),
                'cancel' => (bool) $user?->can('cancel', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
            'links' => [
                'show' => $this->route("{$prefix}.show"),
                'download' => $this->isDownloadable() ? $this->route("{$prefix}.download") : null,
                'cancel' => $this->route("{$prefix}.cancel"),
                'destroy' => $this->route("{$prefix}.destroy"),
            ],
            'broadcast_channel' => Broadcasting::enabled() ? $this->broadcastChannel() : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function route(string $name): ?string
    {
        return Route::has($name) ? route($name, $this->uuid) : null;
    }
}
