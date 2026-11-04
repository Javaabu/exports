<?php

namespace Javaabu\Exports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Events\Concerns\BroadcastsExport;
use Javaabu\Exports\Models\Export;

/**
 * An export was cancelled
 *
 * Broadcast as `export.cancelled` when broadcasting is enabled.
 */
class ExportCancelled implements ShouldBroadcastNow
{
    use BroadcastsExport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Export $export,
    ) {}
}
