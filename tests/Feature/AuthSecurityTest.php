<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\MembershipRole;
use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Models\School;
 use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function authUrl(string $path): string
    {
        return '/api/v1/auth/' . ltrim($path, '/');
    }

    public function test_otp_cooldown_prevents_rapid_requests(): void
    {
        $mobile = '09121111111';

        $first = $this->postJson($this->authUrl('login'), ['mobile' => $mobile]);
        $first->assertStatus(200);

        $second = $this->postJson($this->authUrl('login'), ['mobile' => $mobile]);
        $second->assertStatus(422);
        $second->assertJsonValidationErrors(['mobile']);
    }

    public function test_login_rate_limit_returns_429_after_too_many_requests(): void
    {
        $mobile = '09122222222';

        $clearCooldown = function () use ($mobile) {
            Cache::forget('otp_cooldown:' . $mobile);
            Cache::forget('otp_cooldown:' . $mobile . ':expires_at');
        };

        for ($i = 0; $i < 5; $i++) {
            $clearCooldown();
            $this->postJson($this->authUrl('login'), ['mobile' => $mobile]);
        }

        $clearCooldown();
        $blocked = $this->postJson($this->authUrl('login'), ['mobile' => $mobile]);
        $blocked->assertStatus(429);
    }

    public function test_verify_rate_limit_returns_429_after_too_many_attempts(): void
    {
        $mobile = '09123333333';

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson($this->authUrl('verify'), [
                'mobile' => $mobile,
                'code'   => '00000',
            ]);

            $this->assertNotEquals(
                429,
                $response->getStatusCode(),
                'تلاش شماره ' . ($i + 1) . ' نباید 429 بگیرد'
            );
        }

        $this->postJson($this->authUrl('verify'), [
            'mobile' => $mobile,
            'code'   => '00000',
        ])->assertStatus(429);
    }

    public function test_permission_service_returns_false_for_user_without_roles(): void
    {
        $user = User::create([
            'id'           => (string) Str::uuid(),
            'mobile_e164'  => '+989123333333',
            'display_name' => 'بدون نقش',
            'status'       => 'active',
        ]);

        $service = app(PermissionService::class);

        $this->assertFalse($service->userHasPermission($user, 'user.invite'));
        $this->assertFalse($service->isPlatformAdmin($user));
        $this->assertSame([], $service->getUserRoleCodes($user));
        $this->assertSame([], $service->getUserPermissionCodes($user));
    }

    public function test_permission_service_can_be_resolved_from_container(): void
    {
        $service = app(PermissionService::class);
        $this->assertInstanceOf(PermissionService::class, $service);
    }

    public function test_ip_rate_limit_blocks_after_20_requests_with_different_mobiles(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $mobile = '09120000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $response = $this->postJson($this->authUrl('login'), [
                'mobile' => $mobile,
            ]);

            $this->assertNotEquals(
                429,
                $response->getStatusCode(),
                "درخواست شماره {$i} نباید 429 بگیرد"
            );
        }

        $blocked = $this->postJson($this->authUrl('login'), [
            'mobile' => '09120000999',
        ]);

        $blocked->assertStatus(429);
    }

    public function test_deny_overrides_allow(): void
    {
        $this->seed(\App\Modules\Auth\Database\Seeders\RoleSeeder::class);
        $this->seed(\App\Modules\Auth\Database\Seeders\PermissionSeeder::class);

        $user = User::create([
            'mobile_e164'  => '+989120000001',
            'display_name' => 'تست',
            'status'       => 'active',
        ]);

        $roleA = Role::where('code', 'teacher')->firstOrFail();
        $roleB = Role::where('code', 'student')->firstOrFail();

        $school = School::create([
            'name'   => 'مدرسه تست',
            'status' => 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $user->id,
            'status'    => 'active',
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $roleA->id,
        ]);
        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $roleB->id,
        ]);

        $perm = Permission::where('code', 'class.view')->firstOrFail();
        $roleA->permissions()->syncWithoutDetaching([
            $perm->id => ['effect' => 'allow'],
        ]);
        $roleB->permissions()->syncWithoutDetaching([
            $perm->id => ['effect' => 'deny'],
        ]);

        app(PermissionService::class)->clearUserCache($user);

        $this->assertFalse(app(PermissionService::class)->userHasPermission($user, 'class.view'));
    }

    public function test_admin_always_has_all_permissions_regardless_of_school(): void
    {
        $this->seed(\App\Modules\Auth\Database\Seeders\RoleSeeder::class);
        $this->seed(\App\Modules\Auth\Database\Seeders\PermissionSeeder::class);

        $admin = User::create([
            'mobile_e164'  => '+989120000002',
            'display_name' => 'ادمین',
            'status'       => 'active',
        ]);

        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $school = School::create([
            'name'   => 'مدرسه تست ۲',
            'status' => 'active',
        ]);

        $membership = SchoolMembership::create([
            'id'        => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id'   => $admin->id,
            'status'    => 'active',
        ]);

        MembershipRole::create([
            'membership_id' => $membership->id,
            'role_id'       => $adminRole->id,
        ]);

        app(PermissionService::class)->clearUserCache($admin);

        $allPerms = \App\Modules\Auth\Models\Permission::count();
        $this->assertCount($allPerms, app(PermissionService::class)->getUserPermissionCodes($admin, 'some-school'));
    }

    public function test_blocked_user_cannot_verify_otp(): void
    {
        $phone = '+989123456789';
        $this->postJson($this->authUrl('login'), ['mobile' => $phone]);
        $code = Cache::get('otp:' . $phone)['code'];

        User::where('mobile_e164', $phone)->update(['status' => 'blocked']);

        $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => $code,
        ])->assertStatus(422);
    }

    public function test_otp_ttl_is_not_extended_by_failed_attempts(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), ['mobile' => $phone]);
        $cached = Cache::get('otp:' . $phone);
        $this->assertNotNull($cached);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson($this->authUrl('verify'), [
                'mobile' => $phone,
                'code'   => '00000',
            ]);
        }

        $cachedAfter = Cache::get('otp:' . $phone);
        $this->assertNotNull($cachedAfter);
        $this->assertSame($cached['created_at'], $cachedAfter['created_at']);
    }

    public function test_otp_expires_after_ttl_even_with_attempts(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), ['mobile' => $phone]);

        $this->travel(30)->seconds();
        $this->postJson($this->authUrl('verify'), ['mobile' => $phone, 'code' => '00000']);

        $this->travel(91)->seconds();

        $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => '00000',
        ])->assertStatus(422);
    }
}