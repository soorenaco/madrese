<?php

namespace App\Modules\Class\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Resources\SchoolClassResource;
use App\Modules\Class\Services\ClassPromoteService;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassPromoteController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassPromoteService $promoteService,
        protected SchoolManagerGate $gate
    ) {}

    public function store(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertAccess($request->user(), $class);

        $data = $request->validate([
            'academic_year'   => ['required', 'string', 'max:20'],
            'grade'           => ['nullable', 'string', 'max:40'],
            'mode'            => ['required', Rule::in(['update', 'clone'])],
            'carry_students'  => ['boolean'],
            'teacher_mobile'  => ['nullable', 'string', 'max:20'],
            'teacher_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'teacher_name'    => ['nullable', 'string', 'max:160'],
        ]);

        $data['carry_students'] = $request->boolean('carry_students', true);

        $result = $this->promoteService->promote($class, $data);

        return response()->json([
            'message' => $data['mode'] === 'clone'
                ? 'کلاس جدید برای سال تحصیلی ساخته شد.'
                : 'اطلاعات سال/پایه کلاس به‌روز شد.',
            'data'    => new SchoolClassResource($result->load(['school:id,name', 'teacher:id,display_name,mobile_e164'])->loadCount('enrollments')),
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
        if (! $this->gate->allows($user, $class->school_id, 'can_promote_class')) {
            abort(403, 'اجازه ارتقای کلاس را ندارید.');
        }
    }
}