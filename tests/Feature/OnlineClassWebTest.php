<?php

namespace Tests\Feature;

use App\Modules\Auth\Database\Seeders\RoleSeeder;
use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\OnlineClass\Models\SystemSetting;
use App\Modules\OnlineClass\Services\Providers\BbbProvider;
use App\Modules\OnlineClass\Services\Providers\SkyroomProvider;
use App\Modules\School\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Fakes\FakeMeetingProvider;
use Tests\TestCase;

class OnlineClassWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->withoutVite();

        SystemSetting::set('bbb', ['base_url' => 'https://bbb.test', 'secret' => 'test', 'checksum_algo' => 'sha1']);
        SystemSetting::set('skyroom', ['base_url' => 'https://skyroom.test', 'api_key' => 'key']);

        $this->app->bind(BbbProvider::class, fn () => new FakeMeetingProvider());
        $this->app->bind(SkyroomProvider::class, fn () => new FakeMeetingProvider());

        FakeMeetingProvider::reset();
    }

    private function makeSchool(): School
    {
        return School::create([
            'name'   => 'مدرسه ' . Str::random(6),
            'code'   => 'S' . Str::random(8),
            'city'   => 'کرمان',
            'status' => 'active',
        ]);
    }

    private function makeClass(School $school): SchoolClass
    {
        return SchoolClass::create([
            'school_id'     => $school->id,
            'name'          => 'کلاس ' . Str::random(6),
            'grade'         => 'دهم',
            'academic_year' => '1405-1406',
            'status'        => 'active',
        ]);
    }

    private function actAsAdmin(School $school): User
    {
        $user = User::create([
            'mobile_e164'  => '+98912' . random_int(1000000, 9999999),
            'display_name' => 'ادمین',
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
            'role_id'       => Role::where('code', 'admin')->firstOrFail()->id,
        ]));

        $this->actingAs($user);

        return $user;
    }

    public function test_settings_page_loads_with_provider_config(): void
    {
        $school = $this->makeSchool();
        $this->actAsAdmin($school);

        $this->get('/settings')->assertOk();
    }

    public function test_admin_can_save_provider_config_and_keeps_blank_secret(): void
    {
        $school = $this->makeSchool();
        $this->actAsAdmin($school);

        $this->put('/settings/providers', [
            'bbb'      => ['base_url' => 'https://bbb.new', 'secret' => 'new-secret', 'checksum_algo' => 'sha256'],
            'skyroom'  => ['base_url' => 'https://skyroom.online', 'api_key' => ''],
            'default_provider' => 'bbb',
        ])->assertRedirect();

        $bbb = SystemSetting::get('bbb');
        $this->assertSame('https://bbb.new', $bbb['base_url']);
        $this->assertSame('new-secret', $bbb['secret']);
        $this->assertSame('sha256', $bbb['checksum_algo']);

        // api_key خالی ارسال شد → مقدار قبلی حفظ می‌شود.
        $this->assertSame('key', SystemSetting::get('skyroom')['api_key']);
        $this->assertSame('bbb', SystemSetting::get('online_class.default_provider'));
    }

    public function test_online_classes_index_loads(): void
    {
        $school = $this->makeSchool();
        $this->actAsAdmin($school);

        $this->get('/online-classes')->assertOk();
    }

    public function test_join_redirects_to_provider_url(): void
    {
        $school = $this->makeSchool();
        $class = $this->makeClass($school);
        $this->actAsAdmin($school);

        $this->post("/classes/{$class->id}/online", ['provider' => 'bbb'])->assertRedirect();
        $this->post("/classes/{$class->id}/online/start")->assertRedirect();

        $response = $this->get("/classes/{$class->id}/online/join");
        $response->assertRedirect();
        $this->assertStringStartsWith('https://fake.example', (string) $response->headers->get('Location'));
    }

    public function test_only_admin_can_access_settings(): void
    {
        $school = $this->makeSchool();
        $user = User::create([
            'mobile_e164'  => '+989127777777',
            'display_name' => 'معلم',
            'status'       => 'active',
        ]);
        $membership = SchoolMembership::unguarded(fn () => SchoolMembership::create([
            'id' => (string) Str::uuid(), 'school_id' => $school->id, 'user_id' => $user->id, 'status' => 'active',
        ]));
        MembershipRole::unguarded(fn () => MembershipRole::create([
            'membership_id' => $membership->id, 'role_id' => Role::where('code', 'teacher')->firstOrFail()->id,
        ]));
        $this->actingAs($user);

        $this->get('/settings')->assertForbidden();
    }
}
