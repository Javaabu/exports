<?php

namespace Javaabu\Exports\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Events\Concerns\BroadcastsExport;
use Javaabu\Exports\Models\Export;

/**
 * An export's file is ready to download
 *
 * Broadcast as `export.completed` when broadcasting is enabled.
 */
class ExportCompleted implements ShouldBroadcastNow
{
    use BroadcastsExport;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Export $export,
    ) {}
}
