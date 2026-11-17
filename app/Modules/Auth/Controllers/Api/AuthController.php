<?php

namespace App\Modules\Auth\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\VerifyOtpRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * درخواست ورود / ثبت‌نام (ارسال OTP)
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->requestLogin($request->mobile);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'mobile' => $result['mobile'],
                // فقط در محیط local OTP برگردانده می‌شود
                'otp'    => $result['otp'] ?? null,
            ],
        ]);
    }

    /**
     * تأیید OTP و دریافت توکن
     * POST /api/auth/verify
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyOtp(
            $request->mobile,
            $request->code
        );

        return response()->json([
            'success' => true,
            'message' => 'ورود با موفقیت انجام شد.',
            'data'    => [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * اطلاعات کاربر فعلی
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }

    /**
     * خروج از حساب (حذف توکن فعلی)
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // حذف فقط توکن فعلی
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'با موفقیت خارج شدید.',
        ]);
    }

    /**
     * خروج از همه دستگاه‌ها
     * POST /api/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'از همه دستگاه‌ها خارج شدید.',
        ]);
    }
}