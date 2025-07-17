<?php

namespace Javaabu\Exports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Exports\Tests\TestSupport\Factories\OrganizationFactory;

class Organization extends Authenticatable
{
    use HasFactory;

    protected static function newFactory()
    {
        return new OrganizationFactory();
    }

    public function getMorphClass()
    {
        return 'organization';
    }

    public function getAdminLinkNameAttribute()
    {
        return $this->name;
    }
}
