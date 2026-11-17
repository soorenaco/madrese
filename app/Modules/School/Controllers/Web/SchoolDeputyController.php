<?php

namespace App\Modules\School\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SchoolDeputyController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolManagerGate $gate
    ) {}

    public function show(Request $request, School $school): Response
    {
        $this->assertSchoolAccess($request, $school);

        $managers = SchoolMembership::query()
            ->where('school_id', $school->id)
            ->whereIn('status', ['active', 'invited'])  
            ->whereNull('ended_at')
            ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
            ->with('user:id,display_name,mobile_e164')
            ->get()
            ->map(fn (SchoolMembership $m) => [
                'user_id'            => $m->user_id,
                'display_name'       => $m->user?->display_name,
                'mobile'             => $m->user?->mobile_e164,
                'status'             => $m->status,         
                'is_primary_manager' => (bool) $m->is_primary_manager,
                'manager_limits'     => $m->manager_limits,
            ]);

        return Inertia::render('School/Deputies', [
            'school'    => $school->only(['id', 'name', 'code']),
            'managers'  => $managers,
            'limitKeys' => SchoolManagerGate::LIMIT_KEYS,
            'canManage' => $this->gate->isPrimaryManager($request->user(), $school->id)
                || $this->permissions->isPlatformAdmin($request->user())
                || $this->gate->allows($request->user(), $school->id, 'can_manage_deputies'),
        ]);
    }

    public function addDeputy(Request $request, School $school): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school);

        $data = $request->validate([
            'mobile'       => ['required', 'string', 'max:20'],
            'display_name' => ['nullable', 'string', 'max:160'],
        ]);

        $normalized = app(\App\Modules\Class\Services\ClassRosterService::class)
            ->normalizeMobile($data['mobile']);

        $user = User::where('mobile_e164', $normalized)->first();

        if (! $user) {
            $user = User::create([
                'mobile_e164'  => $normalized,
                'display_name' => $data['display_name'] ?: ('معاون ' . substr($normalized, -4)),
                'status'       => 'invited',
            ]);
        }

        // آیا این اولین school_manager این مدرسه هست؟
        $isFirstManager = ! SchoolMembership::query()
            ->where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
            ->exists();

        $membership = SchoolMembership::where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            $membership = SchoolMembership::create([
                'id'                 => (string) Str::uuid(),
                'school_id'          => $school->id,
                'user_id'            => $user->id,
                'status'             => $user->status === 'invited' ? 'invited' : 'active',
                'joined_at'          => now(),
                'is_primary_manager' => $isFirstManager,
            ]);
        } else {
            $membership->update([
                'is_primary_manager' => $isFirstManager || $membership->is_primary_manager,
            ]);
        }

        $role = Role::where('code', 'school_manager')->firstOrFail();

        MembershipRole::unguarded(function () use ($membership, $role) {
            MembershipRole::query()->updateOrCreate(
                ['membership_id' => $membership->id, 'role_id' => $role->id],
                ['granted_at' => now(), 'revoked_at' => null]
            );
        });

        $this->permissions->clearUserCache($user);

        $msg = $isFirstManager
            ? 'اولین کاربر به‌عنوان مدیر اصلی مدرسه ثبت شد.'
            : 'معاون به مدرسه اضافه شد.';

        return back()->with('success', $msg);
    }


    public function setPrimary(Request $request, School $school): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $target = User::findOrFail($data['user_id']);
        $this->gate->setPrimaryManager($request->user(), $school->id, $target);

        return back()->with('success', 'مدیر اصلی مدرسه تنظیم شد.');
    }

    public function setLimits(Request $request, School $school): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'limits'  => ['required', 'array'],
        ]);

        $target = User::findOrFail($data['user_id']);
        $this->gate->setDeputyLimits($request->user(), $school->id, $target, $data['limits']);

        return back()->with('success', 'محدودیت معاون ذخیره شد.');
    }

    protected function assertSchoolAccess(Request $request, School $school): void
    {
        $user = $request->user();
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        abort_unless($this->permissions->managesSchool($user, $school->id), 403);
    }
}