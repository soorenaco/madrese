<?php

namespace App\Modules\Class\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\ClassScheduleSlot;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Resources\ClassScheduleSlotResource;
use App\Modules\Class\Services\ClassScheduleService;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassScheduleController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassScheduleService $schedules,
        protected SchoolManagerGate $gate
    ) {}

    public function index(Request $request, SchoolClass $class): AnonymousResourceCollection
    {
        $this->assertAccess($request->user(), $class);

        $slots = ClassScheduleSlot::query()
            ->where('school_class_id', $class->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return ClassScheduleSlotResource::collection($slots);
    }

    public function store(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertAccess($request->user(), $class);

        $data = $request->validate([
            'slots'               => ['array'],
            'slots.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'  => ['required', 'date_format:H:i'],
            'slots.*.end_time'    => ['required', 'date_format:H:i'],
            'slots.*.room'        => ['nullable', 'string', 'max:80'],
        ]);

        $result = $this->schedules->syncSchedule($class, $data['slots'] ?? []);

        return response()->json([
            'message'   => $result['conflicts']
                ? 'برنامه ذخیره شد، ولی تداخل وجود دارد.'
                : 'برنامه کلاس ذخیره شد.',
            'data'      => ClassScheduleSlotResource::collection($result['slots']),
            'conflicts' => $result['conflicts'],
        ]);
    }

    protected function assertAccess($user, SchoolClass $class): void
    {
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