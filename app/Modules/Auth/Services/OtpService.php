<?php

namespace App\Modules\Auth\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * مدت اعتبار OTP (ثانیه)
     */
    protected int $ttl = 120; // 2 دقیقه

    /**
     * حداکثر تلاش مجاز برای وارد کردن کد
     */
    protected int $maxAttempts = 5;

    /**
     * حداقل فاصله بین دو درخواست OTP برای یک شماره (ثانیه)
     */
    protected int $cooldownSeconds = 60;

    /**
     * تولید و ذخیره OTP برای یک موبایل
     *
     * @throws ValidationException اگر هنوز در دوره cooldown باشد
     */
    public function generate(string $mobile): string
    {
        $cooldownKey = $this->getCooldownKey($mobile);

        if (Cache::has($cooldownKey)) {
            $remaining = Cache::get($cooldownKey . ':expires_at') - time();
            $remaining = max(1, $remaining);

            throw ValidationException::withMessages([
                'mobile' => ["لطفاً {$remaining} ثانیه دیگر دوباره تلاش کنید."],
            ]);
        }

        $otp = $this->generateCode();

        $key = $this->getCacheKey($mobile);

        Cache::put($key, [
            'code'       => $otp,
            'attempts'   => 0,
            'created_at' => now()->timestamp,
        ], $this->ttl);

        // ثبت cooldown
        Cache::put($cooldownKey, true, $this->cooldownSeconds);
        Cache::put($cooldownKey . ':expires_at', time() + $this->cooldownSeconds, $this->cooldownSeconds);

        // در محیط واقعی اینجا باید SMS ارسال شود
        // OTP فقط در محیط local لاگ می‌شود (برای debug)
        if (app()->environment('local'))
        {
        logger()->info("OTP for {$mobile}: {$otp}");
        }
        
        return $otp;
    }

    /**
     * اعتبارسنجی OTP
     */
    public function verify(string $mobile, string $code): bool
    {
        $key = $this->getCacheKey($mobile);
        $data = Cache::get($key);

        if (!$data) {
            return false; // منقضی شده یا وجود ندارد
        }

        // چک TTL واقعی (بر اساس created_at، نه Cache::put)
        $age = now()->timestamp - $data['created_at'];
        if ($age > $this->ttl) {
            Cache::forget($key);
            return false;
        }

        // افزایش تعداد تلاش
        $data['attempts']++;
        $remainingTtl = $this->ttl - $age; // TTL باقی‌مانده
        Cache::put($key, $data, max(1, $remainingTtl));

        if ($data['attempts'] > $this->maxAttempts) {
            Cache::forget($key);
            return false;
        }

        if (!hash_equals($data['code'], $code)) {
            return false;
        }

        // OTP صحیح بود → پاک کردن
        Cache::forget($key);

        return true;
    }
    /**
     * آیا OTP برای این موبایل هنوز معتبر است؟
     */
    public function hasValidOtp(string $mobile): bool
    {
        return Cache::has($this->getCacheKey($mobile));
    }

    /**
     * تولید کد عددی
     */
    protected function generateCode(int $length = 5): string
    {
        return (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);
    }

    protected function getCacheKey(string $mobile): string
    {
        return 'otp:' . $mobile;
    }

    protected function getCooldownKey(string $mobile): string
    {
        return 'otp_cooldown:' . $mobile;
    }
}
