<?php

namespace Tests\Feature\Api;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolClassApiTest extends TestCase
{
    use RefreshDatabase;

    private const SHAPE = ['id', 'school_id', 'name', 'grade', 'academic_year', 'teacher_user_id', 'status'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeSchool(array $attrs = []): School
    {
        return School::create(array_merge([
            'name'   => 'مدرسه ' . Str::random(6),
            'code'   => 'S' . Str::random(8),
            'city'   => 'کرمان',
            'status' => 'active',
        ], $attrs));
    }

    private function makeClass(School $school, array $attrs = []): SchoolClass
    {
        return SchoolClass::create(array_merge([
            'school_id'     => $school->id,
            'name'          => 'کلاس ' . Str::random(6),
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ], $attrs));
    }

    private function userWithRole(string $roleCode, School $school): User
    {
        $user = User::create([
            'mobile_e164'  => '+98912' . random_int(1000000, 9999999),
            'display_name' => 'کاربر ' . $roleCode,
            'status'       => 'active',
        ]);

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', $roleCode)->firstOrFail()->id,
        ]));

        return $user;
    }

    private function actAs(string $roleCode, School $school): User
    {
        $user = $this->userWithRole($roleCode, $school);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'school_id'     => null,
            'name'          => 'دهم الف',
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ], $override);
    }

    // ───────────── احراز هویت ─────────────

    public function test_all_routes_require_token(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);

        $this->getJson('/api/v1/classes')->assertUnauthorized();
        $this->postJson('/api/v1/classes', [])->assertUnauthorized();
        $this->getJson("/api/v1/classes/{$class->id}")->assertUnauthorized();
        $this->putJson("/api/v1/classes/{$class->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/classes/{$class->id}")->assertUnauthorized();
    }

    // ───────────── GET /classes ─────────────

    public function test_admin_lists_all_classes(): void
    {
        $school1 = $this->makeSchool();
        $school2 = $this->makeSchool();
        $this->makeClass($school1);
        $this->makeClass($school2);
        $this->actAs('admin', $school1);

        $this->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => ['*' => self::SHAPE],
            ]);
    }

    public function test_manager_lists_only_managed_school_classes(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $this->makeClass($mine);
        $this->makeClass($other);
        $this->actAs('school_manager', $mine);

        $this->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ───────────── POST /classes ─────────────

    public function test_admin_creates_class(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin', $school);

        $this->postJson('/api/v1/classes', $this->payload(['school_id' => $school->id]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'دهم الف')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_teacher_cannot_create_class(): void
    {
        $school = $this->makeSchool();
        $this->actAs('teacher', $school);

        $this->postJson('/api/v1/classes', $this->payload(['school_id' => $school->id]))
            ->assertForbidden();
    }

    public function test_create_validation_errors(): void
    {
        $school = $this->makeSchool();
        $this->actAs('admin', $school);

        $this->postJson('/api/v1/classes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'school_id', 'status']);
    }

    // ───────────── GET /classes/{class} ─────────────

    public function test_admin_shows_any_class(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->getJson("/api/v1/classes/{$class->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $class->id);
    }

    public function test_manager_shows_own_school_class_but_not_others(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $myClass = $this->makeClass($mine);
        $otherClass = $this->makeClass($other);
        $this->actAs('school_manager', $mine);

        $this->getJson("/api/v1/classes/{$myClass->id}")->assertOk();
        $this->getJson("/api/v1/classes/{$otherClass->id}")->assertForbidden();
    }

    // ───────────── PUT /classes/{class} ─────────────

    public function test_admin_updates_class(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->putJson("/api/v1/classes/{$class->id}", $this->payload([
            'school_id' => $school->id,
            'name'      => 'نام جدید',
            'grade'     => 'یازدهم',
        ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'نام جدید')
            ->assertJsonPath('data.grade', 'یازدهم');
    }

    // ───────────── DELETE /classes/{class} ─────────────

    public function test_admin_deletes_empty_class(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->deleteJson("/api/v1/classes/{$class->id}")->assertNoContent();
        $this->assertDatabaseMissing('school_classes', ['id' => $class->id]);
    }

    public function test_delete_class_with_enrollments_is_409(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $student = $this->userWithRole('student', $school);

        ClassEnrollment::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'user_id'         => $student->id,
            'status'          => 'active',
            'enrolled_at'     => now(),
        ]);

        $this->actAs('admin', $school);

        $this->deleteJson("/api/v1/classes/{$class->id}")
            ->assertStatus(409);
    }

    // ───────────── Roster ─────────────

    public function test_manager_can_list_roster(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $student = $this->userWithRole('student', $school);

        ClassEnrollment::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'user_id'         => $student->id,
            'status'          => 'active',
            'enrolled_at'     => now(),
        ]);

        $this->actAs('school_manager', $school);

        $this->getJson("/api/v1/classes/{$class->id}/roster")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_can_invite_student(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('school_manager', $school);

        $this->postJson("/api/v1/classes/{$class->id}/students", [
            'mobile'       => '09123334455',
            'display_name' => 'علی دانش',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'invited');
    }

    // ───────────── Schedule ─────────────

    public function test_manager_can_save_schedule(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('school_manager', $school);

        $this->postJson("/api/v1/classes/{$class->id}/schedule", [
            'slots' => [
                ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'A1'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('conflicts', []);
    }

    // ───────────── Promote ─────────────

    public function test_manager_can_promote_class(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('school_manager', $school);

        $this->postJson("/api/v1/classes/{$class->id}/promote", [
            'academic_year'  => '1406-1407',
            'grade'          => 'یازدهم',
            'mode'           => 'update',
            'carry_students' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.academic_year', '1406-1407')
            ->assertJsonPath('data.grade', 'یازدهم');
    }
}