<?php

namespace App\Modules\Class\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\School\Models\School;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Services\SchoolClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolClassService $classes
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $this->permissions->isPlatformAdmin($user);
        $isManager = ! $isAdmin && $this->permissions->userHasRole($user, 'school_manager');
        $managedIds = $this->permissions->managedSchoolIds($user);

        $classes = SchoolClass::query()
            ->with(['school:id,name', 'teacher:id,display_name,mobile_e164'])
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

        $schoolsQuery = School::query()->orderBy('name');
        if ($isManager) {
            $schoolsQuery->whereIn('id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']);
        }

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'schools' => $schoolsQuery->get(['id', 'name']),
            'filters' => [
                'q'         => $request->string('q')->toString(),
                'school_id' => $request->string('school_id')->toString(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);

        $managedIds = $this->permissions->managedSchoolIds($user);
        $isAdmin = $this->permissions->isPlatformAdmin($user);

        $schoolsQuery = School::query()->where('status', 'active')->orderBy('name');
        if (! $isAdmin) {
            $schoolsQuery->whereIn('id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']);
        }

        $schools = $schoolsQuery->get(['id', 'name']);

        // باگ‌رفته: اگر مدیر فقط به یک مدرسه دسترسی دارد، آن مدرسه به‌صورت
        // خودکار به‌عنوان مدرسه‌ی کلاس انتخاب می‌شود و فیلد در فرم قفل می‌گردد
        // تا کاربر مجبور به انتخاب دستی نباشد و «اسم مدرسه بالا بیاید».
        $defaultSchoolId = null;
        $schoolLocked = false;
        if (! $isAdmin && $schools->count() === 1) {
            $defaultSchoolId = $schools->first()->id;
            $schoolLocked = true;
        }

        return Inertia::render('Classes/Form', [
            'classItem'       => null,
            'schools'         => $schools,
            'defaultSchoolId' => $defaultSchoolId,
            'schoolLocked'    => $schoolLocked,
            'teachers'        => $this->teacherOptions($isAdmin ? null : $managedIds),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanWriteClasses($request->user());
        $data = $this->validated($request);
        $this->assertSchoolInScope($request->user(), $data['school_id']);
        $this->classes->assertTeacherBelongsToSchool($data['teacher_user_id'] ?? null, $data['school_id']);

        SchoolClass::create($data);

        return redirect()->route('panel.classes')->with('success', 'کلاس با موفقیت ایجاد شد.');
    }

    public function edit(Request $request, SchoolClass $school_class): Response
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);
        $this->assertSchoolInScope($user, $school_class->school_id);

        $managedIds = $this->permissions->managedSchoolIds($user);
        $isAdmin = $this->permissions->isPlatformAdmin($user);

        $schoolsQuery = School::query()->orderBy('name');
        if (! $isAdmin) {
            $schoolsQuery->whereIn('id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']);
        }

        return Inertia::render('Classes/Form', [
            'classItem'       => $school_class->load(['school:id,name', 'teacher:id,display_name']),
            'schools'         => $schoolsQuery->get(['id', 'name']),
            'defaultSchoolId' => null,
            'schoolLocked'    => true,
            'teachers'        => $this->teacherOptions($isAdmin ? null : $managedIds),
        ]);
    }

    public function update(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);
        $this->assertSchoolInScope($user, $school_class->school_id);

        $data = $this->validated($request, $school_class);
        $this->classes->assertSchoolNotChanged($school_class, $data['school_id']);
        $this->classes->assertTeacherBelongsToSchool($data['teacher_user_id'] ?? null, $school_class->school_id);

        $school_class->update($data);

        return redirect()->route('panel.classes')->with('success', 'کلاس به‌روزرسانی شد.');
    }

    public function destroy(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanWriteClasses($user);
        $this->assertSchoolInScope($user, $school_class->school_id);

        if ($this->classes->hasEnrollments($school_class)) {
            return redirect()->route('panel.classes')
                ->with('error', 'این کلاس دانش‌آموز (یا سابقه عضویت) دارد و قابل حذف نیست.');
        }

        $school_class->delete();

        return redirect()->route('panel.classes')->with('success', 'کلاس حذف شد.');
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

    protected function assertSchoolInScope($user, string $schoolId): void
    {
        abort_unless($this->permissions->managesSchool($user, $schoolId), 403, 'به این مدرسه دسترسی ندارید.');
    }

    protected function validated(Request $request, ?SchoolClass $existing = null): array
    {
        $schoolId = $request->input('school_id', $existing?->school_id);

        return $request->validate([
            'school_id'       => ['required', 'uuid', 'exists:schools,id'],
            'name'            => [
                'required', 'string', 'max:120',
                Rule::unique('school_classes', 'name')
                    ->where(fn ($q) => $q->where('school_id', $schoolId)
                        ->where('academic_year', $request->input('academic_year')))
                    ->ignore($existing?->id),
            ],
            'grade'           => ['nullable', 'string', 'max:40'],
            'academic_year'   => ['nullable', 'string', 'max:20'],
            'teacher_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status'          => ['required', Rule::in(['active', 'archived'])],
        ], [
            'school_id.required' => 'انتخاب مدرسه الزامی است.',
            'name.required'      => 'نام کلاس الزامی است.',
            'name.unique'        => 'این نام کلاس در همین مدرسه و سال تحصیلی تکراری است.',
        ]);
    }

    /** @param  list<string>|null  $managedIds */
    protected function teacherOptions(?array $managedIds)
    {
        $query = User::query()->where('status', 'active')->orderBy('display_name')->limit(200);

        if ($managedIds !== null) {
            $ids = $managedIds ?: ['00000000-0000-0000-0000-000000000000'];
            $query->whereHas('memberships', function ($q) use ($ids) {
                $q->whereIn('school_id', $ids)
                    ->where('status', 'active')
                    ->whereNull('ended_at');
            });
        }

        return $query->get(['id', 'display_name', 'mobile_e164']);
    }
}
