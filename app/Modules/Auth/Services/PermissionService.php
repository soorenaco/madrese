<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Modules\School\Models\School;

class PermissionService
{
    /**
     * مدت کش دسترسی‌های یک کاربر (ثانیه)
     */
    protected int $cacheTtl = 300; // 5 دقیقه

    /**
     * آیا کاربر مجوز مشخصی دارد؟
     *
     * منطق:
     * 1. اگر نقش platform-level (admin) داشته باشد → همه مجوزها
     * 2. در غیر این صورت مجوزها از طریق Membershipهای فعال + Roleهای فعال جمع‌آوری می‌شوند
     */
    public function userHasPermission(User $user, string $permissionCode, ?string $schoolId = null): bool
    {
        $permissions = $this->getUserPermissionCodes($user, $schoolId);

        return in_array($permissionCode, $permissions, true);
    }

    /**
     * آیا کاربر نقش مشخصی دارد؟
     */
    public function userHasRole(User $user, string $roleCode, ?string $schoolId = null): bool
    {
        $roles = $this->getUserRoleCodes($user, $schoolId);

        return in_array($roleCode, $roles, true);
    }

    /**
     * آیا کاربر ادمین پلتفرم است؟
    */
    public function isPlatformAdmin(User $user): bool
    {
        return $this->userHasRoleDirect($user, 'admin', null);
    }

        /**
     * شناسه مدارس تحت مدیریت کاربر.
     * ادمین: null = همه | مدیر: لیست school_id | بقیه: []
     *
     * @return list<string>|null
     */
    public function managedSchoolIds(User $user): ?array
    {
        if ($this->isPlatformAdmin($user)) {
            return null;
        }

        return SchoolMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('roles', function ($q) {
                $q->where('roles.code', 'school_manager');
            })            ->pluck('school_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * آیا کاربر این مدرسه را مدیریت می‌کند؟
     */
    public function managesSchool(User $user, string $schoolId): bool
    {
        $ids = $this->managedSchoolIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($schoolId, $ids, true);
    }






    /**
     * لیست کد مجوزهای فعال کاربر
        */
    public function getUserPermissionCodes(User $user, ?string $schoolId = null): array
    {
        $cacheKey = $this->permissionCacheKey($user->id, $schoolId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $schoolId) {
            // D8: ادمین پلتفرم همیشه همه‌ی مجوزها رو داره (بدون توجه به schoolId)
            if ($this->isPlatformAdmin($user)) {
                return Permission::query()->pluck('code')->all();
            }

            $roleIds = $this->getActiveRoleIdsForUser($user, $schoolId);

            if (empty($roleIds)) {
                return [];
            }

            // D7: deny > allow
            // اول همه‌ی مجوزها رو با effect جمع کن
            $permissions = Permission::query()
                ->whereHas('roles', function ($q) use ($roleIds) {
                    $q->whereIn('roles.id', $roleIds);
                })
                ->with(['roles' => function ($q) use ($roleIds) {
                    $q->whereIn('roles.id', $roleIds);
                }])
                ->get();

            $allowed = [];
            $denied = [];

            foreach ($permissions as $permission) {
                foreach ($permission->roles as $role) {
                    $effect = $role->pivot->effect ?? 'allow';

                    if ($effect === 'deny') {
                        $denied[$permission->code] = true;
                    } elseif ($effect === 'allow') {
                        $allowed[$permission->code] = true;
                    }
                }
            }

            // deny > allow
            return array_values(array_diff(array_keys($allowed), array_keys($denied)));
        });
    }
    /**
     * لیست کد نقش‌های فعال کاربر
     */
    public function getUserRoleCodes(User $user, ?string $schoolId = null): array
    {
        $cacheKey = $this->roleCacheKey($user->id, $schoolId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $schoolId) {
            return $this->getActiveRolesForUser($user, $schoolId)
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * پاک کردن کش دسترسی‌های کاربر (بعد از تغییر نقش/مجوز)
     */
    public function clearUserCache(User $user, ?string $schoolId = null): void
    {
        Cache::forget($this->permissionCacheKey($user->id, $schoolId));
        Cache::forget($this->roleCacheKey($user->id, $schoolId));

        // اگر schoolId مشخص نبود، کش کلی بدون school را هم پاک کن
        if ($schoolId !== null) {
            Cache::forget($this->permissionCacheKey($user->id, null));
            Cache::forget($this->roleCacheKey($user->id, null));
        }
    }



    /**
     * نقشه code => title همه نقش‌ها (کش یک‌ساعته؛ عنوان‌ها به‌ندرت عوض می‌شوند)
     *
     * @return array<string, string>
     */
    public function getRoleTitles(): array
    {
        return Cache::remember('role_titles', 3600, function () {
            return Role::query()->pluck('title', 'code')->all();
        });
    }

    /**
     * پاک کردن کش عنوان نقش‌ها (بعد از تغییر title در جدول roles)
     */
    public function clearRoleTitlesCache(): void
    {
        Cache::forget('role_titles');
    }


    /**
     * نقش‌های فعال کاربر (مدل Role)
        */
    public function getActiveRolesForUser(User $user, ?string $schoolId = null): Collection
    {
        $query = SchoolMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $memberships = $query->with(['roles' => function ($q) {
            $q->wherePivotNull('revoked_at');
        }])->get();

        $roles = collect();

        foreach ($memberships as $membership) {
            $roles = $roles->merge($membership->roles);
        }

        return $roles->unique('id');
    }
    /**
     * شناسه نقش‌های فعال
     */
    protected function getActiveRoleIdsForUser(User $user, ?string $schoolId = null): array
    {
        return $this->getActiveRolesForUser($user, $schoolId)
            ->pluck('id')
            ->all();
    }

    /**
     * بررسی مستقیم نقش بدون وابستگی به getUserRoleCodes (جلوگیری از recursion در cache)
    */
    protected function userHasRoleDirect(User $user, string $roleCode, ?string $schoolId): bool
    {
        return $this->getActiveRolesForUser($user, $schoolId)
            ->contains(fn (Role $role) => $role->code === $roleCode);
    }

    protected function permissionCacheKey(string $userId, ?string $schoolId): string
    {
        return 'user_permissions:' . $userId . ':' . ($schoolId ?? 'all');
    }

    protected function roleCacheKey(string $userId, ?string $schoolId): string
    {
        return 'user_roles:' . $userId . ':' . ($schoolId ?? 'all');
    }
}
