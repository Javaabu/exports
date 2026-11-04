<?php

namespace Javaabu\Exports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Models\Export;

/**
 * An export's file is ready to download
 */
class ExportCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Export $export,
    ) {}
}
