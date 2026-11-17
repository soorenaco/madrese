<?php

namespace App\Modules\Auth\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\School\Models\School;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\VerifyOtpRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ورود وب (Inertia) با OTP و session guard.
 * برخلاف API، هیچ توکن Sanctum ساخته نمی‌شود.
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /** GET /login */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /** POST /login — ارسال OTP */
    public function requestOtp(LoginRequest $request): RedirectResponse
    {
        $result = $this->authService->requestLogin($request->mobile);

        return redirect()->route('auth.login')
            ->with('otp_step', 'otp')
            ->with('otp_mobile', $result['mobile'])
            ->with('otp_dev', $result['otp']); // فقط در local مقدار دارد
    }

    /** POST /login/verify — تأیید OTP و ورود با session */
    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $user = $this->authService->verifyCode($request->mobile, $request->code);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('auth.dashboard'));
    }

    /** GET /dashboard */
    public function dashboard(Request $request, PermissionService $permissions): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => $this->dashboardStats($user, $permissions),
            'quickLinks' => $this->dashboardQuickLinks($user, $permissions),
        ]);
    }

    /**
     * کارت‌های آماری بر اساس نقش کاربر.
     *
     * @return list<array{key: string, label: string, value: int|string, hint?: string}>
     */
    protected function dashboardStats(User $user, PermissionService $permissions): array
    {
        $isAdmin = $permissions->isPlatformAdmin($user);
        $isManager = $isAdmin || $permissions->userHasRole($user, 'school_manager');
        $isTeacher = $isAdmin || $permissions->userHasRole($user, 'teacher');
        $isStudent = $permissions->userHasRole($user, 'student');

        $stats = [];

        if ($isAdmin || $isManager) {
            $managedIds = $permissions->managedSchoolIds($user); // null = همه (ادمین)

            $schoolsQuery = School::query();
            $classesQuery = SchoolClass::query();
            $usersQuery = User::query();

            if ($managedIds !== null) {
                $ids = $managedIds ?: ['00000000-0000-0000-0000-000000000000'];
                $schoolsQuery->whereIn('id', $ids);
                $classesQuery->whereIn('school_id', $ids);
                $usersQuery->whereHas('memberships', fn ($q) => $q->whereIn('school_id', $ids));
            }

            $stats[] = [
                'key'   => 'schools',
                'label' => 'مدارس',
                'value' => $schoolsQuery->count(),
                'hint'  => $isAdmin ? 'کل مدارس ثبت‌شده' : 'مدارس تحت مدیریت شما',
            ];
            $stats[] = [
                'key'   => 'classes',
                'label' => 'کلاس‌ها',
                'value' => $classesQuery->count(),
                'hint'  => $isAdmin ? 'کل کلاس‌ها' : 'کلاس‌های مدارس شما',
            ];
            $stats[] = [
                'key'   => 'users',
                'label' => 'کاربران',
                'value' => $usersQuery->count(),
                'hint'  => $isAdmin ? 'کل حساب‌های سامانه' : 'اعضای مدارس شما',
            ];
        } elseif ($isTeacher) {
            $stats[] = [
                'key'   => 'my_classes',
                'label' => 'کلاس‌های من',
                'value' => SchoolClass::query()
                    ->where('teacher_user_id', $user->id)
                    ->where('status', 'active')
                    ->count(),
                'hint'  => 'کلاس‌های فعال با معلم شما',
            ];
            $stats[] = [
                'key'   => 'all_classes',
                'label' => 'همه کلاس‌ها',
                'value' => SchoolClass::query()->where('status', 'active')->count(),
                'hint'  => 'کلاس‌های فعال سامانه',
            ];
        } elseif ($isStudent) {
            $activeMemberships = SchoolMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->count();

            $stats[] = [
                'key'   => 'memberships',
                'label' => 'عضویت فعال',
                'value' => $activeMemberships,
                'hint'  => 'مدرسه‌هایی که در آن عضو هستید',
            ];
            $stats[] = [
                'key'   => 'student_hint',
                'label' => 'وضعیت',
                'value' => $activeMemberships > 0 ? 'فعال' : 'بدون مدرسه',
                'hint'  => 'پنل دانش‌آموز در مرحله بعد کامل می‌شود',
            ];
        } else {
            $stats[] = [
                'key'   => 'no_role',
                'label' => 'نقش',
                'value' => 'تعیین‌نشده',
                'hint'  => 'هنوز نقشی به حساب شما اختصاص داده نشده است',
            ];
        }

        return $stats;
    }

    /**
     * لینک‌های سریع مجاز برای کاربر.
     *
     * @return list<array{label: string, href: string, description: string}>
     */
    protected function dashboardQuickLinks(User $user, PermissionService $permissions): array
    {
        $isAdmin = $permissions->isPlatformAdmin($user);
        $links = [
            [
                'label'       => 'داشبورد',
                'href'        => '/dashboard',
                'description' => 'نمای کلی حساب',
            ],
        ];

        if ($isAdmin || $permissions->userHasRole($user, 'teacher') || $permissions->userHasRole($user, 'school_manager')) {
            $links[] = [
                'label'       => 'کلاس‌ها',
                'href'        => '/classes',
                'description' => 'مشاهده و مدیریت کلاس‌ها',
            ];
        }

        if ($isAdmin || $permissions->userHasRole($user, 'school_manager')) {
            $links[] = [
                'label'       => 'مدارس',
                'href'        => '/school',
                'description' => 'مدیریت مدارس',
            ];
            $links[] = [
                'label'       => 'کاربران',
                'href'        => '/users',
                'description' => 'وضعیت و عضویت کاربران',
            ];
        }

        if ($isAdmin) {
            $links[] = [
                'label'       => 'تنظیمات',
                'href'        => '/settings',
                'description' => 'تنظیمات سیستم',
            ];
        }

        if ($permissions->userHasRole($user, 'student') && ! $isAdmin) {
            $links[] = [
                'label'       => 'کلاس‌های من',
                'href'        => '/dashboard',
                'description' => 'به‌زودی — پنل دانش‌آموز',
            ];
        }

        return $links;
    }

    /** POST /logout */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
