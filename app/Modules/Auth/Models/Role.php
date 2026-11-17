<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'title',
        'scope',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * مجوزهای این نقش
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )->withPivot('effect')->withTimestamps();
    }

    /**
     * عضویت‌هایی که این نقش را دارند
     */
    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class, 'role_id');
    }

    /**
     * آیا این نقش سیستمی است؟
     */
    public function isSystem(): bool
    {
        return $this->is_system === true;
    }
}