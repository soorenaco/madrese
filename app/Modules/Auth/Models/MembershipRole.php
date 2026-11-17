<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipRole extends Model
{
    // چون کلید اصلی ترکیبی است، افزایش خودکار نداریم
    public $incrementing = false;

    protected $fillable = [
        'membership_id',
        'role_id',
        'granted_by_user_id',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * عضویت مربوطه
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(SchoolMembership::class, 'membership_id');
    }

    /**
     * نقش مربوطه
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * کاربری که این نقش را اعطا کرده
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /**
     * آیا این نقش هنوز فعال است؟
     */
    public function isActive(): bool
    {
        return is_null($this->revoked_at);
    }
}