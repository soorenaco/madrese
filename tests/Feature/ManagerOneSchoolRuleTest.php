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

/**
 * قانون کاری: «هر مدرسه یک مدیر دارد و مدیری که چند مدرسه داشته
 * باشد نداریم».
 *
 * یعنی هر کاربر فقط در یک مدرسه می‌تواند نقش school_manager داشته باشد
 * (چه به‌عنوان مدیر اصلی is_primary_manager=true، چه به‌عنوان معاون).
 * نقش‌های دیگر (معلم، دانش‌آموز، ...) مشمول این محدودیت نیستند.
 *
 * نقاط اعمال:
 *  1) SchoolController::store       — ادمین مدرسه‌ی جدید + مدیر اولیه می‌سازد.
 *  2) SchoolDeputyController::addDeputy — مدیر اصلی به مدرسه‌اش معاون اضافه می‌کند.
 *  3) UserManageController::assignMembership — اعطای دستی نقش از پنل مدیریت کاربر.
 */
class ManagerOneSchoolRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(RoleSeeder::class);
    }

    private function makeSchool(string $name = 'مدرسه'): School
    {
        return School::create([
            'name'   => $name,
            'code'   => 'S' . Str::random(6),
            'status' => 'active',
        ]);
    }

    /**
     * ساخت یک کاربر و وصل کردنش به مدرسه به‌عنوان school_manager
     * (primary یا deputy). برمی‌گرداند: ['user', 'membership'].
     */
    private function makeManager(School $school, bool $primary = true): array
    {
        $user = User::create([
            'mobile_e164'  => '+98912' . random_int(1000000, 9999999),
            'display_name' => 'مدیر ' . Str::random(4),
            'status'       => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();

        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'                 => (string) Str::uuid(),
            'school_id'          => $school->id,
            'user_id'            => $user->id,
            'status'             => 'active',
            'joined_at'          => now(),
            'is_primary_manager' => $primary,
        ]));

        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]));

        app(PermissionService::class)->clearUserCache($user);

        return ['user' => $user, 'membership' => $membership];
    }

    private function platformAdmin(): User
    {
        $admin = User::create([
            'mobile_e164'  => '+989120000000',
            'display_name' => 'ادمین پلتفرم',
            'status'       => 'active',
        ]);
        $role = Role::where('code', 'admin')->firstOrFail();
        $baseSchool = $this->makeSchool('مدرسه‌ی پایه‌ی ادمین');
        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $baseSchool->id,
            'user_id'   => $admin->id,
            'status'    => 'active',
        ]));
        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]));
        app(PermissionService::class)->clearUserCache($admin);

        return $admin;
    }

    private function e164ToLocal(string $e164): string
    {
        // +98912XXXXXXXX → 0912XXXXXXXX (فرمت ورودی کاربر در پنل)
        return '0' . substr($e164, 3);
    }

    // ───────────── Tests ─────────────

    /**
     * ۱) مسیر «ایجاد مدرسه»: ادمین نمی‌تواند کاربری را که قبلاً مدیر
     *    مدرسه‌ی دیگری است، به‌عنوان مدیر اولیه‌ی مدرسه‌ی جدید ثبت کند.
     */
    public function test_store_school_rejects_user_who_is_already_manager_elsewhere(): void
    {
        $existingSchool = $this->makeSchool('مدرسه‌ی اول');
        ['user' => $manager] = $this->makeManager($existingSchool, primary: true);

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->post('/school', [
                'name'                  => 'مدرسه‌ی دوم',
                'code'                  => 'NEWSCH',
                'status'                => 'active',
                'manager_mobile'        => $this->e164ToLocal($manager->mobile_e164),
                'manager_display_name'  => 'مدیر تکراری',
            ])
            ->assertStatus(422);

        // مطمئن می‌شویم مدرسه‌ی دوم واقعاً ساخته نشده
        $this->assertDatabaseMissing('schools', ['code' => 'NEWSCH']);
    }

    /**
     * ۲) مسیر «افزودن معاون»: مدیر اصلی نمی‌تواند کاربری را که قبلاً در
     *    مدرسه‌ی دیگری مدیر است، به‌عنوان معاون به مدرسه‌ی خود اضافه کند.
     */
    public function test_add_deputy_rejects_user_who_is_already_manager_elsewhere(): void
    {
        $schoolA = $this->makeSchool('مدرسه الف');
        $schoolB = $this->makeSchool('مدرسه ب');

        ['user' => $primary] = $this->makeManager($schoolA, primary: true);
        ['user' => $alreadyManager] = $this->makeManager($schoolB, primary: true);

        $this->actingAs($primary)
            ->post("/school/{$schoolA->id}/deputies", [
                'mobile'       => $this->e164ToLocal($alreadyManager->mobile_e164),
                'display_name' => 'معاون تکراری',
            ])
            ->assertStatus(422);

        // مطمئن می‌شویم هیچ عضویت school_manager دومی برای این کاربر در مدرسه الف نشده
        $this->assertSame(
            0,
            SchoolMembership::query()
                ->where('school_id', $schoolA->id)
                ->where('user_id', $alreadyManager->id)
                ->whereHas('roles', fn ($q) => $q->where('roles.code', 'school_manager'))
                ->count()
        );
    }

    /**
     * ۳) مسیر «اعطای نقش دستی» (UserManageController): نقش school_manager
     *    نباید به کاربری داده شود که قبلاً مدیر مدرسه‌ی دیگری است.
     */
    public function test_assign_membership_rejects_school_manager_for_user_already_manager(): void
    {
        $schoolA = $this->makeSchool('مدرسه الف');
        $schoolB = $this->makeSchool('مدرسه ب');

        ['user' => $primary] = $this->makeManager($schoolA, primary: true);
        ['user' => $alreadyManager] = $this->makeManager($schoolB, primary: true);

        $managerRole = Role::where('code', 'school_manager')->firstOrFail();

        $this->actingAs($primary)
            ->post("/users/{$alreadyManager->id}/membership", [
                'school_id' => $schoolA->id,
                'role_id'   => $managerRole->id,
                'status'    => 'active',
            ])
            ->assertStatus(422);
    }

    /**
     * ۴) حالت مثبت: کاربری که هنوز در هیچ مدرسه‌ای مدیر نیست، بدون مشکل
     *    می‌تواند معاون شود (قانون نباید جلوی انتساب اول را بگیرد).
     */
    public function test_add_deputy_allows_user_with_no_prior_manager_role(): void
    {
        $school = $this->makeSchool('مدرسه تنها');
        ['user' => $primary] = $this->makeManager($school, primary: true);

        $newbie = User::create([
            'mobile_e164'  => '+989130000000',
            'display_name' => 'کاربر تازه',
            'status'       => 'active',
        ]);

        $this->actingAs($primary)
            ->post("/school/{$school->id}/deputies", [
                'mobile'       => $this->e164ToLocal($newbie->mobile_e164),
                'display_name' => 'معاون جدید',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_memberships', [
            'school_id' => $school->id,
            'user_id'   => $newbie->id,
            'status'    => 'active',
        ]);
    }

    /**
     * ۵) نقش‌های غیر از school_manager (مثلاً teacher) مشمول این محدودیت
     *    نیستند: یک کاربر می‌تواند در چند مدرسه معلم باشد.
     */
    public function test_teacher_role_is_not_subject_to_one_school_rule(): void
    {
        $schoolA = $this->makeSchool('مدرسه الف');
        $schoolB = $this->makeSchool('مدرسه ب');

        ['user' => $primaryA] = $this->makeManager($schoolA, primary: true);
        ['user' => $primaryB] = $this->makeManager($schoolB, primary: true);

        $teacherRole = Role::where('code', 'teacher')->firstOrFail();
        $teacher = User::create([
            'mobile_e164'  => '+989140000000',
            'display_name' => 'معلم',
            'status'       => 'active',
        ]);

        // مدیر مدرسه الف معلم را در مدرسه‌اش ثبت می‌کند → باید موفق باشد.
        $this->actingAs($primaryA)
            ->post("/users/{$teacher->id}/membership", [
                'school_id' => $schoolA->id,
                'role_id'   => $teacherRole->id,
                'status'    => 'active',
            ])
            ->assertRedirect();

        // مدیر مدرسه ب همین معلم را در مدرسه‌ی خود هم ثبت می‌کند → باید موفق باشد.
        $this->actingAs($primaryB)
            ->post("/users/{$teacher->id}/membership", [
                'school_id' => $schoolB->id,
                'role_id'   => $teacherRole->id,
                'status'    => 'active',
            ])
            ->assertRedirect();

        $this->assertSame(2, SchoolMembership::query()->where('user_id', $teacher->id)->count());
    }
}
