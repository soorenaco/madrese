<?php

namespace App\Modules\Class\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Requests\SchoolClassRequest;
use App\Modules\Class\Resources\SchoolClassResource;
use App\Modules\Class\Services\SchoolClassService;
use App\Modules\School\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SchoolClassController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolClassService $classes
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = $this->permissions->isPlatformAdmin($user);
        $isManager = ! $isAdmin && $this->permissions->userHasRole($user, 'school_manager');
        $managedIds = $this->permissions->managedSchoolIds($user);

        $classes = SchoolClass::query()
            ->with(['school:id,name', 'teacher:id,display_name,mobile_e164'])
            ->withCount('enrollments')
            ->when($isManager, fn ($q) => $q->whereIn(
                'school_id',
                $managedIds ?: ['00000000-0000-0000-0000-000000000000']
            ))
            ->when(
                ! $isAdmin && ! $isManager && $this->permissions->userHasRole($user, 'teacher'),
                fn ($q) => $q->where('teacher_user_id', $user->id)
            )
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('grade', 'ilike', "%{$term}%");
                });
            })
            ->when($request->string('school_id')->toString(), function ($q, $id) use ($isAdmin, $isManager, $managedIds) {
                if ($isManager && ! in_array($id, $managedIds ?: [], true)) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->where('school_id', $id);
                }
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return SchoolClassResource::collection($classes);
    }

    public function store(SchoolClassRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);

        $data = $request->validated();
        $this->assertSchoolInScope($user, $data['school_id']);
        $this->classes->assertTeacherBelongsToSchool($data['teacher_user_id'] ?? null, $data['school_id']);

        $class = SchoolClass::create($data)->loadCount('enrollments');

        return (new SchoolClassResource($class))->response()->setStatusCode(201);
    }

    public function show(Request $request, SchoolClass $class): SchoolClassResource
    {
        $this->assertAccess($request->user(), $class);

        return new SchoolClassResource($class->load(['school:id,name', 'teacher:id,display_name,mobile_e164'])->loadCount('enrollments'));
    }

    public function update(SchoolClassRequest $request, SchoolClass $class): SchoolClassResource
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);
        $this->assertAccess($user, $class);

        $data = $request->validated();
        $this->classes->assertSchoolNotChanged($class, $data['school_id']);
        $this->classes->assertTeacherBelongsToSchool($data['teacher_user_id'] ?? null, $class->school_id);

        $class->update($data);

        return new SchoolClassResource($class->load(['school:id,name', 'teacher:id,display_name,mobile_e164'])->loadCount('enrollments'));
    }

    public function destroy(Request $request, SchoolClass $class): Response|JsonResponse
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);
        $this->assertAccess($user, $class);

        if ($this->classes->hasEnrollments($class)) {
            return response()->json([
                'message' => 'این کلاس دانش‌آموز (یا سابقه عضویت) دارد و قابل حذف نیست.',
            ], 409);
        }

        $class->delete();

        return response()->noContent();
    }

    protected function assertCanWriteClasses($user): void
    {
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        if ($this->permissions->userHasRole($user, 'school_manager')) {
            return;
        }
        abort(403, 'فقط مدیر مدرسه یا ادمین می‌تواند کلاس را تغییر دهد.');
    }

    protected function assertAccess($user, SchoolClass $class): void
    {
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        if ($this->permissions->managesSchool($user, $class->school_id)) {
            return;
        }
        abort(403, 'به این کلاس دسترسی ندارید.');
    }

    protected function assertSchoolInScope($user, string $schoolId): void
    {
        abort_unless($this->permissions->managesSchool($user, $schoolId), 403, 'به این مدرسه دسترسی ندارید.');
    }
}