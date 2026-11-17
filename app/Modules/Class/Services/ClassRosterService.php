<?php

namespace App\Modules\Class\Services;

use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Modules\Auth\Services\PermissionService;   

/**
 * دعوت دانش‌آموز به کلاس + عضویت مدرسه، و تعیین معلم کلاس.
 */
class ClassRosterService
{
    public function __construct(
        protected PermissionService $permissions
    ) {}

    /**
     * نرمال‌سازی موبایل ایران به E.164 (+98...)
     */
    public function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+98'.substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '+98'.$digits;
        }

        throw ValidationException::withMessages([
            'mobile' => 'شماره موبایل معتبر نیست.',
        ]);
    }

    /**
     * دعوت / افزودن دانش‌آموز به کلاس:
     * - پیدا کردن یا ساخت User
     * - عضویت فعال در مدرسه با نقش student
     * - enrollment در کلاس
     */
    public function inviteStudent(SchoolClass $class, string $mobile, ?string $displayName = null): ClassEnrollment
    {
        $e164 = $this->normalizeMobile($mobile);

        $user = User::query()->where('mobile_e164', $e164)->first();

        if (! $user) {
            $user = User::create([
                'mobile_e164'  => $e164,
                'display_name' => $displayName ?: ('دانش‌آموز '.substr($e164, -4)),
                'status'       => 'invited',
            ]);
        } elseif ($displayName && $user->display_name !== $displayName) {
            // فقط اگر هنوز اسم پیش‌فرض/خالی است عوض نکنیم مگر فرستاده شده
            if ($displayName !== '') {
                $user->update(['display_name' => $displayName]);
            }
        }

        $this->ensureSchoolRole($user, $class->school_id, 'student');

        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $user->id)
            ->first();

        if ($enrollment) {
            if ($enrollment->status === 'ended') {
                $enrollment->update([
                    'status'      => 'active',
                    'enrolled_at' => now(),
                    'ended_at'    => null,
                ]);
            }

            return $enrollment->fresh(['user']);
        }

        return ClassEnrollment::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'user_id'         => $user->id,
            'status'          => $user->status === 'invited' ? 'invited' : 'active',
            'enrolled_at'     => now(),
        ])->load('user');
    }

    /**
     * پایان عضویت دانش‌آموز در کلاس (حذف نرم)
     */
    public function endStudentEnrollment(SchoolClass $class, User $student): void
    {
        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $student->id)
            ->where('status', '!=', 'ended')
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'user_id' => 'این دانش‌آموز در کلاس عضو نیست.',
            ]);
        }

        $enrollment->update([
            'status'   => 'ended',
            'ended_at' => now(),
        ]);
    }

    /**
     * تعیین معلم کلاس + عضویت teacher در مدرسه
     */
    public function assignTeacher(SchoolClass $class, ?string $mobile = null, ?string $userId = null, ?string $displayName = null): User
    {
        if ($userId) {
            $user = User::query()->findOrFail($userId);
        } elseif ($mobile) {
            $e164 = $this->normalizeMobile($mobile);
            $user = User::query()->where('mobile_e164', $e164)->first();

            if (! $user) {
                $user = User::create([
                    'mobile_e164'  => $e164,
                    'display_name' => $displayName ?: ('معلم '.substr($e164, -4)),
                    'status'       => 'invited',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'mobile' => 'موبایل یا شناسه معلم لازم است.',
            ]);
        }

        $this->ensureSchoolRole($user, $class->school_id, 'teacher');

        $class->update(['teacher_user_id' => $user->id]);

        return $user->fresh();
    }

    /**
     * عضویت مدرسه + نقش (student/teacher/...) را تضمین می‌کند.
     */
    public function ensureSchoolRole(User $user, string $schoolId, string $roleCode): SchoolMembership
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        $membership = SchoolMembership::query()
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            $membership = SchoolMembership::create([
                'id'        => (string) Str::uuid(),
                'school_id' => $schoolId,
                'user_id'   => $user->id,
                'status'    => $user->status === 'invited' ? 'invited' : 'active',
                'joined_at' => now(),
            ]);
        } elseif (in_array($membership->status, ['ended', 'suspended'], true)) {
            $membership->update([
                'status'   => 'active',
                'ended_at' => null,
                'joined_at' => $membership->joined_at ?? now(),
            ]);
        }

        MembershipRole::unguarded(function () use ($membership, $role) {
            MembershipRole::query()->updateOrCreate(
                [
                    'membership_id' => $membership->id,
                    'role_id'       => $role->id,
                ],
                [
                    'granted_at' => now(),
                    'revoked_at' => null,
                ]
            );
        });

        $this->permissions->clearUserCache($user);

        return $membership;
    }
}
