<?php

namespace App\Modules\School\Services;

use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
/**
 * M6: مدیر اصلی / محدودیت معاون
 *
 * manager_limits نمونه:
 * {
 *   "can_invite_students": true,
 *   "can_manage_schedule": true,
 *   "can_promote_class": false,
 *   "can_edit_school": false
 * }
 */
class SchoolManagerGate
{
    public const LIMIT_KEYS = [
        'can_invite_students',
        'can_manage_schedule',
        'can_promote_class',
        'can_edit_school',
        'can_manage_deputies',
    ];

    public function __construct(
        protected PermissionService $permissions
    ) {}

    public function isPlatformAdmin(User $user): bool
    {
        return $this->permissions->isPlatformAdmin($user);
    }

    public function primaryMembership(User $user, string $schoolId): ?SchoolMembership
    {
        return SchoolMembership::query()
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
            ->first();
    }

    public function isPrimaryManager(User $user, string $schoolId): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $m = $this->primaryMembership($user, $schoolId);

        return $m?->is_primary_manager === true;
    }

    /**
     * قانون کاری: هر کاربر فقط در «یک» مدرسه می‌تواند نقش school_manager
     * داشته باشد (چه به‌عنوان مدیر اصلی و چه معاون). این متد جلوی انتساب
     * نقش مدیر در مدرسه‌ی دوم را می‌گیرد.
     *
     * @param  string  $schoolId  مدرسه‌ای که قرار است کاربر در آن مدیر شود.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 اگر کاربر قبلاً مدیر مدرسه‌ی دیگری است.
     */
    public function assertUserNotManagerElsewhere(User $user, string $schoolId): void
    {
        $existing = SchoolMembership::managerMembershipsElsewhere($user->id, $schoolId);

        if ($existing->isNotEmpty()) {
            $other = $existing->first()->school?->name ?? 'مدرسه‌ی دیگری';
            abort(422, "این کاربر قبلاً در «{$other}» نقش مدیر دارد و نمی‌تواند در دو مدرسه هم‌زمان مدیر باشد.");
        }
    }

    /**
     * آیا این عمل برای کاربر در مدرسه مجاز است؟
     */
    public function allows(User $user, string $schoolId, string $ability): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        if (! $this->permissions->managesSchool($user, $schoolId)) {
            return false;
        }

        $m = $this->primaryMembership($user, $schoolId);
        if (! $m) {
            return false;
        }

        // مدیر اصلی: همه چیز
        if ($m->is_primary_manager) {
            return true;
        }

        // هنوز محدودیتی برایش ست نشده → دسترسی کامل (سازگاری با مدیران فعلی)
        if ($m->manager_limits === null) {
            return true;
        }

        return (bool) ($m->manager_limits[$ability] ?? false);
    }

    /**
     * تنظیم مدیر اصلی (فقط ادمین یا مدیر اصلی فعلی)
     */
    public function setPrimaryManager(User $actor, string $schoolId, User $target): void
    {
        if (! $this->isPlatformAdmin($actor) && ! $this->isPrimaryManager($actor, $schoolId)) {
            abort(403, 'فقط مدیر اصلی یا ادمین می‌تواند مدیر اصلی را تغییر دهد.');
        }

        // همه را از primary خارج کن
        SchoolMembership::query()
            ->where('school_id', $schoolId)
            ->where('is_primary_manager', true)
            ->update(['is_primary_manager' => false]);

        $m = SchoolMembership::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $target->id)
            ->whereIn('status', ['active', 'invited'])
            ->whereNull('ended_at')
            ->firstOrFail();

        $m->update([
            'is_primary_manager' => true,
            'manager_limits'     => null,
        ]);
    }

    /**
     * تنظیم محدودیت معاون
     */
    public function setDeputyLimits(User $actor, string $schoolId, User $deputy, array $limits): void
    {
        // چک ۱: اجازه مدیریت معاونان
        if (! $this->allows($actor, $schoolId, 'can_manage_deputies') && ! $this->isPrimaryManager($actor, $schoolId)) {
            abort(403, 'اجازه مدیریت معاونان را ندارید.');
        }

        // چک ۲: کاربر نمی‌تونه به خودش محدودیت بده (self-escalation)
        if ($actor->id === $deputy->id) {
            abort(403, 'نمی‌توانید محدودیت‌های خودتان را تغییر دهید.');
        }

        $m = SchoolMembership::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $deputy->id)
            ->whereIn('status', ['active', 'invited'])
            ->whereNull('ended_at')
            ->firstOrFail();

        if ($m->is_primary_manager) {
            abort(422, 'برای مدیر اصلی محدودیت تعریف نمی‌شود.');
        }

        $clean = [];
        foreach (self::LIMIT_KEYS as $key) {
            $clean[$key] = (bool) ($limits[$key] ?? false);
        }

        $m->update(['manager_limits' => $clean]);
    }
}
