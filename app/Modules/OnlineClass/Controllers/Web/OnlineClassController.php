<?php

namespace App\Modules\OnlineClass\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Requests\OnlineClassRequest;
use App\Modules\OnlineClass\Services\OnlineClassService;
use App\Modules\OnlineClass\Services\ProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Validation\ValidationException;

class OnlineClassController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected OnlineClassService $service,
    ) {}

    /** فهرست کلاس‌های آنلاین قابل‌مشاهده توسط کاربر (ورود دانش‌آموز از اینجا). */
    public function index(Request $request): InertiaResponse
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
                $query->whereHas('schoolClass.enrollments', fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->where('status', 'active'));
            }
        }

        $items = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        return Inertia::render('OnlineClasses/Index', [
            'onlineClasses' => $items,
        ]);
    }

    public function show(Request $request, SchoolClass $school_class): InertiaResponse
    {
        $user = $request->user();

        $online = OnlineClass::query()
            ->where('school_class_id', $school_class->id)
            ->with(['schoolClass.school:id,name', 'schoolClass.teacher:id,display_name'])
            ->first();

        $canManage = $this->service->canManage($user, $school_class);
        $canJoin = $this->service->canJoin($user, $school_class);

        abort_unless($canManage || $canJoin, 403, 'به این کلاس دسترسی ندارید.');

        return Inertia::render('Classes/Online', [
            'classItem'   => $school_class->load(['school:id,name', 'teacher:id,display_name']),
            'online'      => $online,
            'canManage'   => $canManage,
            'canJoin'     => $canJoin,
            'providers'   => $this->configuredProviders(),
        ]);
    }

    public function store(OnlineClassRequest $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request->user(), $school_class);

        $this->service->register($school_class, $request->validated(), $request->user());

        return redirect()->route('panel.classes.online', $school_class)->with('success', 'کلاس آنلاین ثبت شد.');
    }

    public function update(OnlineClassRequest $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request->user(), $school_class);

        $online = OnlineClass::query()->where('school_class_id', $school_class->id)->firstOrFail();
        $this->service->update($online, $request->validated());

        return redirect()->route('panel.classes.online', $school_class)->with('success', 'کلاس آنلاین به‌روز شد.');
    }

    public function destroy(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request->user(), $school_class);

        $online = OnlineClass::query()->where('school_class_id', $school_class->id)->first();

        if ($online) {
            $this->service->unregister($online);
        }

        return redirect()->route('panel.classes.online', $school_class)->with('success', 'کلاس آنلاین حذف شد.');
    }

    public function start(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request->user(), $school_class);

        $online = OnlineClass::query()->where('school_class_id', $school_class->id)->firstOrFail();
        $this->service->start($online);

        return redirect()->route('panel.classes.online', $school_class)->with('success', 'جلسه شروع شد.');
    }

    public function end(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request->user(), $school_class);

        $online = OnlineClass::query()->where('school_class_id', $school_class->id)->firstOrFail();
        $this->service->end($online);

        return redirect()->route('panel.classes.online', $school_class)->with('success', 'جلسه پایان یافت.');
    }

    public function join(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $user = $request->user();
        $online = OnlineClass::query()->where('school_class_id', $school_class->id)->first();

        if (! $online) {
            return redirect()->route('panel.classes.online', $school_class)
                ->with('error', 'برای این کلاس کلاس آنلاینی ثبت نشده است.');
        }

        $this->assertCanJoin($user, $school_class);

        try {
            $url = $this->service->join($online, $user);
        } catch (ValidationException $e) {
            return redirect()->route('panel.classes.online', $school_class)
                ->with('error', implode(' ', Arr::flatten($e->errors())));
        }

        return redirect()->away($url);
    }

    /** لیست ارائه‌دهنده‌های پیکربندی‌شده (برای نمایش در فرم ثبت). */
    protected function configuredProviders(): array
    {
        $factory = app(ProviderFactory::class);

        $list = [];
        foreach (['bbb', 'skyroom'] as $code) {
            if ($factory->isConfigured($code)) {
                $list[] = [
                    'code'  => $code,
                    'title' => $code === 'bbb' ? 'BigBlueButton' : 'Skyroom',
                ];
            }
        }

        return $list;
    }

    protected function assertCanManage(User $user, SchoolClass $class): void
    {
        abort_unless($this->service->canManage($user, $class), 403, 'اجازهٔ مدیریت کلاس آنلاین را ندارید.');
    }

    protected function assertCanJoin(User $user, SchoolClass $class): void
    {
        abort_unless($this->service->canJoin($user, $class), 403, 'شما به این کلاس آنلاین دسترسی ندارید.');
    }
}
