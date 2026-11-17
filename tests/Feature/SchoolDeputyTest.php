<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolDeputyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    /**
     * ساخت مدرسه + مدیر اصلی
     */
    protected function makeSchoolWithPrimaryManager(): array
    {
        $manager = User::create([
            'mobile_e164'  => '+989121000001',
            'display_name' => 'مدیر اصلی',
            'status'       => 'active',
        ]);

        $school = School::create([
            'name'   => 'مدرسه تست',
            'code'   => 'T1',
            'status' => 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $manager->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => true,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', 'school_manager')->firstOrFail()->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($manager);

        return compact('manager', 'school', 'membership');
    }

    /**
     * ساخت معاون برای مدرسه
     */
    protected function makeDeputy(School $school, string $mobile, bool $isPrimary = false): User
    {
        $deputy = User::create([
            'mobile_e164'  => $mobile,
            'display_name' => 'معاون',
            'status'       => 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $deputy->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => $isPrimary,
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => Role::where('code', 'school_manager')->firstOrFail()->id,
            'granted_at'    => now(),
        ]);

        app(PermissionService::class)->clearUserCache($deputy);

        return $deputy;
    }

    // ───────────── Tests ─────────────

    public function test_primary_manager_can_view_deputies_page(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->makeSchoolWithPrimaryManager();

        $this->actingAs($manager)
            ->get("/school/{$school->id}/deputies")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('School/Deputies'));
    }

    public function test_primary_manager_can_set_deputy_limits(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->makeSchoolWithPrimaryManager();
        $deputy = $this->makeDeputy($school, '+989121000002');

        $this->actingAs($manager)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy->id,
                'limits'  => [
                    'can_invite_students' => true,
                    'can_manage_schedule' => false,
                    'can_promote_class'   => true,
                    'can_edit_school'     => false,
                    'can_manage_deputies' => false,
                ],
            ])
            ->assertRedirect();

        $membership = SchoolMembership::where('school_id', $school->id)
            ->where('user_id', $deputy->id)
            ->first();

        $this->assertNotNull($membership->manager_limits);
        $this->assertTrue($membership->manager_limits['can_invite_students']);
        $this->assertFalse($membership->manager_limits['can_manage_schedule']);
    }

    public function test_deputy_cannot_set_own_limits(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->makeSchoolWithPrimaryManager();
        $deputy = $this->makeDeputy($school, '+989121000003');

        $this->actingAs($deputy)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy->id,
                'limits'  => ['can_manage_deputies' => true],
            ])
            ->assertForbidden();
    }

    public function test_deputy_with_can_manage_deputies_can_set_limits(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->makeSchoolWithPrimaryManager();
        $deputy1 = $this->makeDeputy($school, '+989121000004');
        $deputy2 = $this->makeDeputy($school, '+989121000005');

        // به deputy1 دسترسی can_manage_deputies می‌دیم
        $membership1 = SchoolMembership::where('user_id', $deputy1->id)->first();
        $membership1->update([
            'manager_limits' => [
                'can_invite_students' => false,
                'can_manage_schedule' => false,
                'can_promote_class'   => false,
                'can_edit_school'     => false,
                'can_manage_deputies' => true,
            ],
        ]);
        app(PermissionService::class)->clearUserCache($deputy1);

        $this->actingAs($deputy1)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy2->id,
                'limits'  => ['can_invite_students' => true],
            ])
            ->assertRedirect();
    }

    public function test_deputy_without_can_manage_deputies_cannot_set_limits(): void
    {
        ['manager' => $manager, 'school' => $school] = $this->makeSchoolWithPrimaryManager();
        $deputy1 = $this->makeDeputy($school, '+989121000006');
        $deputy2 = $this->makeDeputy($school, '+989121000007');

        // deputy1 هیچ دسترسی‌ای نداره
        $membership1 = SchoolMembership::where('user_id', $deputy1->id)->first();
        $membership1->update([
            'manager_limits' => [
                'can_invite_students' => false,
                'can_manage_schedule' => false,
                'can_promote_class'   => false,
                'can_edit_school'     => false,
                'can_manage_deputies' => false,
            ],
        ]);
        app(PermissionService::class)->clearUserCache($deputy1);

        $this->actingAs($deputy1)
            ->post("/school/{$school->id}/deputy-limits", [
                'user_id' => $deputy2->id,
                'limits'  => ['can_invite_students' => true],
            ])
            ->assertForbidden();
    }
}