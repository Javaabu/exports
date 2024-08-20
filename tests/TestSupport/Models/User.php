<?php

namespace Javaabu\Exports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Exports\Tests\TestSupport\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    protected $hidden = [
        'password',
    ];

    protected static function newFactory()
    {
        return new UserFactory();
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%');
    }

    public function getMorphClass()
    {
        return 'user';
    }
}
