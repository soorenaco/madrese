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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * باگ‌رفته: «موقع ثبت کلاس اسم مدرسه بالا نمیاد»
 *
 * سناریوها:
 *  - یک school_manager که فقط به یک مدرسه دسترسی دارد: فیلد مدرسه باید
 *    به‌صورت خودکار با همان مدرسه‌ی تک‌تایی پر شود (defaultSchoolId) و
 *    flag قفل فیلد (schoolLocked) برقرار باشد.
 *  - ادمین پلتفرم (که همه‌ی مدارس را می‌بیند): نباید فیلد قفل شود و باید
 *    لیست همه‌ی مدارس فعال به‌عنوان گزینه‌های انتخابی نمایش داده شود.
 *  - حالت ویرایش کلاس: فیلد مدرسه همیشه قفل است (تغییر مدرسه‌ی کلاس
 *    مجاز نیست).
 */
class ClassCreateSchoolAutofillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // محیط تست فایل‌های .vue را از طریق view-finder نمی‌بیند؛ فقط
        // propsهای Inertia را بررسی می‌کنیم و وجود فایل کامپوننت را
        // الزامی نمی‌کنیم.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(RoleSeeder::class);
    }

    /**
     * ساخت کاربر با نقش school_manager و وصل کردنش به یک یا چند مدرسه.
     */
    private function makeManager(array $schools): User
    {
        $manager = User::create([
            'mobile_e164'  => '+98912' . random_int(1000000, 9999999),
            'display_name' => 'مدیر ' . Str::random(4),
            'status'       => 'active',
        ]);

        $role = Role::where('code', 'school_manager')->firstOrFail();

        foreach ($schools as $school) {
            $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
                'id'        => (string) Str::uuid(),
                'school_id' => $school->id,
                'user_id'   => $manager->id,
                'status'    => 'active',
            ]));

            MembershipRole::unguarded(fn () => MembershipRole::create([
                'membership_id' => $membership->id,
                'role_id'       => $role->id,
                'granted_at'    => now(),
            ]));
        }

        app(PermissionService::class)->clearUserCache($manager);

        return $manager;
    }

    private function makeSchool(string $name = 'مدرسه تست'): School
    {
        return School::create([
            'name'   => $name,
            'code'   => 'S' . Str::random(6),
            'status' => 'active',
        ]);
    }

    // ───────────── Tests ─────────────

    /**
     * باگ اول: مدیر مدرسه با یک مدرسه → فیلد خودکار پر می‌شود.
     */
    public function test_manager_with_single_school_gets_school_autofilled(): void
    {
        $school = $this->makeSchool('مدرسه واحد');
        $manager = $this->makeManager([$school]);

        $this->actingAs($manager)
            ->get('/classes/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaultSchoolId', $school->id)
                ->where('schoolLocked', true)
                ->has('schools', 1)
                ->where('schools.0.id', $school->id)
                ->where('schools.0.name', 'مدرسه واحد')
            );
    }

    /**
     * ادمین پلتفرم: همه‌ی مدارس فعال، dropdown باز، بدون قفل.
     *
     * نکته: سناریوی «مدیر با چند مدرسه» حذف شد، زیرا طبق قانون کاری جدید
     * هر کاربر فقط در یک مدرسه می‌تواند نقش school_manager داشته باشد و
     * داشتن چند مدرسه غیرمجاز است. این قانون در فایل تست جداگانه‌ی
     * ManagerOneSchoolRuleTest پوشش داده می‌شود.
     */
    public function test_platform_admin_sees_all_active_schools_unlocked(): void
    {
        $this->makeSchool('مدرسه فعال ۱');
        $this->makeSchool('مدرسه فعال ۲');
        $this->makeSchool('مدرسه فعال ۳');
        // مدرسه‌ی معوق نباید در گزینه‌ها ظاهر شود
        $suspended = $this->makeSchool('مدرسه معوق');
        $suspended->update(['status' => 'suspended']);

        $admin = User::create([
            'mobile_e164'  => '+989120000000',
            'display_name' => 'ادمین',
            'status'       => 'active',
        ]);
        $role = Role::where('code', 'admin')->firstOrFail();
        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $this->makeSchool('مدرسه‌ی پایه‌ی ادمین')->id,
            'user_id'   => $admin->id,
            'status'    => 'active',
        ]));
        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $role->id,
            'granted_at'    => now(),
        ]));
        app(PermissionService::class)->clearUserCache($admin);

        $this->actingAs($admin)
            ->get('/classes/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaultSchoolId', null)
                ->where('schoolLocked', false)
                // ۳ مدرسه‌ی فعال + ۱ مدرسه‌ی پایه‌ی ادمین = ۴ مدرسه‌ی فعال
                ->has('schools', 4)
            );
    }

    /**
     * حالت ویرایش کلاس: فیلد مدرسه قفل است و نمی‌توان آن را عوض کرد.
     */
    public function test_edit_class_has_school_locked(): void
    {
        $school = $this->makeSchool('مدرسه ویرایش');
        $manager = $this->makeManager([$school]);

        $class = SchoolClass::create([
            'school_id'     => $school->id,
            'name'          => 'دهم الف',
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ]);

        $this->actingAs($manager)
            ->get("/classes/{$class->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schoolLocked', true)
                ->where('classItem.school_id', $school->id)
                ->where('classItem.name', 'دهم الف')
            );
    }

    /**
     * سناریوی کامل ثبت کلاس: مدیر تک‌مدرسه‌ای می‌تواند کلاس را بدون
     * ارسال دستی school_id در فرم (توسط autoComplete سمت کلاینت) ثبت کند.
     * در سمت سرور، school_id باید توسط کنترلر معتبر شود و به مدرسه‌ی
     * مدیریت‌شده‌ی کاربر وصل شود.
     */
    public function test_manager_can_store_class_with_autofilled_school(): void
    {
        $school = $this->makeSchool('مدرسه ثبت');
        $manager = $this->makeManager([$school]);

        $this->actingAs($manager)
            ->post('/classes', [
                // این همان مقداری است که فرم به‌صورت خودکار (hidden) ارسال می‌کند
                'school_id'     => $school->id,
                'name'          => 'کلاس هفتم',
                'grade'         => 'هفتم',
                'academic_year' => '1405-1406',
                'status'        => 'active',
            ])
            ->assertRedirect('/classes');

        $this->assertDatabaseHas('school_classes', [
            'school_id'     => $school->id,
            'name'          => 'کلاس هفتم',
            'grade'         => 'هفتم',
        ]);
    }
}
