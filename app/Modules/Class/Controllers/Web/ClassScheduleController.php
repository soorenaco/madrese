<?php

namespace App\Modules\Class\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Class\Models\ClassScheduleSlot;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Services\ClassScheduleService;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassScheduleController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassScheduleService $schedules,
        protected SchoolManagerGate $gate
    ) {}

    public function show(Request $request, SchoolClass $school_class): Response
    {
        $this->assertAccess($request, $school_class);

        $school_class->load(['school:id,name', 'teacher:id,display_name']);

        $slots = ClassScheduleSlot::query()
            ->where('school_class_id', $school_class->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn ($s) => [
                'day_of_week' => $s->day_of_week,
                'start_time'  => substr((string) $s->start_time, 0, 5),
                'end_time'    => substr((string) $s->end_time, 0, 5),
                'room'        => $s->room,
            ])
            ->values();

        return Inertia::render('Classes/Schedule', [
            'classItem' => $school_class,
            'slots'     => $slots,
            'dayLabels' => [
                0 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه',
                3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه',
            ],
        ]);
    }

    public function store(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertAccess($request, $school_class);

        $data = $request->validate([
            'slots'               => ['array'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'  => ['required', 'date_format:H:i'],
            'slots.*.end_time'    => ['required', 'date_format:H:i'],
            'slots.*.room'        => ['nullable', 'string', 'max:80'],
        ]);

        $result = $this->schedules->syncSchedule($school_class, $data['slots'] ?? []);

        $msg = 'برنامه کلاس ذخیره شد.';
        if ($result['conflicts']) {
            $msg .= ' هشدار تداخل: '.collect($result['conflicts'])->pluck('message')->implode(' | ');
        }

        return back()->with(
            $result['conflicts'] ? 'error' : 'success',
            $msg
        );
    }

    protected function assertAccess(Request $request, SchoolClass $class): void
    {
        $user = $request->user();
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        if (! $this->permissions->managesSchool($user, $class->school_id)) {
            abort(403);
        }
        if ($this->gate->isPrimaryManager($user, $class->school_id)) {
            return;
        }
        if (! $this->gate->allows($user, $class->school_id, 'can_manage_schedule')) {
            abort(403, 'اجازه مدیریت برنامه را ندارید.');
        }
    }
}
