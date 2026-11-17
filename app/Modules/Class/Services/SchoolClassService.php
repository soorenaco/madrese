<?php

namespace App\Modules\Class\Services;

use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\SchoolMembership;
use Illuminate\Validation\ValidationException;

/**
 * قوانین مشترک کلاس‌ها (پنل وب و API).
 */
class SchoolClassService
{
    /**
     * معلم باید عضو فعال همان مدرسه باشد.
     *
     * @throws ValidationException
     */
    public function assertTeacherBelongsToSchool(?string $teacherUserId, string $schoolId): void
    {
        if ($teacherUserId === null || $teacherUserId === '') {
            return;
        }

        $isMember = SchoolMembership::query()
            ->where('user_id', $teacherUserId)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'معلم انتخاب‌شده عضو فعال این مدرسه نیست.',
            ]);
        }
    }

    /**
     * مدرسهٔ کلاس پس از ساخت قابل تغییر نیست.
     *
     * @throws ValidationException
     */
    public function assertSchoolNotChanged(SchoolClass $class, string $newSchoolId): void
    {
        if ($class->school_id !== $newSchoolId) {
            throw ValidationException::withMessages([
                'school_id' => 'مدرسه کلاس قابل تغییر نیست.',
            ]);
        }
    }

    /**
     * کلاس دارای عضویت (حتی پایان‌یافته) قابل حذف نیست، چون حذف، roster را cascade پاک می‌کند.
     */
    public function hasEnrollments(SchoolClass $class): bool
    {
        return $class->enrollments()->exists();
    }
}
