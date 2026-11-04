<?php

namespace Javaabu\Exports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Javaabu\Exports\Models\Export;

/**
 * An export wrote another chunk of rows
 */
class ExportProgressed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Export $export,
    ) {}
}
