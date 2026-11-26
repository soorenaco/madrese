<?php

namespace App\Modules\Attendance\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected AttendanceService $attendance,
        protected SchoolManagerGate $gate,
    ) {}

    /**
     * صفحه‌ی ثبت/مشاهده‌ی حضور و غیاب یک جلسه‌ی کلاس.
     */
    public function show(Request $request, SchoolClass $school_class): Response
    {
        $this->assertCanRecord($request, $school_class);

        $school_class->load(['school:id,name', 'teacher:id,display_name,mobile_e164']);

        // تاریخ جلسه: از query یا امروز
        $sessionDate = $request->string('date')->toString() ?: now()->format('Y-m-d');
        // اعتبارسنجی فرمت تاریخ
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
            $sessionDate = now()->format('Y-m-d');
        }
        $slot = (int) ($request->integer('slot') ?: 1);
        if ($slot < 1) {
            $slot = 1;
        }

        $roster = $this->attendance->getRosterForSession($school_class, $sessionDate, $slot);
        $summary = $this->attendance->getSessionSummary($school_class, $sessionDate, $slot);
        $recordedDays = $this->attendance->getRecordedDays($school_class, 60);

        return Inertia::render('Classes/Attendance', [
            'classItem'    => $school_class,
            'sessionDate'  => $sessionDate,
            'slot'         => $slot,
            'roster'       => $roster,
            'summary'      => $summary,
            'recordedDays' => $recordedDays,
            'statuses'     => $this->statusesMeta(),
            'canRecord'    => true,
        ]);
    }

    /**
     * ثبت دسته‌ای حضور و غیاب یک جلسه.
     */
    public function store(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanRecord($request, $school_class);

        $data = $request->validate([
            'session_date' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'slot'         => ['required', 'integer', 'min:1', 'max:20'],
            'records'      => ['required', 'array', 'min:1'],
            'records.*.user_id' => ['required', 'uuid'],
            'records.*.status'  => ['required', Rule::in(Attendance::statuses())],
            'records.*.note'    => ['nullable', 'string', 'max:300'],
        ], [
            'session_date.required' => 'تاریخ جلسه الزامی است.',
            'session_date.regex'    => 'فرمت تاریخ معتبر نیست.',
            'records.required'      => 'حداقل یک رکورد نیاز است.',
            'records.*.status.required' => 'وضعیت هر دانش‌آموز الزامی است.',
        ]);

        $count = $this->attendance->recordBatch(
            $school_class,
            $request->user(),
            $data['session_date'],
            (int) $data['slot'],
            $data['records']
        );

        return back()->with('success', "حضور و غیاب {$count} دانش‌آموز ثبت شد.");
    }

    /**
     * گزارش کلی کلاس (برای مدیر/معلم).
     */
    public function report(Request $request, SchoolClass $school_class): Response
    {
        $this->assertCanView($request, $school_class);

        $school_class->load(['school:id,name', 'teacher:id,display_name,mobile_e164']);

        $from = $request->string('from')->toString() ?: null;
        $to   = $request->string('to')->toString()   ?: null;
        if ($from && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = null; }
        if ($to   && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to = null; }

        $report = $this->attendance->getClassReport($school_class, $from, $to);

        return Inertia::render('Classes/AttendanceReport', [
            'classItem' => $school_class,
            'rows'      => $report['rows'],
            'totals'    => $report['totals'],
            'filters'   => ['from' => $from, 'to' => $to],
            'statuses'  => $this->statusesMeta(),
        ]);
    }

    // ─── Authorization helpers ───

    /**
     * اجازه‌ی ثبت: ادمین پلتفرم، مدیر مدرسه، یا معلم کلاس.
     */
    protected function assertCanRecord(Request $request, SchoolClass $class): void
    {
        $user = $request->user();

        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }

        // معلم کلاس می‌تواند ثبت کند
        if ($class->teacher_user_id === $user->id) {
            return;
        }

        // مدیر مدرسه (اصلی یا معاون با مجوز attendance.record)
        if (! $this->permissions->managesSchool($user, $class->school_id)) {
            abort(403, 'به این کلاس دسترسی ندارید.');
        }

        if ($this->gate->isPrimaryManager($user, $class->school_id)) {
            return;
        }

        // معاون: بررسی دسترسی runtime
        if ($this->permissions->userHasPermission($user, 'attendance.record', $class->school_id)) {
            return;
        }

        abort(403, 'اجازه‌ی ثبت حضور و غیاب را ندارید.');
    }

    /**
     * اجازه‌ی مشاهده: دامنه‌ی وسیع‌تر (دانش‌آموز برای گزارش خودش، والدین، ...).
     */
    protected function assertCanView(Request $request, SchoolClass $class): void
    {
        $user = $request->user();

        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        if ($class->teacher_user_id === $user->id) {
            return;
        }
        if ($this->permissions->managesSchool($user, $class->school_id)) {
            return;
        }
        // دانش‌آموز عضو کلاس
        $isMember = \App\Modules\Class\Models\ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
        if ($isMember) {
            return;
        }

        abort(403, 'مشاهده‌ی گزارش این کلاس مجاز نیست.');
    }

    /**
     * متادیتای وضعیت‌ها برای فرانت.
     */
    protected function statusesMeta(): array
    {
        $out = [];
        foreach (Attendance::statuses() as $code) {
            $out[] = [
                'code'    => $code,
                'label'   => Attendance::statusLabel($code),
                'variant' => Attendance::statusVariant($code),
            ];
        }
        return $out;
    }
}
