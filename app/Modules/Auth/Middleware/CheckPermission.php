<?php

namespace App\Modules\Auth\Middleware;

use App\Modules\Auth\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * استفاده:
     *   ->middleware('permission:user.invite')
     *   ->middleware('permission:user.invite,school_id_from_route')
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $schoolParam = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny($request, 401, 'احراز هویت انجام نشده است.', $permission);
        }

        $schoolId = null;
        if ($schoolParam) {
            $schoolId = $request->route($schoolParam) ?? $request->input($schoolParam);
        }

        if (! $this->permissionService->userHasPermission($user, $permission, $schoolId)) {
            return $this->deny(
                $request,
                403,
                'شما مجوز لازم برای این عملیات را ندارید.',
                $permission
            );
        }

        return $next($request);
    }

    protected function deny(Request $request, int $status, string $message, ?string $permission = null): Response
    {
        if ($this->wantsJson($request)) {
            $payload = [
                'success' => false,
                'message' => $message,
            ];

            if ($permission && $status === 403) {
                $payload['required_permission'] = $permission;
            }

            return response()->json($payload, $status);
        }

        abort($status, $message);
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*');
    }
}