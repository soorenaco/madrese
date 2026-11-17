<?php

namespace App\Modules\Class\Services;

use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassPromoteService
{
    public function __construct(
        protected ClassRosterService $roster
    ) {}

    /**
     * انتقال کلاس به سال/پایه جدید.
     *
     * @param  array{grade?: string|null, academic_year: string, teacher_mobile?: string|null, teacher_user_id?: string|null, teacher_name?: string|null, mode: string, carry_students: bool}  $data
     * mode: update = همان کلاس | clone = کلاس جدید + بایگانی قبلی
     */
    public function promote(SchoolClass $class, array $data): SchoolClass
    {
        return DB::transaction(function () use ($class, $data) {
            $mode = $data['mode'] ?? 'update';
            $carry = (bool) ($data['carry_students'] ?? true);

            if ($mode === 'clone') {
                $newClass = SchoolClass::create([
                    'id'              => (string) Str::uuid(),
                    'school_id'       => $class->school_id,
                    'name'            => $class->name,
                    'grade'           => $data['grade'] ?? $class->grade,
                    'academic_year'   => $data['academic_year'],
                    'teacher_user_id' => $class->teacher_user_id,
                    'status'          => 'active',
                ]);

                $class->update(['status' => 'archived']);

                if ($carry) {
                    $this->copyActiveEnrollments($class, $newClass);
                }

                $target = $newClass;
            } else {
                $class->update([
                    'grade'         => $data['grade'] ?? $class->grade,
                    'academic_year' => $data['academic_year'],
                ]);
                $target = $class->fresh();
            }

            if (! empty($data['teacher_user_id']) || ! empty($data['teacher_mobile'])) {
                $this->roster->assignTeacher(
                    $target,
                    $data['teacher_mobile'] ?? null,
                    $data['teacher_user_id'] ?? null,
                    $data['teacher_name'] ?? null
                );
                $target = $target->fresh();
            }

            return $target;
        });
    }

    protected function copyActiveEnrollments(SchoolClass $from, SchoolClass $to): void
    {
        $rows = ClassEnrollment::query()
            ->where('school_class_id', $from->id)
            ->whereIn('status', ['active', 'invited'])
            ->get();

        foreach ($rows as $row) {
            ClassEnrollment::create([
                'id'              => (string) Str::uuid(),
                'school_class_id' => $to->id,
                'user_id'         => $row->user_id,
                'status'          => $row->status === 'invited' ? 'invited' : 'active',
                'enrolled_at'     => now(),
            ]);
        }
    }
}
