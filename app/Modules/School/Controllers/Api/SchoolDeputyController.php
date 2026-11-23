<?php

namespace App\Modules\School\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolDeputyController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
        protected SchoolManagerGate $gate
    ) {}

    public function index(Request $request, School $school): JsonResponse
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
                'is_primary_manager' => (bool) $m->is_primary_manager,
                'manager_limits'     => $m->manager_limits,
                'status'             => $m->status,        
            ]);

        return response()->json([
            'data' => $managers,
            'meta' => [
                'limit_keys' => SchoolManagerGate::LIMIT_KEYS,
                'can_manage' => $this->gate->isPrimaryManager($request->user(), $school->id)
                    || $this->permissions->isPlatformAdmin($request->user())
                    || $this->gate->allows($request->user(), $school->id, 'can_manage_deputies'),
            ],
        ]);
    }

    public function setPrimary(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $target = User::findOrFail($data['user_id']);
        $this->gate->setPrimaryManager($request->user(), $school->id, $target);

        return response()->json(['message' => 'مدیر اصلی مدرسه تنظیم شد.']);
    }

    public function setLimits(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);

        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'limits'  => ['required', 'array'],
        ]);

        $target = User::findOrFail($data['user_id']);
        $this->gate->setDeputyLimits($request->user(), $school->id, $target, $data['limits']);

        return response()->json(['message' => 'محدودیت معاون ذخیره شد.']);
    }

    protected function assertSchoolAccess(Request $request, School $school): void
    {
        $user = $request->user();
        if ($this->permissions->isPlatformAdmin($user)) {
            return;
        }
        abort_unless($this->permissions->managesSchool($user, $school->id), 403, 'به این مدرسه دسترسی ندارید.');
    }
}