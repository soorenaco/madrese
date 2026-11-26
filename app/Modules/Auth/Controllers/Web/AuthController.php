<?php

namespace App\Modules\Auth\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\School\Models\School;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Class\Models\ClassEnrollment;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Auth\Models\SchoolMembership;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\ParentStudentLink;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\VerifyOtpRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'dashboard' => [
                'stats'           => $this->dashboardStats($user, $permissions),
                'enrollmentTrend' => $this->enrollmentTrend($user, $permissions),
                'trendLabels'     => $this->trendLabels(),
                'recentActivity'  => $this->recentActivity($user, $permissions),
                'onlineUpcoming'  => $this->onlineUpcoming($user, $permissions),
                'role'            => $this->primaryRole($user, $permissions),
            ],
            'quickLinks' => $this->dashboardQuickLinks($user, $permissions),
        ]);
    }

    /**
     * نقش اصلی کاربر (برای نمایش عنوان و انتخاب قالب داشبورد).
     */
    protected function primaryRole(User $user, PermissionService $permissions): string
    {
        if ($permissions->isPlatformAdmin($user)) {
            return 'admin';
        }
        foreach (['school_manager', 'teacher', 'parent', 'student'] as $role) {
            if ($permissions->userHasRole($user, $role)) {
                return $role;
            }
        }
        return 'guest';
    }

    /**
     * کارت‌های آماری بر اساس نقش کاربر — همگی از دیتابیس واقعی.
     *
     * @return list<array{key: string, label: string, value: int|string, hint?: string, to?: string, icon?: string, variant?: string}>
     */
    protected function dashboardStats(User $user, PermissionService $permissions): array
    {
        $isAdmin   = $permissions->isPlatformAdmin($user);
        $isManager = $isAdmin || $permissions->userHasRole($user, 'school_manager');
        $isTeacher = $permissions->userHasRole($user, 'teacher');
        $isParent  = $permissions->userHasRole($user, 'parent');
        $isStudent = $permissions->userHasRole($user, 'student');

        $stats = [];

        // ─── ادمین / مدیر مدرسه: مدارس، کلاس‌ها، کاربران، آنلاین ───
        if ($isAdmin || $isManager) {
            $managedIds = $permissions->managedSchoolIds($user); // null = همه (ادمین)
            $ids = $managedIds === null ? null : ($managedIds ?: ['00000000-0000-0000-0000-000000000000']);

            $schoolsQuery = School::query();
            $classesQuery = SchoolClass::query();
            $usersQuery   = User::query();
            $onlineQuery  = OnlineClass::query();

            if ($ids !== null) {
                $schoolsQuery->whereIn('id', $ids);
                $classesQuery->whereIn('school_id', $ids);
                $usersQuery->whereHas('memberships', fn ($q) => $q->whereIn('school_id', $ids));
                $onlineQuery->whereHas('schoolClass', fn ($q) => $q->whereIn('school_id', $ids));
            }

            $stats[] = [
                'key'     => 'schools',
                'label'   => 'مدارس',
                'value'   => $schoolsQuery->count(),
                'hint'    => $isAdmin ? 'کل مدارس ثبت‌شده' : 'مدارس تحت مدیریت شما',
                'to'      => '/school',
                'icon'    => 'building',
                'variant' => 'primary',
            ];
            $stats[] = [
                'key'     => 'classes',
                'label'   => 'کلاس‌ها',
                'value'   => $classesQuery->count(),
                'hint'    => $isAdmin ? 'کل کلاس‌ها' : 'کلاس‌های مدارس شما',
                'to'      => '/classes',
                'icon'    => 'layers',
                'variant' => 'info',
            ];
            $stats[] = [
                'key'     => 'users',
                'label'   => 'کاربران',
                'value'   => $usersQuery->count(),
                'hint'    => $isAdmin ? 'کل حساب‌های سامانه' : 'اعضای مدارس شما',
                'to'      => '/users',
                'icon'    => 'users',
                'variant' => 'success',
            ];
            $stats[] = [
                'key'     => 'online',
                'label'   => 'کلاس‌های آنلاین',
                'value'   => $onlineQuery->count(),
                'hint'    => 'کل جلسات آنلاین ثبت‌شده',
                'to'      => '/online-classes',
                'icon'    => 'video',
                'variant' => 'warning',
            ];
        }
        // ─── معلم: کلاس‌های من، جلسات امروز، غیبت‌های امروز ───
        elseif ($isTeacher) {
            $myClasses = SchoolClass::query()
                ->where('teacher_user_id', $user->id)
                ->where('status', 'active')
                ->pluck('id');

            $today = now()->format('Y-m-d');
            $todaySessions = Attendance::query()
                ->whereIn('school_class_id', $myClasses)
                ->where('session_date', $today)
                ->count();
            $todayAbsences = Attendance::query()
                ->whereIn('school_class_id', $myClasses)
                ->where('session_date', $today)
                ->where('status', 'absent')
                ->count();

            $stats[] = [
                'key'     => 'my_classes',
                'label'   => 'کلاس‌های من',
                'value'   => $myClasses->count(),
                'hint'    => 'کلاس‌های فعال با معلمی شما',
                'to'      => '/classes',
                'icon'    => 'class',
                'variant' => 'primary',
            ];
            $stats[] = [
                'key'     => 'today_sessions',
                'label'   => 'جلسات امروز',
                'value'   => $todaySessions,
                'hint'    => 'ثبت‌شده برای امروز',
                'to'      => '/classes',
                'icon'    => 'check-circle',
                'variant' => 'info',
            ];
            $stats[] = [
                'key'     => 'today_absences',
                'label'   => 'غیبت‌های امروز',
                'value'   => $todayAbsences,
                'hint'    => 'در کلاس‌های شما',
                'to'      => '/classes',
                'icon'    => 'x-circle',
                'variant' => 'danger',
            ];
            $stats[] = [
                'key'     => 'online',
                'label'   => 'کلاس‌های آنلاین',
                'value'   => OnlineClass::query()
                    ->whereHas('schoolClass', fn ($q) => $q->whereIn('id', $myClasses))
                    ->count(),
                'hint'    => 'جلسات آنلاینِ کلاس‌های شما',
                'to'      => '/online-classes',
                'icon'    => 'video',
                'variant' => 'warning',
            ];
        }
        // ─── والد: تعداد فرزندان، غیبت‌های این هفته، اعلان‌های خوانده‌نشده ───
        elseif ($isParent) {
            $childIds = ParentStudentLink::activeStudentIdsFor($user->id);

            // غیبت‌های این هفته برای همه‌ی فرزندان
            $weekStart = now()->startOfWeek(); // شنبه (با تنظیم اول هفته)
            // در تقویم ایران شنبه اول هفته است؛ اما Laravel پیش‌فرض دوشنبه.
            // برای سادگی از ۷ روز گذشته استفاده می‌کنیم.
            $weekAbsences = Attendance::query()
                ->whereIn('user_id', $childIds)
                ->where('status', 'absent')
                ->where('session_date', '>=', now()->subDays(7)->format('Y-m-d'))
                ->count();

            // کلاس‌های فعالِ فرزندان
            $childClassCount = ClassEnrollment::query()
                ->whereIn('user_id', $childIds)
                ->where('status', 'active')
                ->distinct('school_class_id')
                ->count('school_class_id');

            $stats[] = [
                'key'     => 'children',
                'label'   => 'فرزندان متصل',
                'value'   => count($childIds),
                'hint'    => 'دانش‌آموزان متصل به حساب شما',
                'to'      => '/parent/dashboard',
                'icon'    => 'users',
                'variant' => 'primary',
            ];
            $stats[] = [
                'key'     => 'child_classes',
                'label'   => 'کلاس‌های فرزندان',
                'value'   => $childClassCount,
                'hint'    => 'کلاس‌های فعالِ فرزندان شما',
                'to'      => '/parent/dashboard',
                'icon'    => 'class',
                'variant' => 'info',
            ];
            $stats[] = [
                'key'     => 'week_absences',
                'label'   => 'غیبت‌های هفته',
                'value'   => $weekAbsences,
                'hint'    => 'غیبت‌های ثبت‌شده در ۷ روز گذشته',
                'to'      => '/parent/dashboard',
                'icon'    => 'x-circle',
                'variant' => 'danger',
            ];
            $stats[] = [
                'key'     => 'notifications',
                'label'   => 'اعلان‌های جدید',
                'value'   => \App\Modules\Notification\Models\Notification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
                'hint'    => 'خوانده‌نشده',
                'to'      => '/notifications',
                'icon'    => 'bell',
                'variant' => 'warning',
            ];
        }
        // ─── دانش‌آموز: عضویت‌ها، جلسات امروز، حضور این هفته ───
        elseif ($isStudent) {
            $activeMemberships = SchoolMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->count();

            $today = now()->format('Y-m-d');
            $todayRecords = Attendance::query()
                ->where('user_id', $user->id)
                ->where('session_date', $today)
                ->count();

            $weekPresent = Attendance::query()
                ->where('user_id', $user->id)
                ->where('status', 'present')
                ->where('session_date', '>=', now()->subDays(7)->format('Y-m-d'))
                ->count();

            $weekAbsences = Attendance::query()
                ->where('user_id', $user->id)
                ->where('status', 'absent')
                ->where('session_date', '>=', now()->subDays(7)->format('Y-m-d'))
                ->count();

            $stats[] = [
                'key'     => 'memberships',
                'label'   => 'عضویت فعال',
                'value'   => $activeMemberships,
                'hint'    => 'مدرسه‌هایی که در آن عضو هستید',
                'to'      => '/student/dashboard',
                'icon'    => 'building',
                'variant' => 'primary',
            ];
            $stats[] = [
                'key'     => 'today',
                'label'   => 'جلسات امروز',
                'value'   => $todayRecords,
                'hint'    => 'ثبت‌شده برای امروز',
                'to'      => '/student/schedule',
                'icon'    => 'check-circle',
                'variant' => 'info',
            ];
            $stats[] = [
                'key'     => 'week_present',
                'label'   => 'حضور هفته',
                'value'   => $weekPresent,
                'hint'    => 'در ۷ روز گذشته',
                'to'      => '/student/schedule',
                'icon'    => 'check',
                'variant' => 'success',
            ];
            $stats[] = [
                'key'     => 'week_absent',
                'label'   => 'غیبت هفته',
                'value'   => $weekAbsences,
                'hint'    => 'در ۷ روز گذشته',
                'to'      => '/student/schedule',
                'icon'    => 'x-circle',
                'variant' => 'danger',
            ];
        }
        // ─── بدون نقش ───
        else {
            $stats[] = [
                'key'     => 'no_role',
                'label'   => 'نقش',
                'value'   => 'تعیین‌نشده',
                'hint'    => 'هنوز نقشی به حساب شما اختصاص داده نشده است',
                'icon'    => 'info',
                'variant' => 'neutral',
            ];
        }

        return $stats;
    }

    /**
     * روند ثبت‌نام در ۱۰ هفته گذشته — از دیتابیس واقعی.
     *
     * @return list<int>
     */
    protected function enrollmentTrend(User $user, PermissionService $permissions): array
    {
        $isAdmin   = $permissions->isPlatformAdmin($user);
        $isManager = $isAdmin || $permissions->userHasRole($user, 'school_manager');

        // فقط برای ادمین/مدیر معنا دارد؛ بقیه آرایه‌ی خالی می‌گیرند
        if (! ($isAdmin || $isManager)) {
            return array_fill(0, 10, 0);
        }

        $managedIds = $permissions->managedSchoolIds($user);
        $ids = $managedIds === null ? null : ($managedIds ?: ['00000000-0000-0000-0000-000000000000']);

        $trend = [];
        for ($i = 9; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end   = now()->subWeeks($i)->endOfWeek();

            // enrolled_at زمان واقعی عضویت است؛ اگر null بود به created_at برمی‌گردیم.
            $query = ClassEnrollment::query()
                ->whereBetween('enrolled_at', [$start, $end]);
            if ($ids !== null) {
                $query->whereHas('schoolClass', fn ($q) => $q->whereIn('school_id', $ids));
            }
            $trend[] = (int) $query->count();
        }
        return $trend;
    }

    /**
     * برچسب‌های ۱۰ هفته‌ی گذشته (مثلاً «هفته -۹» تا «این هفته»).
     *
     * @return list<string>
     */
    protected function trendLabels(): array
    {
        $labels = [];
        for ($i = 9; $i >= 0; $i--) {
            $labels[] = $i === 0 ? 'این هفته' : ($i . ' هفته پیش');
        }
        return $labels;
    }

    /**
     * فعالیت‌های اخیر — در حال حاضر خالی (می‌توان بعداً از لاگ فعالیت پر کرد).
     *
     * @return list<array{type: string, message: string, time: string}>
     */
    protected function recentActivity(User $user, PermissionService $permissions): array
    {
        return [];
    }

    /**
     * کلاس‌های آنلاین فعال/اخیر برای کاربر.
     *
     * مدل OnlineClass یک «اتاق» به ازای هر school_class است (نه جلسه‌ی زمان‌بندی‌شده).
     * وضعیت‌ها: idle, live, ended, cancelled. ستونی به نام scheduled_at وجود ندارد.
     * بنابراین: کلاس‌های live یا اخیراً شروع‌شده (۷ روز گذشته) که کاربر به آن‌ها دسترسی دارد نمایش می‌دهیم.
     *
     * @return list<array{title: string, when: string, href: string}>
     */
    protected function onlineUpcoming(User $user, PermissionService $permissions): array
    {
        $isAdmin   = $permissions->isPlatformAdmin($user);
        $isManager = $isAdmin || $permissions->userHasRole($user, 'school_manager');
        $isTeacher = $permissions->userHasRole($user, 'teacher');
        $isStudent = $permissions->userHasRole($user, 'student');
        $isParent  = $permissions->userHasRole($user, 'parent');

        // والد دسترسی به کلاس آنلاین ندارد — لیست خالی
        if ($isParent && ! $isAdmin) {
            return [];
        }

        $query = OnlineClass::query()
            ->with('schoolClass:id,name')
            // کلاس‌های live یا اخیراً شروع‌شده (۷ روز گذشته)
            ->where(function ($q) {
                $q->where('status', 'live')
                  ->orWhere('started_at', '>=', now()->subDays(7));
            })
            ->orderByDesc('started_at')
            ->limit(5);

        if ($isManager && ! $isAdmin) {
            $managedIds = $permissions->managedSchoolIds($user);
            $ids = $managedIds ?: ['00000000-0000-0000-0000-000000000000'];
            $query->whereHas('schoolClass', fn ($q) => $q->whereIn('school_id', $ids));
        } elseif ($isTeacher) {
            $myClassIds = SchoolClass::query()
                ->where('teacher_user_id', $user->id)
                ->where('status', 'active')
                ->pluck('id');
            $query->whereIn('school_class_id', $myClassIds);
        } elseif ($isStudent) {
            $myClassIds = ClassEnrollment::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('school_class_id');
            $query->whereIn('school_class_id', $myClassIds);
        }

        return $query->get()
            ->map(function ($oc) {
                $statusLabel = $oc->status === 'live' ? 'در حال برگزاری' : 'برگزاری‌شده';
                $when = $oc->started_at
                    ? $oc->started_at->format('Y-m-d H:i')
                    : 'هنوز شروع نشده';
                return [
                    'title' => ($oc->schoolClass?->name ?? 'کلاس') . ' — ' . $statusLabel,
                    'when'  => $when,
                    'href'  => '/online-classes',
                ];
            })
            ->all();
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
