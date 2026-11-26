/**
 * آیتم‌های منوی پنل.
 * فیلتر نمایش فقط برای UI است؛ دسترسی واقعی باید سمت سرور اعمال شود.
 */
export const menuItems = [
    {
        label: 'داشبورد',
        href: '/dashboard',
        icon: 'home',
    },
    {
        label: 'داشبورد دانش‌آموز',
        href: '/student/dashboard',
        icon: 'home',
        roles: ['student'],
    },
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
        label: 'داشبورد والد',
        href: '/parent/dashboard',
        icon: 'users',
        roles: ['parent'],
    },
    {
        label: 'اعلان‌ها',
        href: '/notifications',
        icon: 'bell',
    },
    {
        label: 'کلاس‌ها',
        href: '/classes',
        icon: 'class',
        permission: 'class.view',
        roles: ['teacher', 'school_manager', 'admin'],
    },
    {
        label: 'حضور و غیاب',
        href: '/classes',
        icon: 'check-circle',
        permission: 'attendance.record',
        roles: ['teacher', 'school_manager', 'admin'],
    },
    {
        label: 'مدرسه',
        href: '/school',
        icon: 'school',
        roles: ['school_manager', 'admin'],
    },
    {
        label: 'کاربران',
        href: '/users',
        icon: 'users',
        permission: 'user.invite',
        roles: ['school_manager', 'admin'],
    },
    {
        label: 'تنظیمات سیستم',
        href: '/settings',
        icon: 'settings',
        adminOnly: true,
    },
    {
        label: 'کلاس آنلاین',
        href: '/online-classes',
        icon: 'video',
        permission: 'online_class.view',
        roles: ['student', 'teacher', 'school_manager', 'admin'],
    },
];