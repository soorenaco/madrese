<?php

namespace App\Modules\Class\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Services\ClassPromoteService;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassPromoteController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected ClassPromoteService $promoteService,
        protected SchoolManagerGate $gate
    ) {}

    public function show(Request $request, SchoolClass $school_class): Response
    {
        $this->assertAccess($request, $school_class);

        $school_class->load(['school:id,name', 'teacher:id,display_name,mobile_e164']);

        return Inertia::render('Classes/Promote', [
            'classItem' => $school_class,
        ]);
    }

    public function store(Request $request, SchoolClass $school_class): RedirectResponse
    {
        $this->assertAccess($request, $school_class);

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

        $result = $this->promoteService->promote($school_class, $data);

        return redirect()
            ->route('panel.classes.roster', $result)
            ->with('success', $data['mode'] === 'clone'
                ? 'کلاس جدید برای سال تحصیلی ساخته شد.'
                : 'اطلاعات سال/پایه کلاس به‌روز شد.');
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
        if (! $this->gate->allows($user, $class->school_id, 'can_promote_class')
            && ! $this->gate->isPrimaryManager($user, $class->school_id)) {
            // مدیر اصلی همیشه؛ معاون فقط با مجوز
            if (! $this->gate->isPrimaryManager($user, $class->school_id)) {
                $m = $this->gate->primaryMembership($user, $class->school_id);
                if (! $m?->is_primary_manager && ! ($m->manager_limits['can_promote_class'] ?? false)) {
                    abort(403, 'اجازه ارتقای کلاس را ندارید.');
                }
            }
        }
    }
}
