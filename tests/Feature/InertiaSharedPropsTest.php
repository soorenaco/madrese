<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\PermissionSeeder;
use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function makeUser(string $status = 'active'): User
    {
        return User::create([
            'mobile_e164'  => '+989123456789',
            'display_name' => 'تست کاربر',
            'status'       => $status,
        ]);
    }

    protected function giveRole(User $user, string $roleCode, string $membershipStatus = 'active'): Role
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        $school = School::create([
            'name'   => 'مدرسه تست ' . Str::random(6),
            'status' => 'active',
        ]);

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => $membershipStatus,
            'ended_at'  => $membershipStatus === 'ended' ? now() : null,
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
        ]));

        return $role;
    }

    public function test_guest_gets_null_auth_prop(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth', null)
            );
    }

    public function test_user_without_roles_gets_empty_roles_and_permissions(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.mobile', '+989123456789')
                ->where('auth.user.display_name', 'تست کاربر')
                ->where('auth.user.status', 'active')
                ->where('auth.roles', [])
                ->where('auth.permissions', [])
                ->where('auth.is_admin', false)
            );
    }

    public function test_teacher_gets_role_with_title_and_assigned_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();
        $role = $this->giveRole($user, 'teacher');

        $permission = Permission::firstOrCreate(
            ['code' => 'class.view'],
            [
                'id'          => (string) Str::uuid(),
                'resource'    => 'class',
                'action'      => 'view',
                'description' => 'مشاهده کلاس',
            ]
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id => ['effect' => 'allow'],
        ]);

        app(PermissionService::class)->clearUserCache($user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles.0.code', 'teacher')
                ->where('auth.roles.0.title', 'معلم')
                ->where('auth.is_admin', false)
                ->where('auth.permissions', fn ($codes) => collect($codes)->contains('class.view'))
            );
    }

    public function test_admin_gets_all_permissions_and_is_admin_true(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();
        $this->giveRole($user, 'admin');

        app(PermissionService::class)->clearUserCache($user);

        $permissionCount = Permission::count();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.is_admin', true)
                ->has('auth.permissions', $permissionCount)
                ->where('auth.permissions', fn ($codes) => collect($codes)->contains('platform.manage'))
            );
    }

    public function test_ended_membership_role_is_not_shared(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'teacher', 'ended');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles', [])
                ->where('auth.permissions', [])
            );
    }

    public function test_new_role_is_visible_after_cache_is_cleared(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.roles', []));

        $this->giveRole($user, 'student');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.roles', []));

        app(PermissionService::class)->clearUserCache($user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.0.code', 'student')
                ->where('auth.roles.0.title', 'دانش‌آموز')
            );
    }
}