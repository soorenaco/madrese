/**
 * آیتم‌های منوی پنل — بر اساس نقش کاربر.
 *
 * قواعد:
 *   - هر آیتم فقط برای نقش‌های لیست‌شده در `roles` نمایش داده می‌شود.
 *   - اگر `roles` تعریف نشده باشد، یعنی آیتم برای همه‌ی نقش‌هاست (مثل اعلان‌ها).
 *   - اگر `adminOnly: true` باشد، فقط برای ادمین پلتفرم است.
 *   - هیچ آیتمی نباید برای کاربری نمایش داده شود که به مسیر آن دسترسی ندارد؛
 *     دسترسی واقعی سمت سرور با middleware `role:` اعمال می‌شود.
 *
 * توجه: نقش‌ها به‌جای منطق «یا»، به‌صورت «دقیقاً این نقش‌ها» در نظر گرفته می‌شوند.
 * ادمین پلتفرم به همه‌ی آیتم‌ها دسترسی دارد.
 */
export const menuItems = [
    // ─── داشبورد اصلی (برای همه) ───
    {
        label: 'داشبورد',
        href: '/dashboard',
        icon: 'home',
        // همه‌ی نقش‌ها — هیچ محدودیتی ندارد
    },

    // ─── دانش‌آموز ───
    {
        label: 'برنامه هفتگی من',
        href: '/student/schedule',
        icon: 'calendar',
        roles: ['student'],
    },
    {
        label: 'ثبت حضور با کد',
        href: '/student/check-in',
        icon: 'check-circle',
        roles: ['student'],
    },
    {
        label: 'کلاس‌های آنلاین',
        href: '/online-classes',
        icon: 'video',
        roles: ['student', 'teacher', 'school_manager', 'admin'],
    },

    // ─── والد ───
    {
        label: 'داشبورد والد',
        href: '/parent/dashboard',
        icon: 'users',
        roles: ['parent'],
    },

    // ─── معلم / مدیر / ادمین ───
    {
        label: 'کلاس‌ها',
        href: '/classes',
        icon: 'class',
        roles: ['teacher', 'school_manager', 'admin'],
    },
    {
        label: 'حضور و غیاب',
        href: '/classes',
        icon: 'check-circle',
        roles: ['teacher', 'school_manager', 'admin'],
    },

    // ─── مدیر مدرسه / ادمین ───
    {
        label: 'مدیریت مدرسه',
        href: '/school',
        icon: 'school',
        roles: ['school_manager', 'admin'],
    },
    {
        label: 'کاربران',
        href: '/users',
        icon: 'users',
        roles: ['school_manager', 'admin'],
    },

    // ─── فقط ادمین پلتفرم ───
    {
        label: 'تنظیمات سیستم',
        href: '/settings',
        icon: 'settings',
        adminOnly: true,
    },

    // ─── اعلان‌ها (برای همه) ───
    {
        label: 'اعلان‌ها',
        href: '/notifications',
        icon: 'bell',
        // همه — اعلان‌ها برای هر کاربری قابل مشاهده است
    },
];
