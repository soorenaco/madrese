<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeputyLimitsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function makeSchoolWithPrimary(): array
    {
        $primary = User::create([
            'mobile_e164'  => '+989121000001',
            'display_name' => 'مدیر اصلی',
            'status'       => 'active',
        ]);

        $school = School::create([
            'name'   => 'مدرسه تست محدودیت',
            'code'   => 'DL1',
            'status' => 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $primary->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => true,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', 'school_manager')->firstOrFail()->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($primary);

        $class = SchoolClass::create([
            'school_id'     => $school->id,
            'name'          => 'دهم الف',
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ]);

        return compact('primary', 'school', 'class', 'membership');
    }

    /**
     * معاون با وضعیت مشخص (active یا invited)
     */
    protected function makeDeputy(School $school, string $mobile, string $status = 'active'): User
    {
        $deputy = User::create([
            'mobile_e164'  => $mobile,
            'display_name' => 'معاون تست',
            'status'       => $status === 'invited' ? 'invited' : 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $deputy->id,
            'status'             => $status,
            'joined_at'          => now(),
            'is_primary_manager' => false,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', 'school_manager')->firstOrFail()->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($deputy);

        return $deputy;
    }

    protected function setLimits(User $deputy, School $school, array $limits): void
    {
        $m = SchoolMembership::where('school_id', $school->id)
            ->where('user_id', $deputy->id)
            ->firstOrFail();

        $m->update(['manager_limits' => array_merge([
            'can_invite_students' => false,
            'can_manage_schedule' => false,
            'can_promote_class'   => false,
            'can_edit_school'     => false,
            'can_manage_deputies' => false,
        ], $limits)]);

        app(PermissionService::class)->clearUserCache($deputy);
    }

    // ─────────────────────────────────────────────
    // ۱. باگ ۴۰۴ — ذخیره محدودیت برای معاون invited
    // ─────────────────────────────────────────────

    public function test_can_set_limits_on_invited_deputy(): void
    {
        ['primary' => $primary, 'school' => $school] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000010', 'invited');

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
            ->assertRedirect(); // نباید 404 بدهد

        $m = SchoolMembership::where('school_id', $school->id)
            ->where('user_id', $deputy->id)
            ->first();

        $this->assertNotNull($m->manager_limits);
        $this->assertTrue($m->manager_limits['can_invite_students']);
        $this->assertSame('invited', $m->status);
    }

    public function test_can_set_limits_on_active_deputy(): void
    {
        ['primary' => $primary, 'school' => $school] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000011', 'active');

        $this->actingAs($primary)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy->id,
                'limits'  => [
                    'can_invite_students' => false,
                    'can_manage_schedule' => true,
                    'can_promote_class'   => false,
                    'can_edit_school'     => false,
                    'can_manage_deputies' => false,
                ],
            ])
            ->assertRedirect();

        $m = SchoolMembership::where('user_id', $deputy->id)->first();
        $this->assertTrue($m->manager_limits['can_manage_schedule']);
    }

    // ─────────────────────────────────────────────
    // ۲. can_invite_students
    // ─────────────────────────────────────────────

    public function test_deputy_without_can_invite_students_cannot_invite(): void
    {
        ['primary' => $primary, 'school' => $school, 'class' => $class] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000020');
        $this->setLimits($deputy, $school, ['can_invite_students' => false]);

        $this->actingAs($deputy)
            ->post("/classes/{$class->id}/students", [
                'mobile'       => '09123334455',
                'display_name' => 'شاگرد تست',
            ])
            ->assertForbidden();
    }

    public function test_deputy_with_can_invite_students_can_invite(): void
    {
        ['primary' => $primary, 'school' => $school, 'class' => $class] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000021');
        $this->setLimits($deputy, $school, ['can_invite_students' => true]);

        $this->actingAs($deputy)
            ->post("/classes/{$class->id}/students", [
                'mobile'       => '09123334456',
                'display_name' => 'شاگرد تست ۲',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'mobile_e164' => '+989123334456',
        ]);
    }

    public function test_primary_manager_can_always_invite(): void
    {
        ['primary' => $primary, 'class' => $class] = $this->makeSchoolWithPrimary();

        $this->actingAs($primary)
            ->post("/classes/{$class->id}/students", [
                'mobile' => '09123334457',
            ])
            ->assertRedirect();
    }

    // ─────────────────────────────────────────────
    // ۳. can_edit_school
    // ─────────────────────────────────────────────

    public function test_deputy_without_can_edit_school_cannot_update_school(): void
    {
        ['primary' => $primary, 'school' => $school] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000030');
        $this->setLimits($deputy, $school, ['can_edit_school' => false]);

        $this->actingAs($deputy)
            ->put("/school/{$school->id}", [
                'name'   => 'نام جدید',
                'code'   => $school->code,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_deputy_with_can_edit_school_can_update_school(): void
    {
        ['primary' => $primary, 'school' => $school] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000031');
        $this->setLimits($deputy, $school, ['can_edit_school' => true]);

        $this->actingAs($deputy)
            ->put("/school/{$school->id}", [
                'name'   => 'نام جدید مدرسه',
                'code'   => $school->code,
                'status' => 'active',
            ])
            ->assertRedirect();

        $school->refresh();
        $this->assertSame('نام جدید مدرسه', $school->name);
    }

    // ─────────────────────────────────────────────
    // ۴. can_manage_schedule (قبلاً کار می‌کرد — تأیید)
    // ─────────────────────────────────────────────

    public function test_deputy_without_can_manage_schedule_cannot_save_schedule(): void
    {
        ['primary' => $primary, 'school' => $school, 'class' => $class] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000040');
        $this->setLimits($deputy, $school, ['can_manage_schedule' => false]);

        $this->actingAs($deputy)
            ->post("/classes/{$class->id}/schedule", [
                'slots' => [
                    ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'A1'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_deputy_with_can_manage_schedule_can_save_schedule(): void
    {
        ['primary' => $primary, 'school' => $school, 'class' => $class] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000041');
        $this->setLimits($deputy, $school, ['can_manage_schedule' => true]);

        $this->actingAs($deputy)
            ->post("/classes/{$class->id}/schedule", [
                'slots' => [
                    ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '09:30', 'room' => 'A1'],
                ],
            ])
            ->assertRedirect();
    }

    // ─────────────────────────────────────────────
    // ۵. manager_limits = null → دسترسی کامل (سازگاری)
    // ─────────────────────────────────────────────

    public function test_deputy_with_null_limits_has_full_access(): void
    {
        ['primary' => $primary, 'school' => $school, 'class' => $class] = $this->makeSchoolWithPrimary();
        $deputy = $this->makeDeputy($school, '+989121000050');
        // عمداً manager_limits را null می‌گذاریم

        $this->actingAs($deputy)
            ->post("/classes/{$class->id}/students", [
                'mobile' => '09123334458',
            ])
            ->assertRedirect(); // باید موفق باشد
    }
}