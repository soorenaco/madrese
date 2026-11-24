<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Modules\School\Models\School;


class SchoolMembership extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'user_id',
        'status',
        'joined_at',
        'ended_at',
        'is_primary_manager',
        'manager_limits',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_primary_manager' => 'boolean',
        'manager_limits' => 'array',
    ];

    /**
     * کاربر مربوط به این عضویت
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * مدرسه مربوط به این عضویت
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * نقش‌های این عضویت
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'membership_roles',
            'membership_id',
            'role_id'
        )->withPivot(['granted_by_user_id', 'granted_at', 'revoked_at'])
         ->withTimestamps()
         ->wherePivotNull('revoked_at'); // فقط نقش‌های فعال
    }

    /**
     * تمام رکوردهای نقش (شامل لغو شده‌ها)
     */
    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class, 'membership_id');
    }

    /**
     * آیا عضویت فعال است؟
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * آیا این عضویت نقش خاصی دارد؟
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('code', $roleCode)->exists();
    }

    /**
     * قوانین کاری این پروژه: هر مدرسه دقیقاً یک مدیر دارد و یک کاربر
     * نمی‌تواند در بیش از یک مدرسه نقش «school_manager» (چه primary، چه
     * معاون) داشته باشد. نقش‌های دیگر (معلم، دانش‌آموز، …) محدود نیستند.
     *
     * این متد عضویت‌های فعال نقش school_manager کاربر را در مدارس غیرِ
     * مدرسه‌ی $exceptSchoolId برمی‌گرداند. خالی بودن نتیجه یعنی کاربر
     * آزاد است در این مدرسه نقش مدیر بگیرد.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SchoolMembership>
     */
    public static function managerMembershipsElsewhere(string $userId, ?string $exceptSchoolId = null)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
            ->when($exceptSchoolId !== null, fn ($q) => $q->where('school_id', '!=', $exceptSchoolId))
            ->with('school:id,name')
            ->get();
    }
}