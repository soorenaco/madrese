<?php

namespace App\Http\Middleware;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Blade ریشه Inertia (resources/views/app.blade.php)
     */
    protected $rootView = 'app';

    /**
     * نسخه asset ها؛ با تغییر آن، مرورگر صفحه را کامل رفرش می‌کند.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * props مشترک بین همه صفحه‌ها.
     *
     * auth:
     *   - null برای مهمان
     *   - برای کاربر واردشده: user, roles[{code,title}], permissions[], is_admin
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => function () use ($request) {
                $user = $request->user();

                return $user instanceof User
                    ? $this->buildAuthProp($user)
                    : null;
            },

            // فلش‌های موردنیاز صفحه لاگین OTP
            'flash' => [
                'otp_step'   => fn () => $request->session()->get('otp_step'),
                'otp_mobile' => fn () => $request->session()->get('otp_mobile'),
                'otp_dev'    => fn () => $request->session()->get('otp_dev'),
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * ساخت prop احراز هویت برای کاربر جاری.
     */
    protected function buildAuthProp(User $user): array
    {
        /** @var PermissionService $permissions */
        $permissions = app(PermissionService::class);

        $roleCodes = $permissions->getUserRoleCodes($user);
        $titles    = $permissions->getRoleTitles();

        $roles = collect($roleCodes)
            ->map(fn (string $code) => [
                'code'  => $code,
                'title' => $titles[$code] ?? $code,
            ])
            ->values()
            ->all();

        return [
            'user' => [
                'id'           => $user->id,
                'mobile'       => $user->mobile_e164,
                'display_name' => $user->display_name,
                'status'       => $user->status,
            ],
            'roles'       => $roles,
            'permissions' => $permissions->getUserPermissionCodes($user),
            'is_admin'    => $permissions->isPlatformAdmin($user),
        ];
    }
}