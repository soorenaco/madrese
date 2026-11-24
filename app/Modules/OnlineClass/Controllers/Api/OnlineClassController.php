<?php

namespace App\Modules\OnlineClass\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Requests\OnlineClassRequest;
use App\Modules\OnlineClass\Resources\OnlineClassResource;
use App\Modules\OnlineClass\Services\OnlineClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OnlineClassController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected OnlineClassService $service,
    ) {}

    /** فهرست کلاس‌های آنلاین قابل‌مشاهده توسط کاربر. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $isAdmin = $this->permissions->isPlatformAdmin($user);

        $query = OnlineClass::query()
            ->with([
                'schoolClass:id,school_id,name,grade,academic_year,teacher_user_id',
                'schoolClass.school:id,name',
                'schoolClass.teacher:id,display_name',
            ]);

        if (! $isAdmin) {
            $isManager = $this->permissions->userHasRole($user, 'school_manager');
            $isTeacher = $this->permissions->userHasRole($user, 'teacher');
            $managedIds = $this->permissions->managedSchoolIds($user);
            $none = ['00000000-0000-0000-0000-000000000000'];

            if ($isManager) {
                $query->whereHas('schoolClass', fn ($q) => $q->whereIn('school_id', $managedIds ?: $none));
            } elseif ($isTeacher) {
                $query->whereHas('schoolClass', fn ($q) => $q->where('teacher_user_id', $user->id));
            } else {
                // دانش‌آموز: کلاس‌هایی که درشان enrollment فعال دارند.
                $query->whereHas('schoolClass.enrollments', fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('status', 'active'));
            }
        }

        $online = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        return OnlineClassResource::collection($online);
    }

    public function show(Request $request, SchoolClass $class): JsonResponse
    {
        $online = OnlineClass::query()->where('school_class_id', $class->id)->first();

        if (! $online) {
            return response()->json(['message' => 'برای این کلاس کلاس آنلاینی ثبت نشده است.'], 404);
        }

        $this->assertCanSee($request->user(), $class);

        $online->load(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name']);

        return response()->json(['data' => new OnlineClassResource($online)]);
    }

    public function store(OnlineClassRequest $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $online = $this->service->register($class, $request->validated(), $request->user());

        return (new OnlineClassResource($online->load(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(OnlineClassRequest $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $online = OnlineClass::query()->where('school_class_id', $class->id)->firstOrFail();
        $online = $this->service->update($online, $request->validated());

        return response()->json([
            'data' => new OnlineClassResource($online->fresh(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name'])),
        ]);
    }

    public function destroy(Request $request, SchoolClass $class): Response|JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $online = OnlineClass::query()->where('school_class_id', $class->id)->first();

        if (! $online) {
            return response()->noContent();
        }

        $this->service->unregister($online);

        return response()->noContent();
    }

    public function start(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $online = OnlineClass::query()->where('school_class_id', $class->id)->firstOrFail();
        $online = $this->service->start($online);

        return response()->json([
            'data' => new OnlineClassResource($online->fresh(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name'])),
        ]);
    }

    public function end(Request $request, SchoolClass $class): JsonResponse
    {
        $this->assertCanManage($request->user(), $class);

        $online = OnlineClass::query()->where('school_class_id', $class->id)->firstOrFail();
        $online = $this->service->end($online);

        return response()->json([
            'data' => new OnlineClassResource($online->fresh(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name'])),
        ]);
    }

    public function join(Request $request, SchoolClass $class): JsonResponse
    {
        $online = OnlineClass::query()->where('school_class_id', $class->id)->first();

        if (! $online) {
            return response()->json(['message' => 'برای این کلاس کلاس آنلاینی ثبت نشده است.'], 404);
        }

        $this->assertCanJoin($request->user(), $class);

        $url = $this->service->join($online, $request->user());

        return response()->json(['data' => ['join_url' => $url]]);
    }

    protected function assertCanManage(User $user, SchoolClass $class): void
    {
        abort_unless($this->service->canManage($user, $class), 403, 'اجازهٔ مدیریت کلاس آنلاین را ندارید.');
    }

    protected function assertCanJoin(User $user, SchoolClass $class): void
    {
        abort_unless($this->service->canJoin($user, $class), 403, 'شما به این کلاس آنلاین دسترسی ندارید.');
    }

    protected function assertCanSee(User $user, SchoolClass $class): void
    {
        abort_unless(
            $this->service->canManage($user, $class) || $this->service->canJoin($user, $class),
            403,
            'به این کلاس دسترسی ندارید.'
        );
    }
}
