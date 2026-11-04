<?php

namespace Javaabu\Exports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Exports\Tests\TestSupport\Enums\UserStatuses;
use Javaabu\Exports\Tests\TestSupport\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => UserStatuses::class,
        'is_admin' => 'boolean',
        'birthday' => 'date',
        'settings' => 'array',
    ];

    protected static function newFactory(): UserFactory
    {
        return new UserFactory;
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%');
    }

    public function getMorphClass()
    {
        return 'user';
    }

    public function userRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    public function greeting(): string
    {
        return "ހެލޯ {$this->name}";
    }
}
