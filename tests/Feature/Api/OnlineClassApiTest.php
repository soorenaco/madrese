<?php

namespace Tests\Feature\Api;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\OnlineClass\Models\SystemSetting;
use App\Modules\OnlineClass\Services\Providers\BbbProvider;
use App\Modules\OnlineClass\Services\Providers\SkyroomProvider;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeMeetingProvider;
use Tests\TestCase;

class OnlineClassApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // ارائه‌دهنده‌ها را «پیکربندی‌شده» نشان می‌دهیم و کلاینت واقعی را با Fake جایگزین می‌کنیم.
        SystemSetting::set('bbb', ['base_url' => 'https://bbb.test', 'secret' => 'test-secret', 'checksum_algo' => 'sha1']);
        SystemSetting::set('skyroom', ['base_url' => 'https://skyroom.test', 'api_key' => 'test-key']);

        $this->app->bind(BbbProvider::class, fn () => new FakeMeetingProvider());
        $this->app->bind(SkyroomProvider::class, fn () => new FakeMeetingProvider());

        FakeMeetingProvider::reset();
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

    /** مدیر/معاون با نقش school_manager؛ primary یا معاون با limits. */
    private function makeManager(School $school, bool $primary = true, ?array $limits = null): User
    {
        $user = $this->userWithRole('school_manager', $school);

        SchoolMembership::query()
            ->where('user_id', $user->id)
            ->where('school_id', $school->id)
            ->first()?->update([
                'is_primary_manager' => $primary,
                'manager_limits'     => $limits,
            ]);

        app(PermissionService::class)->clearUserCache($user);

        return $user;
    }

    private function allLimits(bool $online): array
    {
        return [
            'can_invite_students'       => true,
            'can_manage_schedule'       => true,
            'can_promote_class'         => false,
            'can_edit_school'           => false,
            'can_manage_deputies'       => false,
            'can_manage_online_classes' => $online,
        ];
    }

    private function enrollStudent(SchoolClass $class, User $student): void
    {
        ClassEnrollment::create([
            'id'              => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'user_id'         => $student->id,
            'status'          => 'active',
            'enrolled_at'     => now(),
        ]);
    }

    // ───────────── احراز هویت ─────────────

    public function test_management_routes_require_token(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);

        $this->getJson("/api/v1/classes/{$class->id}/online")->assertUnauthorized();
        $this->postJson("/api/v1/classes/{$class->id}/online", [])->assertUnauthorized();
        $this->getJson("/api/v1/classes/{$class->id}/online/join")->assertUnauthorized();
        $this->getJson('/api/v1/online-classes')->assertUnauthorized();
    }

    // ───────────── ثبت کلاس آنلاین ─────────────

    public function test_admin_registers_online_class(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb', 'title' => 'جلسه ریاضی'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'bbb')
            ->assertJsonPath('data.status', 'idle')
            ->assertJsonPath('data.title', 'جلسه ریاضی');
    }

    public function test_student_cannot_register(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('student', $school);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertForbidden();
    }

    public function test_teacher_not_assigned_cannot_register(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school); // بدون معلم
        $this->actAs('teacher', $school);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertForbidden();
    }

    public function test_teacher_assigned_can_register(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->userWithRole('teacher', $school);
        $class = $this->makeClass($school, ['teacher_user_id' => $teacher->id]);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertCreated();
    }

    public function test_deputy_without_permission_cannot_register(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $deputy = $this->makeManager($school, false, $this->allLimits(false));
        Sanctum::actingAs($deputy);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertForbidden();
    }

    public function test_deputy_with_permission_can_register(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $deputy = $this->makeManager($school, false, $this->allLimits(true));
        Sanctum::actingAs($deputy);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'skyroom'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'skyroom');
    }

    public function test_register_requires_configured_provider(): void
    {
        // BBB را غیرفعال می‌کنیم.
        SystemSetting::set('bbb', ['base_url' => '', 'secret' => '', 'checksum_algo' => 'sha1']);

        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_duplicate_register_is_422(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_class_id']);
    }

    // ───────────── شروع / ورود / پایان ─────────────

    public function test_start_creates_room_and_goes_live(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();

        $this->postJson("/api/v1/classes/{$class->id}/online/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'live');

        $this->assertDatabaseHas('online_classes', [
            'school_class_id' => $class->id,
            'status'          => 'live',
        ]);

        $this->assertTrue(collect(FakeMeetingProvider::$calls)->contains(
            fn (array $c) => $c[0] === 'createRoom'
        ));
    }

    public function test_join_as_teacher_yields_moderator_url(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->userWithRole('teacher', $school);
        $class = $this->makeClass($school, ['teacher_user_id' => $teacher->id]);
        Sanctum::actingAs($teacher);

        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();

        $this->getJson("/api/v1/classes/{$class->id}/online/join")
            ->assertOk()
            ->assertJsonPath('data.join_url', FakeMeetingProvider::$lastJoinUrl);

        $this->assertStringContainsString('role=moderator', (string) FakeMeetingProvider::$lastJoinUrl);
    }

    public function test_join_as_student_yields_viewer_url(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $student = $this->userWithRole('student', $school);
        $this->enrollStudent($class, $student);

        // مدیر کلاس آنلاین را ثبت و شروع می‌کند.
        $manager = $this->makeManager($school, true);
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$class->id}/online/start")->assertOk();

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/classes/{$class->id}/online/join")
            ->assertOk()
            ->assertJsonPath('data.join_url', FakeMeetingProvider::$lastJoinUrl);

        $this->assertStringContainsString('role=viewer', (string) FakeMeetingProvider::$lastJoinUrl);
    }

    public function test_student_join_when_not_live_is_422(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $student = $this->userWithRole('student', $school);
        $this->enrollStudent($class, $student);

        $manager = $this->makeManager($school, true);
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();
        // بدون start.

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/classes/{$class->id}/online/join")
            ->assertStatus(422);
    }

    public function test_non_enrolled_student_cannot_join(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $student = $this->userWithRole('student', $school); // بدون enrollment

        $manager = $this->makeManager($school, true);
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$class->id}/online/start")->assertOk();

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/classes/{$class->id}/online/join")
            ->assertForbidden();
    }

    public function test_end_sets_status_ended(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAs('admin', $school);
        $this->postJson("/api/v1/classes/{$class->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$class->id}/online/start")->assertOk();

        $this->postJson("/api/v1/classes/{$class->id}/online/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertTrue(collect(FakeMeetingProvider::$calls)->contains(
            fn (array $c) => $c[0] === 'endRoom'
        ));
    }

    // ───────────── فهرست ─────────────

    public function test_student_lists_only_enrolled_classes(): void
    {
        $school = $this->makeSchool();
        $mine = $this->makeClass($school);
        $other = $this->makeClass($school);
        $student = $this->userWithRole('student', $school);
        $this->enrollStudent($mine, $student);

        $manager = $this->makeManager($school, true);
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/classes/{$mine->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$other->id}/online", ['provider' => 'bbb'])->assertCreated();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/online-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_lists_only_managed_school_classes(): void
    {
        $mine = $this->makeSchool();
        $other = $this->makeSchool();
        $myClass = $this->makeClass($mine);
        $otherClass = $this->makeClass($other);

        $this->actAs('admin', $mine);
        $this->postJson("/api/v1/classes/{$myClass->id}/online", ['provider' => 'bbb'])->assertCreated();
        $this->postJson("/api/v1/classes/{$otherClass->id}/online", ['provider' => 'bbb'])->assertCreated();

        $manager = $this->makeManager($mine, true);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/online-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
