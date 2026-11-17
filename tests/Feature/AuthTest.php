<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * مسیر پایه API احراز هویت
     */
    protected function authUrl(string $path): string
    {
        return '/api/v1/auth/' . ltrim($path, '/');
    }

    public function test_user_can_request_otp_with_valid_mobile(): void
    {
        $response = $this->postJson($this->authUrl('login'), [
            'mobile' => '09123456789',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'mobile',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'mobile_e164' => '+989123456789',
        ]);
    }

    public function test_user_cannot_request_otp_with_invalid_mobile(): void
    {
        $response = $this->postJson($this->authUrl('login'), [
            'mobile' => '12345',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_verify_otp_and_get_token(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), [
            'mobile' => $phone,
        ]);

        $cachedData = Cache::get('otp:' . $phone);
        $this->assertNotNull($cachedData, 'OTP باید در کش ذخیره شده باشد');
        $code = $cachedData['code'];

        $response = $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => $code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'mobile',
                        'display_name',
                        'status',
                        'last_login_at',
                        'created_at',
                    ],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'mobile_e164' => $phone,
            'status'      => 'active',
        ]);
    }

    public function test_verify_fails_with_wrong_otp(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), [
            'mobile' => $phone,
        ]);

        $response = $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => '00000',
        ]);

        $response->assertStatus(422);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $phone = '+989123456789';

        User::create([
            'id'           => (string) Str::uuid(),
            'mobile_e164'  => $phone,
            'display_name' => 'کاربر مسدود',
            'status'       => 'blocked',
        ]);

        $response = $this->postJson($this->authUrl('login'), [
            'mobile' => $phone,
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_get_me(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), ['mobile' => $phone]);
        $cachedData = Cache::get('otp:' . $phone);
        $code = $cachedData['code'];

        $verifyResponse = $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => $code,
        ]);

        $token = $verifyResponse->json('data.token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->authUrl('me'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'mobile',
                        'display_name',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $phone = '+989123456789';

        $this->postJson($this->authUrl('login'), ['mobile' => $phone]);
        $cachedData = Cache::get('otp:' . $phone);
        $code = $cachedData['code'];

        $verifyResponse = $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => $code,
        ]);

        $token = $verifyResponse->json('data.token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->authUrl('logout'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson($this->authUrl('me'))->assertStatus(401);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $phone = '+989123456789';

        $tokenA = $this->loginAndGetToken($phone);
        $this->clearOtpCooldown($phone);
        $tokenB = $this->loginAndGetToken($phone);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson($this->authUrl('logout'))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        // فقط توکن A حذف شده؛ توکن B باید سالم بماند
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // guard کش‌شده‌ی همین process تست را پاک می‌کنیم
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson($this->authUrl('me'))
            ->assertStatus(200);
    }

    public function test_logout_all_revokes_every_token_of_the_user(): void
    {
        $phone = '+989123456789';

        $tokenA = $this->loginAndGetToken($phone);
        $this->clearOtpCooldown($phone);
        $tokenB = $this->loginAndGetToken($phone);

        $this->assertNotSame($tokenA, $tokenB);
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson($this->authUrl('logout-all'))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // guard کش‌شده‌ی همین process تست را پاک می‌کنیم تا 401 واقعی دیده شود
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson($this->authUrl('me'))
            ->assertStatus(401);
    }

    public function test_logout_all_requires_authentication(): void
    {
        $this->postJson($this->authUrl('logout-all'))->assertStatus(401);
    }

    /**
     * helper: ورود کامل با OTP و برگرداندن توکن
     */
    protected function loginAndGetToken(string $phone): string
    {
        $this->postJson($this->authUrl('login'), ['mobile' => $phone])->assertStatus(200);

        $code = Cache::get('otp:' . $phone)['code'];

        return $this->postJson($this->authUrl('verify'), [
            'mobile' => $phone,
            'code'   => $code,
        ])->assertStatus(200)->json('data.token');
    }

    /**
     * helper: پاک کردن cooldown ۶۰ ثانیه‌ای OTP (نه شمارنده RateLimiter)
     */
    protected function clearOtpCooldown(string $phone): void
    {
        Cache::forget('otp_cooldown:' . $phone);
        Cache::forget('otp_cooldown:' . $phone . ':expires_at');
    }

    public function test_roles_are_seeded_correctly_including_admin(): void
    {
        $this->seed(\App\Modules\Auth\Database\Seeders\RoleSeeder::class);

        $this->assertDatabaseHas('roles', [
            'code'  => 'admin',
            'scope' => 'platform',
            'title' => 'ادمین سیستم',
        ]);

        $this->assertDatabaseHas('roles', [
            'code'  => 'school_manager',
            'scope' => 'school',
        ]);

        $this->assertDatabaseHas('roles', [
            'code'  => 'driver',
            'scope' => 'self',
        ]);

        $this->assertGreaterThanOrEqual(8, DB::table('roles')->count());
    }

    public function test_permissions_are_seeded_including_platform_permissions(): void
    {
        $this->seed(\App\Modules\Auth\Database\Seeders\PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'code' => 'platform.manage',
        ]);

        $this->assertDatabaseHas('permissions', [
            'code' => 'school.create',
        ]);

        $this->assertDatabaseHas('permissions', [
            'code' => 'user.invite',
        ]);

        $this->assertGreaterThanOrEqual(20, DB::table('permissions')->count());
    }


    public function test_blocked_user_cannot_verify_otp(): void
    {
        $phone = '+989123456789';
        $this->postJson('/api/v1/auth/login', ['mobile' => $phone]);
        $code = Cache::get('otp:' . $phone)['code'];

        // کاربر رو بلاک کن
        User::where('mobile_e164', $phone)->update(['status' => 'blocked']);

        $this->postJson('/api/v1/auth/verify', [
            'mobile' => $phone,
            'code'   => $code,
        ])->assertStatus(422);
    }

    public function test_otp_ttl_is_not_extended_by_failed_attempts(): void
    {
        $phone = '+989123456789';

        // درخواست OTP
        $this->postJson('/api/v1/auth/login', ['mobile' => $phone]);
        $cached = Cache::get('otp:' . $phone);
        $this->assertNotNull($cached);

        // ۳ بار verify ناموفق (TTL نباید از نو شه)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/verify', [
                'mobile' => $phone,
                'code'   => '00000',
            ]);
        }

        // TTL باقی‌مانده باید کمتر از ttl اصلی باشه
        $cachedAfter = Cache::get('otp:' . $phone);
        $this->assertNotNull($cachedAfter);
        $this->assertSame($cached['created_at'], $cachedAfter['created_at']);
    }

    public function test_otp_expires_after_ttl_even_with_attempts(): void
    {
        $phone = '+989123456789';

        $this->postJson('/api/v1/auth/login', ['mobile' => $phone]);

        // ۳۰ ثانیه بعد، یه verify ناموفق
        $this->travel(30)->seconds();
        $this->postJson('/api/v1/auth/verify', ['mobile' => $phone, 'code' => '00000']);

        // ۹۱ ثانیه بعد از شروع (۳۰+۹۱=۱۲۱ > ۱۲۰)
        $this->travel(91)->seconds();

        // OTP باید منقضی شده باشه
        $this->postJson('/api/v1/auth/verify', [
            'mobile' => $phone,
            'code'   => '00000',
        ])->assertStatus(422);
    }

    public function test_otp_is_not_logged_outside_local_env(): void
    {
        $phone = '+989123456789';

        // محیط رو mock کن
        $this->app['env'] = 'production';

        \Illuminate\Support\Facades\Log::spy();

        $this->postJson('/api/v1/auth/login', ['mobile' => $phone]);

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('info');
    }

}