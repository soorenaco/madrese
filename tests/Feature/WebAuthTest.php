<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected string $phone = '+989123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function makeUser(string $status = 'active'): User
    {
        return User::create([
            'mobile_e164'  => $this->phone,
            'display_name' => 'کاربر تست',
            'status'       => $status,
        ]);
    }

    public function test_guest_sees_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/login')
            ->assertRedirect('/dashboard');
    }

    public function test_request_otp_with_valid_mobile_moves_to_otp_step(): void
    {
        $this->post('/login', ['mobile' => '09123456789'])
            ->assertRedirect('/login')
            ->assertSessionHas('otp_step', 'otp')
            ->assertSessionHas('otp_mobile', $this->phone);

        $this->assertNotNull(Cache::get('otp:' . $this->phone));
    }

    public function test_request_otp_with_invalid_mobile_returns_validation_error(): void
    {
        $this->post('/login', ['mobile' => '12345'])
            ->assertSessionHasErrors('mobile');
    }

    public function test_blocked_user_cannot_request_otp(): void
    {
        $this->makeUser('blocked');

        $this->post('/login', ['mobile' => '09123456789'])
            ->assertSessionHasErrors('mobile');
    }

    public function test_verify_with_correct_code_logs_in_without_creating_api_token(): void
    {
        $this->post('/login', ['mobile' => '09123456789']);
        $code = Cache::get('otp:' . $this->phone)['code'];

        $this->post('/login/verify', ['mobile' => $this->phone, 'code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->get('/dashboard')->assertOk();
    }

    public function test_verify_with_wrong_code_returns_error_and_stays_guest(): void
    {
        $this->post('/login', ['mobile' => '09123456789']);

        $this->post('/login/verify', ['mobile' => $this->phone, 'code' => '00000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_dashboard_with_user_props(): void
    {
        $this->actingAs($this->makeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // از مرحله ۳ به بعد، اطلاعات کاربر در prop مشترک auth است
                ->where('auth.user.mobile', $this->phone)
                ->where('auth.user.display_name', 'کاربر تست')
                ->where('auth.user.status', 'active')
            );
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->makeUser())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_verify_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login/verify', ['mobile' => $this->phone, 'code' => '00000']);
        }

        $this->post('/login/verify', ['mobile' => $this->phone, 'code' => '00000'])
            ->assertStatus(429);
    }
}
