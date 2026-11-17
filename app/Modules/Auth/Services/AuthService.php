<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * درخواست ورود / ثبت‌نام (ارسال OTP)
     */
    public function requestLogin(string $mobile): array
    {
        // نرمال‌سازی شماره موبایل (فرض می‌کنیم فرمت E.164 می‌آید)
        $mobile = $this->normalizeMobile($mobile);

        // پیدا کردن یا ساخت کاربر
        $user = User::firstOrCreate(
            ['mobile_e164' => $mobile],
            [
                'id'           => Str::uuid(),
                'display_name' => 'کاربر ' . substr($mobile, -4),
                'status'       => 'invited',
            ]
        );

        if ($user->isBlocked()) {
            throw ValidationException::withMessages([
                'mobile' => ['حساب کاربری شما مسدود شده است.'],
            ]);
        }

        // تولید و ارسال OTP
        $otp = $this->otpService->generate($mobile);

        return [
            'message' => 'کد تأیید ارسال شد.',
            'mobile'  => $mobile,
            // فقط در محیط توسعه OTP را برمی‌گردانیم
            'otp'     => app()->environment('local') ? $otp : null,
        ];
    }

    /**
     * تأیید OTP و برگرداندن کاربر (بدون ساخت توکن).
     * برای ورود وب (session) و پایه‌ی verifyOtp استفاده می‌شود.
     */

                
    public function verifyCode(string $mobile, string $code): User
    {
        $mobile = $this->normalizeMobile($mobile);

        if (! $this->otpService->verify($mobile, $code)) {
            throw ValidationException::withMessages([
                'code' => ['کد وارد شده نامعتبر یا منقضی شده است.'],
            ]);
        }

        $user = User::where('mobile_e164', $mobile)->firstOrFail();

        if ($user->isBlocked()) {
            throw ValidationException::withMessages([
                'mobile' => ['حساب کاربری شما مسدود شده است.'],
            ]);
        }

        // ✅ اول: کاربر invited → active
        if ($user->status === 'invited') {
            $user->update(['status' => 'active']);
        }

        // ✅ دوم: همه‌ی عضویت‌های invited → active (بدون شرط user.status)
        $updated = \App\Modules\Auth\Models\SchoolMembership::where('user_id', $user->id)
            ->where('status', 'invited')
            ->update(['status' => 'active']);

        // ✅ سوم: اگه عضویتی فعال شد، cache رو پاک کن
        if ($updated > 0) {
            app(\App\Modules\Auth\Services\PermissionService::class)->clearUserCache($user);
        }

        $user->update(['last_login_at' => now()]);

        return $user;
    }
/**
     * تأیید OTP و ورود API (با ساخت توکن Sanctum)
     */
    public function verifyOtp(string $mobile, string $code): array
    {
        $user = $this->verifyCode($mobile, $code);

        // ساخت توکن Sanctum
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    /**
     * نرمال‌سازی شماره موبایل به فرمت E.164
     */
    protected function normalizeMobile(string $mobile): string
    {
        // حذف فاصله و کاراکترهای اضافی
        $mobile = preg_replace('/\s+/', '', $mobile);

        // اگر با 09 شروع شده بود به +98 تبدیل کن
        if (str_starts_with($mobile, '09')) {
            $mobile = '+98' . substr($mobile, 1);
        }

        // اگر با 9 شروع شده بود
        if (str_starts_with($mobile, '9') && strlen($mobile) === 10) {
            $mobile = '+98' . $mobile;
        }

        return $mobile;
    }
}