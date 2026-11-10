<?php

namespace Javaabu\Exports\Events\Concerns;

use Illuminate\Broadcasting\PrivateChannel;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Support\Broadcasting;

/**
 * Broadcasts an export event on the export's channel and its owner's channel, when
 * broadcasting is enabled (see Broadcasting::enabled())
 *
 * @property Export $export
 */
trait BroadcastsExport
{
    public function broadcastWhen(): bool
    {
        return Broadcasting::enabled();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel($this->export->broadcastChannel())];

        if ($owner_channel = $this->export->ownerBroadcastChannel()) {
            $channels[] = new PrivateChannel($owner_channel);
        }

        return $channels;
    }

    /**
     * `export.progressed`, `export.completed`, …
     */
    public function broadcastAs(): string
    {
        return 'export.' . strtolower(substr(class_basename(static::class), strlen('Export')));
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return Broadcasting::payload($this->export);
    }
}
