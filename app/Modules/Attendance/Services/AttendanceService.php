<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\Attendance;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق ثبت و خوانش حضور و غیاب.
 *
 * هر جلسه با (school_class_id, session_date, slot) شناسایی می‌شود.
 * یک دانش‌آموز در هر جلسه فقط یک رکورد دارد (unique constraint).
 */
class AttendanceService
{
    public function __construct(
        protected PermissionService $permissions
    ) {}

    /**
     * لیست دانش‌آموزان فعال کلاس به‌همراه وضعیت ثبت‌شده‌ی آن‌ها برای یک جلسه.
     *
     * @return array<int, array{user: User, enrollment: ClassEnrollment, attendance: ?Attendance}>
     */
    public function getRosterForSession(SchoolClass $class, string $sessionDate, int $slot = 1): array
    {
        $enrollments = ClassEnrollment::query()
            ->with('user:id,display_name,mobile_e164,status')
            ->where('school_class_id', $class->id)
            ->whereIn('status', ['active'])
            ->orderByRaw("case status when 'active' then 0 else 1 end")
            ->get();

        $attendances = Attendance::query()
            ->where('school_class_id', $class->id)
            ->where('session_date', $sessionDate)
            ->where('slot', $slot)
            ->get()
            ->keyBy('user_id');

        $roster = [];
        foreach ($enrollments as $enrollment) {
            $roster[] = [
                'user'        => $enrollment->user,
                'enrollment'  => $enrollment,
                'attendance'  => $attendances->get($enrollment->user_id),
            ];
        }

        return $roster;
    }

    /**
     * ثبت دسته‌ای وضعیت حضور و غیاب یک جلسه.
     *
     * @param array<int, array{user_id: string, status: string, note?: string|null}> $records
     * @return int تعداد رکوردهای upsert شده
     */
    public function recordBatch(SchoolClass $class, User $recorder, string $sessionDate, int $slot, array $records): int
    {
        $allowedStatuses = Attendance::statuses();
        $activeUserIds = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->whereIn('status', ['active'])
            ->pluck('user_id')
            ->all();

        $now = now();
        $count = 0;

        DB::transaction(function () use ($class, $recorder, $sessionDate, $slot, $records, $allowedStatuses, $activeUserIds, &$now, &$count) {
            foreach ($records as $row) {
                $userId = $row['user_id'] ?? null;
                $status = $row['status'] ?? null;
                $note   = $row['note'] ?? null;

                if (! $userId || ! in_array($status, $allowedStatuses, true)) {
                    continue;
                }
                if (! in_array($userId, $activeUserIds, true)) {
                    continue;
                }

                Attendance::updateOrCreate(
                    [
                        'school_class_id' => $class->id,
                        'user_id'         => $userId,
                        'session_date'    => $sessionDate,
                        'slot'            => $slot,
                    ],
                    [
                        'status'      => $status,
                        'recorded_by' => $recorder->id,
                        'note'        => $note ? mb_substr($note, 0, 300) : null,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * خلاصه‌ی آماری یک جلسه.
     *
     * @return array{present:int, absent:int, late:int, excused:int, total:int}
     */
    public function getSessionSummary(SchoolClass $class, string $sessionDate, int $slot = 1): array
    {
        $base = Attendance::query()
            ->where('school_class_id', $class->id)
            ->where('session_date', $sessionDate)
            ->where('slot', $slot);

        $counts = (clone $base)
            ->select('status', DB::raw('count(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        $totalStudents = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('status', 'active')
            ->count();

        return [
            'present' => $counts['present'] ?? 0,
            'absent'  => $counts['absent'] ?? 0,
            'late'    => $counts['late'] ?? 0,
            'excused' => $counts['excused'] ?? 0,
            'total'   => $totalStudents,
        ];
    }

    /**
     * تاریخچه‌ی روزهای ثبت‌شده برای یک کلاس (برای تقویم/انتخاب تاریخ).
     *
     * @return array<int, array{date:string, slot:int, recorded:int}>
     */
    public function getRecordedDays(SchoolClass $class, int $limit = 60): array
    {
        return Attendance::query()
            ->where('school_class_id', $class->id)
            ->select('session_date', 'slot', DB::raw('count(*) as recorded'))
            ->groupBy('session_date', 'slot')
            ->orderByDesc('session_date')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->session_date instanceof \DateTimeInterface
                    ? $r->session_date->format('Y-m-d')
                    : (string) $r->session_date,
                'slot'     => (int) $r->slot,
                'recorded' => (int) $r->recorded,
            ])
            ->all();
    }

    /**
     * گزارش حضور و غیاب یک دانش‌آموز در یک کلاس (برای والدین/خودش).
     *
     * @return array{stats:array, records:Collection}
     */
    public function getStudentReport(SchoolClass $class, User $student): array
    {
        $records = Attendance::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $student->id)
            ->orderByDesc('session_date')
            ->get();

        $stats = [
            'present' => $records->where('status', 'present')->count(),
            'absent'  => $records->where('status', 'absent')->count(),
            'late'    => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total'   => $records->count(),
        ];

        // درصد حضور (حاضر + موجه + نصف تأخیر) / کل
        $effective = $stats['present'] + $stats['excused'] + (int) ceil($stats['late'] / 2);
        $stats['attendance_rate'] = $stats['total'] > 0
            ? round(($effective / $stats['total']) * 100, 1)
            : null;

        return [
            'stats'   => $stats,
            'records' => $records,
        ];
    }

    /**
     * گزارش کلی کلاس در یک بازه‌ی تاریخی (برای مدیر/معلم).
     *
     * @return array{rows: Collection, totals: array}
     */
    public function getClassReport(SchoolClass $class, ?string $from = null, ?string $to = null): array
    {
        $query = Attendance::query()
            ->where('school_class_id', $class->id)
            ->with('user:id,display_name,mobile_e164');

        if ($from) {
            $query->where('session_date', '>=', $from);
        }
        if ($to) {
            $query->where('session_date', '<=', $to);
        }

        $records = $query->orderByDesc('session_date')->get();

        // گروه‌بندی بر اساس دانش‌آموز
        $rows = $records->groupBy('user_id')->map(function ($items, $userId) {
            $user = $items->first()?->user;
            return [
                'user'     => $user,
                'present'  => $items->where('status', 'present')->count(),
                'absent'   => $items->where('status', 'absent')->count(),
                'late'     => $items->where('status', 'late')->count(),
                'excused'  => $items->where('status', 'excused')->count(),
                'total'    => $items->count(),
            ];
        })->values();

        $totals = [
            'present' => $records->where('status', 'present')->count(),
            'absent'  => $records->where('status', 'absent')->count(),
            'late'    => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total'   => $records->count(),
        ];

        return [
            'rows'   => $rows,
            'totals' => $totals,
        ];
    }
}
