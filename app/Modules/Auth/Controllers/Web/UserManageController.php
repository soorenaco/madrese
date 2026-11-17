<?php

namespace App\Modules\Auth\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\School\Models\School;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManageController extends Controller
{
    public function __construct(
        protected PermissionService $permissions
    ) {}

    public function index(Request $request): Response
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

        $schoolsQuery = School::query()->where('status', 'active')->orderBy('name');
        if ($managedIds !== null) {
            $schoolsQuery->whereIn('id', $managedIds ?: ['00000000-0000-0000-0000-000000000000']);
        }

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'filters' => [
                'q'      => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
            ],
            'roles'   => Role::query()->orderBy('title')->get(['id', 'code', 'title']),
            'schools' => $schoolsQuery->get(['id', 'name']),
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->assertCanTouchUser($request, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(['invited', 'active', 'blocked'])],
        ]);

        $user->update(['status' => $data['status']]);

        return back()->with('success', 'وضعیت کاربر به‌روز شد.');
    }

    public function assignMembership(Request $request, User $user): RedirectResponse
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

        // معاون نمی‌تونه نقش school_manager بده (مگر با can_manage_deputies)
        if ($role->code === 'school_manager'
            && ! $this->permissions->isPlatformAdmin($request->user())
            && ! app(\App\Modules\School\Services\SchoolManagerGate::class)->allows(
                $request->user(),
                $data['school_id'],
                'can_manage_deputies'
            )
        ) {
            abort(403, 'اجازه اعطای نقش مدیر مدرسه را ندارید.');
        }

        // آیا این اولین school_manager این مدرسه هست؟
        $isFirstManager = $role->code === 'school_manager'
            && ! SchoolMembership::query()
                ->where('school_id', $data['school_id'])
                ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
                ->exists();

        $membership = SchoolMembership::query()
            ->where('school_id', $data['school_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            $membership = SchoolMembership::create([
                'id'                 => (string) Str::uuid(),
                'school_id'          => $data['school_id'],
                'user_id'            => $user->id,
                'status'             => $data['status'],
                'joined_at'          => $data['status'] === 'active' ? now() : null,
                'ended_at'           => $data['status'] === 'ended' ? now() : null,
                'is_primary_manager' => $isFirstManager,
            ]);
        } else {
            $membership->update([
                'status'             => $data['status'],
                'ended_at'           => $data['status'] === 'ended' ? now() : null,
                'is_primary_manager' => $isFirstManager || $membership->is_primary_manager,
            ]);
        }

        MembershipRole::unguarded(function () use ($membership, $data) {
            MembershipRole::query()->updateOrCreate(
                ['membership_id' => $membership->id, 'role_id' => $data['role_id']],
                ['granted_at' => now(), 'revoked_at' => null]
            );
        });

        $this->permissions->clearUserCache($user);

        $msg = $isFirstManager
            ? 'کاربر به‌عنوان مدیر اصلی مدرسه ثبت شد.'
            : 'عضویت و نقش کاربر ثبت شد.';

        return back()->with('success', $msg);
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
