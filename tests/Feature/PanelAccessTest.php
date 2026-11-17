<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\PermissionSeeder;
use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

use App\Modules\School\Models\School;

class PanelAccessTest extends TestCase
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

        $school = School::create([
            'name'   => 'مدرسه تست ' . Str::random(6),
            'status' => 'active',
        ]);

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
        ]));

        app(PermissionService::class)->clearUserCache($user);

        return $role;
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $this->get('/classes')->assertRedirect('/login');
        $this->get('/school')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_user_without_role_gets_403_on_classes(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/classes')
            ->assertForbidden();
    }

    public function test_teacher_can_access_classes(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'teacher');

        $this->actingAs($user)
            ->get('/classes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Classes/Index'));
    }

    public function test_teacher_cannot_access_school_or_settings(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'teacher');

        $this->actingAs($user)->get('/school')->assertForbidden();
        $this->actingAs($user)->get('/settings')->assertForbidden();
    }

    public function test_school_manager_can_access_school_and_users(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'school_manager');

        $this->actingAs($user)
            ->get('/school')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('School/Index'));

        $this->actingAs($user)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Users/Index'));
    }

    public function test_admin_can_access_settings(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();
        $this->giveRole($user, 'admin');

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings/Index'));
    }

    public function test_student_cannot_access_panel_sections(): void
    {
        $user = $this->makeUser();
        $this->giveRole($user, 'student');

        $this->actingAs($user)->get('/classes')->assertForbidden();
        $this->actingAs($user)->get('/school')->assertForbidden();
        $this->actingAs($user)->get('/users')->assertForbidden();
        $this->actingAs($user)->get('/settings')->assertForbidden();
    }

    public function test_permission_middleware_returns_json_for_api_style_request(): void
    {
        $this->seed(PermissionSeeder::class);

        $user = $this->makeUser();

        Route::middleware(['web', 'auth', 'permission:platform.manage'])
            ->get('/__test_permission', fn () => response()->json(['ok' => true]));

        $this->actingAs($user)
            ->getJson('/__test_permission')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_school_manager_cannot_assign_admin_role(): void
    {
    $this->seed(\App\Modules\Auth\Database\Seeders\RoleSeeder::class);

    $manager = User::create([
        'mobile_e164'  => '+989120000001',
        'display_name' => 'مدیر',
        'status'       => 'active',
    ]);
    $this->giveRole($manager, 'school_manager');

    $school = \App\Modules\School\Models\School::create([
        'name'   => 'مدرسه تست',
        'status' => 'active',
    ]);

    \App\Modules\Auth\Models\SchoolMembership::where('user_id', $manager->id)
        ->update(['school_id' => $school->id]);

    $target = User::create([
        'mobile_e164'  => '+989120000002',
        'display_name' => 'هدف',
        'status'       => 'active',
    ]);
    $adminRole = \App\Modules\Auth\Models\Role::where('code', 'admin')->firstOrFail();

    $this->actingAs($manager)
        ->post("/users/{$target->id}/membership", [
            'school_id' => $school->id,
            'role_id'   => $adminRole->id,
            'status'    => 'active',
        ])
        ->assertForbidden();
    }

    public function test_admin_can_assign_admin_role(): void
    {
        $this->seed(\App\Modules\Auth\Database\Seeders\RoleSeeder::class);

        $admin = User::create([
            'mobile_e164'  => '+989120000003',
            'display_name' => 'ادمین',
            'status'       => 'active',
        ]);
        $this->giveRole($admin, 'admin');

        $school = \App\Modules\School\Models\School::create([
            'name'   => 'مدرسه تست ۲',
            'status' => 'active',
        ]);

        \App\Modules\Auth\Models\SchoolMembership::where('user_id', $admin->id)
            ->update(['school_id' => $school->id]);

        $target = User::create([
            'mobile_e164'  => '+989120000004',
            'display_name' => 'هدف ۲',
            'status'       => 'active',
        ]);
        $adminRole = \App\Modules\Auth\Models\Role::where('code', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/users/{$target->id}/membership", [
                'school_id' => $school->id,
                'role_id'   => $adminRole->id,
                'status'    => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('membership_roles', [
            'role_id' => $adminRole->id,
        ]);
    }
}