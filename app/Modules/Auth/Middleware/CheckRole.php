<?php

namespace App\Modules\Auth\Middleware;

use App\Modules\Auth\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بررسی نقش کاربر.
 *
 * استفاده:
 *   ->middleware('role:admin')
 *   ->middleware('role:teacher,school_manager,admin')
 *
 * اگر کاربر حداقل یکی از نقش‌ها را داشته باشد اجازه عبور دارد.
 * ادمین پلتفرم (is_admin) همیشه عبور می‌کند.
 */
class CheckRole
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 401, 'احراز هویت انجام نشده است.');
        }

        // ادمین پلتفرم به همه بخش‌های نقش‌محور دسترسی دارد
        if ($this->permissionService->isPlatformAdmin($user)) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($this->permissionService->userHasRole($user, $role)) {
                return $next($request);
            }
        }

        return $this->deny($request, 403, 'شما نقش لازم برای دسترسی به این بخش را ندارید.');
    }

    protected function deny(Request $request, int $status, string $message): Response
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        abort($status, $message);
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*');
    }
}