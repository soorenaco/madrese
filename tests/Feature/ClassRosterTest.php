<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\School\Models\School;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function makeManagerWithClass(): array
    {
        $user = User::create([
            'mobile_e164'  => '+989121111111',
            'display_name' => 'مدیر تست',
            'status'       => 'active',
        ]);

        $school = School::create([
            'name'   => 'مدرسه تست',
            'code'   => 'R1',
            'status' => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();

        $membership = SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => 'active',
            'joined_at' => now(),
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

        return compact('user', 'school', 'class');
    }

    public function test_manager_can_open_roster_page(): void
    {
        ['user' => $user, 'class' => $class] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->get("/classes/{$class->id}/roster")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Classes/Roster'));
    }

    public function test_invite_student_creates_user_membership_and_enrollment(): void
    {
        ['user' => $user, 'class' => $class, 'school' => $school] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/students", [
                'mobile'       => '09123334455',
                'display_name' => 'علی دانش',
            ])
            ->assertRedirect();

        $student = User::where('mobile_e164', '+989123334455')->first();
        $this->assertNotNull($student);
        $this->assertSame('علی دانش', $student->display_name);

        $this->assertDatabaseHas('school_memberships', [
            'school_id' => $school->id,
            'user_id'   => $student->id,
        ]);

        $this->assertDatabaseHas('class_enrollments', [
            'school_class_id' => $class->id,
            'user_id'         => $student->id,
        ]);
    }

    public function test_assign_teacher_by_mobile(): void
    {
        ['user' => $user, 'class' => $class, 'school' => $school] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/teacher", [
                'mobile'       => '09125556677',
                'display_name' => 'خانم معلم',
            ])
            ->assertRedirect();

        $teacher = User::where('mobile_e164', '+989125556677')->first();
        $this->assertNotNull($teacher);

        $class->refresh();
        $this->assertSame($teacher->id, $class->teacher_user_id);

        $this->assertDatabaseHas('school_memberships', [
            'school_id' => $school->id,
            'user_id'   => $teacher->id,
        ]);
    }

    public function test_remove_student_ends_enrollment(): void
    {
        ['user' => $user, 'class' => $class] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/students", ['mobile' => '09127778899']);

        $student = User::where('mobile_e164', '+989127778899')->firstOrFail();

        $this->actingAs($user)
            ->delete("/classes/{$class->id}/students/{$student->id}")
            ->assertRedirect();

        $enrollment = ClassEnrollment::where('school_class_id', $class->id)
            ->where('user_id', $student->id)
            ->first();

        $this->assertSame('ended', $enrollment->status);
    }

    public function test_restore_student_after_remove(): void
    {
        ['user' => $user, 'class' => $class] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/students", ['mobile' => '09120001122']);

        $student = User::where('mobile_e164', '+989120001122')->firstOrFail();

        $this->actingAs($user)
            ->delete("/classes/{$class->id}/students/{$student->id}");

        $this->actingAs($user)
            ->post("/classes/{$class->id}/students/{$student->id}/restore")
            ->assertRedirect();

        $enrollment = ClassEnrollment::where('school_class_id', $class->id)
            ->where('user_id', $student->id)
            ->first();

        $this->assertSame('active', $enrollment->status);
        $this->assertNull($enrollment->ended_at);
    }

    public function test_roster_filter_by_status(): void
    {
        ['user' => $user, 'class' => $class] = $this->makeManagerWithClass();

        $this->actingAs($user)
            ->post("/classes/{$class->id}/students", ['mobile' => '09120003344']);

        $student = User::where('mobile_e164', '+989120003344')->firstOrFail();

        $this->actingAs($user)
            ->delete("/classes/{$class->id}/students/{$student->id}");

        $this->actingAs($user)
            ->get("/classes/{$class->id}/roster?status=ended")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Classes/Roster')
                ->has('enrollments', 1)
                ->where('enrollments.0.status', 'ended')
            );
    }
}
