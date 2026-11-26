<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        // لود migrationهای ماژول‌ها
        $this->loadMigrationsFrom(app_path('Modules/Auth/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/School/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Class/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/OnlineClass/Database/Migrations'));
        $this->loadMigrationsFrom(app_path('Modules/Attendance/Database/Migrations'));

        // API routes
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(function () {
                Route::group([], base_path('app/Modules/Auth/Routes/api.php'));
                Route::group([], base_path('app/Modules/School/Routes/api.php'));
                Route::group([], base_path('app/Modules/Class/Routes/api.php'));
                Route::group([], base_path('app/Modules/OnlineClass/Routes/api.php'));
            });

        // Web routes
        Route::middleware('web')
            ->group(function () {
                Route::group([], base_path('app/Modules/Auth/Routes/web.php'));
                Route::group([], base_path('app/Modules/School/Routes/web.php'));
                Route::group([], base_path('app/Modules/Class/Routes/web.php'));
                Route::group([], base_path('app/Modules/OnlineClass/Routes/web.php'));
                Route::group([], base_path('app/Modules/Attendance/Routes/web.php'));
            });
    }

    /**
     * تعریف Rate Limiterهای ماژول Auth
     */
    protected function configureRateLimiting(): void
    {
        // حداکثر ۵ درخواست login در دقیقه به ازای هر IP + شماره موبایل
        RateLimiter::for('auth-login', function (Request $request) {
            $mobile = (string) $request->input('mobile', '');
            $key = $request->ip() . '|' . $mobile;

            return Limit::perMinute(5)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید.',
                ], 429);
            });
        });

        // حداکثر ۲۰ درخواست login در دقیقه به ازای هر IP
        // (ضد SMS bombing با شماره‌های مختلف از یک IP)
        RateLimiter::for('auth-login-ip', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید.',
                ], 429);
            });
        });

        // حداکثر ۱۰ تلاش verify در دقیقه به ازای هر IP + شماره موبایل
        RateLimiter::for('auth-verify', function (Request $request) {
            $mobile = (string) $request->input('mobile', '');
            $key = $request->ip() . '|' . $mobile;

            return Limit::perMinute(10)->by($key)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید.',
                ], 429);
            });
        });

        // حداکثر ۶۰ درخواست API در دقیقه به ازای هر کاربر (یا IP)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است.',
                ], 429);
            });
        });
    }
}