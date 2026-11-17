<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\School;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Services\SchoolManagerGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManageController extends Controller
{
    public function __construct(
        protected PermissionService $permissions
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $managedIds = $this->permissions->managedSchoolIds($user);

        $users = User::query()
            ->with(['memberships' => function ($q) use ($managedIds) {
                $q->with(['roles:id,code,title', 'school:id,name']);
                if ($managedIds !== null) {
                    $q->whereIn('school_id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']);
                }
            }])
            ->when($managedIds !== null, function ($q) use ($managedIds) {
                $ids = $managedIds ?: ['00000000-0000-0000-0000-000000000000'];
                $q->whereHas('memberships', fn ($m) => $m->whereIn('school_id', $ids));
            })
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('display_name', 'ilike', "%{$term}%")
                        ->orWhere('mobile_e164', 'ilike', "%{$term}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id'           => $u->id,
                'mobile'       => $u->mobile_e164,
                'display_name' => $u->display_name,
                'status'       => $u->status,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'memberships'  => $u->memberships->map(fn (SchoolMembership $m) => [
                    'id'        => $m->id,
                    'school_id' => $m->school_id,
                    'school'    => $m->school?->name,
                    'status'    => $m->status,
                    'roles'     => $m->roles->map(fn ($r) => ['code' => $r->code, 'title' => $r->title]),
                ]),
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'per_page'     => $users->perPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->assertCanTouchUser($request, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(['invited', 'active', 'blocked'])],
        ]);

        $user->update(['status' => $data['status']]);

        return response()->json(['message' => 'وضعیت کاربر به‌روز شد.']);
    }

    public function assignMembership(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'uuid', 'exists:schools,id'],
            'role_id'   => ['required', 'uuid', 'exists:roles,id'],
            'status'    => ['required', Rule::in(['invited', 'active', 'suspended', 'ended'])],
        ]);

        abort_unless($this->permissions->managesSchool($request->user(), $data['school_id']), 403);

        // فقط ادمین می‌تونه نقش admin بده
        $role = Role::findOrFail($data['role_id']);
        if ($role->code === 'admin' && ! $this->permissions->isPlatformAdmin($request->user())) {
            abort(403, 'فقط ادمین پلتفرم می‌تواند نقش admin اعطا کند.');
        }

        $membership = SchoolMembership::query()
            ->where('school_id', $data['school_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            $membership = SchoolMembership::create([
                'id'        => (string) \Illuminate\Support\Str::uuid(),
                'school_id' => $data['school_id'],
                'user_id'   => $user->id,
                'status'    => $data['status'],
                'joined_at' => $data['status'] === 'active' ? now() : null,
                'ended_at'  => $data['status'] === 'ended' ? now() : null,
            ]);
        } else {
            $membership->update([
                'status'   => $data['status'],
                'ended_at' => $data['status'] === 'ended' ? now() : null,
            ]);
        }

        MembershipRole::unguarded(function () use ($membership, $data) {
            MembershipRole::query()->updateOrCreate(
                ['membership_id' => $membership->id, 'role_id' => $data['role_id']],
                ['granted_at' => now(), 'revoked_at' => null]
            );
        });

        $this->permissions->clearUserCache($user);

        return response()->json(['message' => 'عضویت و نقش کاربر ثبت شد.']);
    }

    protected function assertCanTouchUser(Request $request, User $target): void
    {
        if ($this->permissions->isPlatformAdmin($request->user())) {
            return;
        }

        $managedIds = $this->permissions->managedSchoolIds($request->user()) ?: [];

        $inScope = SchoolMembership::query()
            ->where('user_id', $target->id)
            ->whereIn('school_id', $managedIds ?: ['00000000-0000-0000-0000-000000000000'])
            ->exists();

        abort_unless($inScope, 403);
    }
}