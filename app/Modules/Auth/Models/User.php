<?php

namespace App\Modules\Auth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = [
        'mobile_e164',
        'display_name',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    /**
     * عضویت‌های این کاربر در مدارس مختلف
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SchoolMembership::class, 'user_id');
    }

    /**
     * آیا حساب فعال است؟
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * آیا حساب مسدود شده؟
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}