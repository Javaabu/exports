<?php

namespace Javaabu\Exports\Commands;

use Illuminate\Console\Command;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Models\Export;

class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune
                            {--days= : Also delete exports created more than this many days ago}
                            {--pretend : List what would be deleted without deleting it}';

    protected $description = 'Delete expired exports and their files';

    public function handle(): int
    {
        $query = Exports::exportModel()::query()->expired();

        if ($days = $this->option('days')) {
            $query->orWhere('created_at', '<=', now()->subDays((int) $days));
        }

        $count = 0;

        $query->lazyById()->each(function (Export $export) use (&$count) {
            $this->line("Deleting export {$export->uuid} ({$export->name}, {$export->status->value})...");

            if (! $this->option('pretend')) {
                $export->delete();
            }

            $count++;
        });

        $this->components->info($this->option('pretend')
            ? "{$count} exports would be deleted."
            : "Deleted {$count} exports.");

        return self::SUCCESS;
    }
}
