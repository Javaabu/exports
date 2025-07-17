<?php

namespace Javaabu\Exports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Exports\Tests\TestSupport\Factories\RoleFactory;

class Role extends Authenticatable
{
    use HasFactory;

    protected static function newFactory()
    {
        return new RoleFactory();
    }

    public function getMorphClass()
    {
        return 'role';
    }

    public function getAdminLinkNameAttribute()
    {
        return $this->name;
    }
}
