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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use App\Modules\School\Models\School;


class PanelMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function makeUser(): User
    {
        return User::create([
            'mobile_e164'  => '+989123456789',
            'display_name' => 'تست کاربر',
            'status'       => 'active',
        ]);
    }

    protected function giveRole(User $user, string $roleCode): Role
    {
        $role = Role::where('code', $roleCode)->firstOrFail();

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => (string) Str::uuid(),
            'user_id'   => $user->id,
            'status'    => 'active',
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
        ]));

        return $role;
    }

    public function test_guest_is_redirected_from_panel_pages(): void
    {
        $this->get('/classes')->assertRedirect('/login');
        $this->get('/school')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

        public function test_authenticated_user_can_open_classes_page(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'teacher');

        $this->actingAs($user)
            ->get('/classes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Classes/Index'));
    }

    
    public function test_teacher_has_class_view_in_shared_auth(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();
        $role = $this->giveRole($user, 'teacher');

        $permission = Permission::where('code', 'class.view')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([
            $permission->id => ['effect' => 'allow'],
        ]);

        app(PermissionService::class)->clearUserCache($user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.0.code', 'teacher')
                ->where('auth.permissions', fn ($codes) => collect($codes)->contains('class.view'))
            );
    }

    public function test_admin_has_admin_flag_for_settings_menu(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();
        $this->giveRole($user, 'admin');
        app(PermissionService::class)->clearUserCache($user);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('auth.is_admin', true)
            );
    }

    public function test_school_manager_sees_school_role_in_auth(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'school_manager');
        app(PermissionService::class)->clearUserCache($user);

        $this->actingAs($user)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/Index')
                ->where('auth.roles.0.code', 'school_manager')
            );
    }
}