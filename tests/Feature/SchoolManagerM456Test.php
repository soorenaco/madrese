<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\ClassScheduleSlot;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolManagerM456Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function makePrimaryManager(): array
    {
        $user = User::create([
            'mobile_e164'  => '+989121000001',
            'display_name' => 'مدیر اصلی',
            'status'       => 'active',
        ]);

        $school = School::create([
            'name'   => 'مدرسه M456',
            'code'   => 'M456',
            'status' => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();

        $membership = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $user->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => true,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($user);

        $class = SchoolClass::create([
            'school_id'     => $school->id,
            'name'          => 'دهم الف',
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ]);

        return compact('user', 'school', 'class', 'membership');
    }

    public function test_promote_updates_year_and_grade(): void
    {
        ['user' => $user, 'class' => $class] = $this->makePrimaryManager();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/promote", [
                'academic_year'  => '1406-1407',
                'grade'          => 'یازدهم',
                'mode'           => 'update',
                'carry_students' => true,
            ])
            ->assertRedirect();

        $class->refresh();
        $this->assertSame('1406-1407', $class->academic_year);
        $this->assertSame('یازدهم', $class->grade);
    }

    public function test_promote_clone_copies_students(): void
    {
        ['user' => $user, 'class' => $class] = $this->makePrimaryManager();

        $student = User::create([
            'mobile_e164'  => '+989121000099',
            'display_name' => 'شاگرد',
            'status'       => 'active',
        ]);

        ClassEnrollment::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'user_id'         => $student->id,
            'status'          => 'active',
            'enrolled_at'     => now(),
        ]);

        $this->actingAs($user)
            ->post("/classes/{$class->id}/promote", [
                'academic_year'  => '1406-1407',
                'grade'          => 'یازدهم',
                'mode'           => 'clone',
                'carry_students' => true,
            ])
            ->assertRedirect();

        $class->refresh();
        $this->assertSame('archived', $class->status);

        $newClass = SchoolClass::query()
            ->where('school_id', $class->school_id)
            ->where('academic_year', '1406-1407')
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($newClass);
        $this->assertDatabaseHas('class_enrollments', [
            'school_class_id' => $newClass->id,
            'user_id'         => $student->id,
        ]);
    }

    public function test_schedule_saves_slots(): void
    {
        ['user' => $user, 'class' => $class] = $this->makePrimaryManager();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/schedule", [
                'slots' => [
                    ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'A1'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, ClassScheduleSlot::where('school_class_id', $class->id)->count());
    }

    public function test_schedule_rejects_internal_overlap(): void
    {
        ['user' => $user, 'class' => $class] = $this->makePrimaryManager();

        $this->actingAs($user)
            ->from("/classes/{$class->id}/schedule")
            ->post("/classes/{$class->id}/schedule", [
                'slots' => [
                    ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '10:00', 'room' => null],
                    ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '11:00', 'room' => null],
                ],
            ])
            ->assertSessionHasErrors('slots');
    }

    public function test_set_primary_and_deputy_limits(): void
    {
        ['user' => $primary, 'school' => $school] = $this->makePrimaryManager();

        $deputy = User::create([
            'mobile_e164'  => '+989121000002',
            'display_name' => 'معاون',
            'status'       => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();
        $m = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $deputy->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => false,
        ]);
        MembershipRole::create([
            'membership_id' => $m->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]);

        $this->actingAs($primary)
            ->get("/school/{$school->id}/deputies")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('School/Deputies'));

        $this->actingAs($primary)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy->id,
                'limits'  => [
                    'can_invite_students' => true,
                    'can_manage_schedule' => false,
                    'can_promote_class'   => false,
                    'can_edit_school'     => false,
                    'can_manage_deputies' => false,
                ],
            ])
            ->assertRedirect();

        $m->refresh();
        $this->assertTrue($m->manager_limits['can_invite_students']);
        $this->assertFalse($m->manager_limits['can_manage_schedule']);
    }

    public function test_deputy_cannot_set_own_limits(): void
    {
        ['user' => $primary, 'school' => $school] = $this->makePrimaryManager();

        $deputy = User::create([
            'mobile_e164'  => '+989121000003',
            'display_name' => 'معاون ۲',
            'status'       => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();
        $m = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $deputy->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => false,
        ]);
        MembershipRole::create([
            'membership_id' => $m->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($deputy);

        // معاون سعی می‌کنه به خودش دسترسی بده
        $this->actingAs($deputy)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy->id,
                'limits'  => [
                    'can_invite_students' => true,
                    'can_manage_schedule' => true,
                    'can_promote_class'   => true,
                    'can_edit_school'     => true,
                    'can_manage_deputies' => true,
                ],
            ])
            ->assertForbidden();
    }
}