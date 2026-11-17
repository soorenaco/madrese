<?php

namespace App\Modules\Class\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\User;
use App\Modules\Class\Services\ClassRosterService;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassRosterController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassRosterService $roster
    ) {}

    public function show(Request $request, SchoolClass $school_class): Response
    {
        $this->assertCanManage($request, $school_class);

        $school_class->load(['school:id,name', 'teacher:id,display_name,mobile_e164']);

        $status = $request->string('status')->toString(); // active|invited|ended|all|''
        $q = $request->string('q')->toString();

        $enrollments = ClassEnrollment::query()
            ->with('user:id,display_name,mobile_e164,status')
            ->where('school_class_id', $school_class->id)
            ->when($status === '' || $status === 'current', function ($query) {
                $query->whereIn('status', ['active', 'invited']);
            })
            ->when(in_array($status, ['active', 'invited', 'ended'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            // status=all → بدون فیلتر وضعیت
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', function ($u) use ($q) {
                    $u->where('display_name', 'ilike', "%{$q}%")
                        ->orWhere('mobile_e164', 'ilike', "%{$q}%");
                });
            })
            ->orderByRaw("case status when 'active' then 0 when 'invited' then 1 else 2 end")
            ->orderBy('created_at')
            ->get();

        $counts = [
            'active'  => ClassEnrollment::where('school_class_id', $school_class->id)->where('status', 'active')->count(),
            'invited' => ClassEnrollment::where('school_class_id', $school_class->id)->where('status', 'invited')->count(),
            'ended'   => ClassEnrollment::where('school_class_id', $school_class->id)->where('status', 'ended')->count(),
        ];

        return Inertia::render('Classes/Roster', [
            'classItem'   => $school_class,
            'enrollments' => $enrollments,
            'counts'      => $counts,
            'filters'     => [
                'q'      => $q,
                'status' => $status === '' ? 'current' : $status,
            ],
        ]);
    }

    public function inviteStudent(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request, $school_class);

        $data = $request->validate([
            'mobile'       => ['required', 'string', 'max:20'],
            'display_name' => ['nullable', 'string', 'max:160'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
        ]);

        $this->roster->inviteStudent(
            $school_class,
            $data['mobile'],
            $data['display_name'] ?? null
        );

        return back()->with('success', 'دانش‌آموز به کلاس و مدرسه اضافه شد.');
    }

    public function removeStudent(Request $request, SchoolClass $school_class, User $user): RedirectResponse
    {
        $this->assertCanManage($request, $school_class);

        $this->roster->endStudentEnrollment($school_class, $user);

        return back()->with('success', 'دانش‌آموز از کلاس خارج شد.');
    }

    public function restoreStudent(Request $request, SchoolClass $school_class, User $user): RedirectResponse
    {
        $this->assertCanManage($request, $school_class);

        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $school_class->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return back()->with('error', 'عضویت یافت نشد.');
        }

        $enrollment->update([
            'status'      => 'active',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
            'ended_at'    => null,
        ]);

        return back()->with('success', 'دانش‌آموز دوباره به کلاس بازگشت.');
    }

    public function updateStudentStatus(Request $request, SchoolClass $school_class, User $user): RedirectResponse
    {
        $this->assertCanManage($request, $school_class);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'invited', 'ended'])],
        ]);

        $enrollment = ClassEnrollment::query()
            ->where('school_class_id', $school_class->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return back()->with('error', 'عضویت یافت نشد.');
        }

        $enrollment->update([
            'status'      => $data['status'],
            'enrolled_at' => $data['status'] !== 'ended'
                ? ($enrollment->enrolled_at ?? now())
                : $enrollment->enrolled_at,
            'ended_at'    => $data['status'] === 'ended' ? now() : null,
        ]);

        return back()->with('success', 'وضعیت عضویت به‌روز شد.');
    }

    public function assignTeacher(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertCanManage($request, $school_class);

        $data = $request->validate([
            'mobile'       => ['nullable', 'string', 'max:20'],
            'user_id'      => ['nullable', 'uuid', 'exists:users,id'],
            'display_name' => ['nullable', 'string', 'max:160'],
        ]);

        if (empty($data['mobile']) && empty($data['user_id'])) {
            return back()->withErrors(['mobile' => 'موبایل یا انتخاب معلم الزامی است.']);
        }

        $this->roster->assignTeacher(
            $school_class,
            $data['mobile'] ?? null,
            $data['user_id'] ?? null,
            $data['display_name'] ?? null
        );

        return back()->with('success', 'معلم کلاس ثبت شد.');
    }

    protected function assertCanManage(Request $request, SchoolClass $class): void
    {
        $user = $request->user();

        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }

        if ($this->permissions->managesSchool($user, $class->school_id)) {
            return;
        }

        abort(403, 'به این کلاس دسترسی ندارید.');
    }
}
