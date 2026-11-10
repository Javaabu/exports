<?php

namespace Javaabu\Exports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Exports\Contracts\HasExportHeadings;
use Javaabu\Exports\Tests\TestSupport\Enums\Atolls;

class Island extends Model implements HasExportHeadings
{
    protected $guarded = [];

    protected $casts = [
        'atoll' => Atolls::class,
        'inhabited' => 'boolean',
    ];

    public function exportHeadings(): array
    {
        return [
            'name' => 'ރަށުގެ ނަން',
        ];
    }
}
