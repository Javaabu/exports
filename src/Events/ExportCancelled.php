<?php

namespace Javaabu\Exports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Models\Export;

/**
 * An export was cancelled
 */
class ExportCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Export $export,
    ) {}
}
