<?php

namespace App\Modules\Class\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Resources\ClassEnrollmentResource;
use App\Modules\Class\Services\ClassRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ClassRosterController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassRosterService $roster
    ) {}

    public function index(Request $request, SchoolClass $class): AnonymousResourceCollection
    {
        $this->assertCanManage($request->user(), $class);

        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        $enrollments = ClassEnrollment::query()
            ->with('user:id,display_name,mobile_e164,status')
            ->where('school_class_id', $class->id)
            ->when($status === '' || $status === 'current', function ($query) {
                $query->whereIn('status', ['active', 'invited']);
            })
            ->when(in_array($status, ['active', 'invited', 'ended'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', function ($u) use ($q) {
                    $u->where('display_name', 'ilike', "%{$q}%")
                        ->orWhere('mobile_e164', 'ilike', "%{$q}%");
                });
            })
            ->orderByRaw("case status when 'active' then 0 when 'invited' then 1 else 2 end")
            ->orderBy('created_at')
            ->get();

        return ClassEnrollmentResource::collection($enrollments);
    }

    public function inviteStudent(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $data = $request->validate([
            'mobile'       => ['required', 'string', 'max:20'],
            'display_name' => ['nullable', 'string', 'max:160'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
        ]);

        $enrollment = $this->roster->inviteStudent(
            $class,
            $data['mobile'],
            $data['display_name'] ?? null
        );

        return (new ClassEnrollmentResource($enrollment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function removeStudent(Request $request, SchoolClass $class, User $user): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $this->roster->endStudentEnrollment($class, $user);

        return response()->json(['message' => 'دانش‌آموز از کلاس خارج شد.']);
    }

    public function restoreStudent(Request $request, SchoolClass $class, User $user): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'عضویت یافت نشد.'], 404);
        }

        $enrollment->update([
            'status'      => 'active',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
            'ended_at'    => null,
        ]);

        return response()->json([
            'message' => 'دانش‌آموز دوباره به کلاس بازگشت.',
            'data'    => new ClassEnrollmentResource($enrollment->load('user')),
        ]);
    }

    public function updateStudentStatus(Request $request, SchoolClass $class, User $user): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'invited', 'ended'])],
        ]);

        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $class->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'عضویت یافت نشد.'], 404);
        }

        $enrollment->update([
            'status'      => $data['status'],
            'enrolled_at' => $data['status'] !== 'ended'
                ? ($enrollment->enrolled_at ?? now())
                : $enrollment->enrolled_at,
            'ended_at'    => $data['status'] === 'ended' ? now() : null,
        ]);

        return response()->json([
            'message' => 'وضعیت عضویت به‌روز شد.',
            'data'    => new ClassEnrollmentResource($enrollment->load('user')),
        ]);
    }

    public function assignTeacher(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $data = $request->validate([
            'mobile'       => ['nullable', 'string', 'max:20'],
            'user_id'      => ['nullable', 'uuid', 'exists:users,id'],
            'display_name' => ['nullable', 'string', 'max:160'],
        ]);

        if (empty($data['mobile']) && empty($data['user_id'])) {
            return response()->json([
                'message' => 'موبایل یا انتخاب معلم الزامی است.',
                'errors'  => ['mobile' => ['موبایل یا انتخاب معلم الزامی است.']],
            ], 422);
        }

        $teacher = $this->roster->assignTeacher(
            $class,
            $data['mobile'] ?? null,
            $data['user_id'] ?? null,
            $data['display_name'] ?? null
        );

        return response()->json([
            'message' => 'معلم کلاس ثبت شد.',
            'data'    => [
                'id'           => $teacher->id,
                'display_name' => $teacher->display_name,
                'mobile'       => $teacher->mobile_e164,
            ],
        ]);
    }

    protected function assertCanManage($user, SchoolClass $class): void
    {
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        if ($this->permissions->managesSchool($user, $class->school_id)) {
            return;
        }
        abort(403, 'به این کلاس دسترسی ندارید.');
    }
}