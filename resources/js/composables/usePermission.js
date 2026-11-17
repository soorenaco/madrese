import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * دسترسی‌های اشتراک‌گذاشته‌شده‌ی Inertia را برای UI فراهم می‌کند.
 *
 * توجه: این فقط برای نمایش/پنهان‌کردن عناصر رابط است.
 * اعمال واقعی دسترسی باید سمت سرور (middleware / Policy) انجام شود.
 */
export function usePermission() {
    const page = usePage();

    const auth = computed(() => page.props.auth);
    const permissions = computed(() => auth.value?.permissions ?? []);
    const roles = computed(() => auth.value?.roles ?? []);
    const isAdmin = computed(() => Boolean(auth.value?.is_admin));

    function can(permissionCode) {
        if (isAdmin.value) {
            return true;
        }
        return permissions.value.includes(permissionCode);
    }

    function hasRole(roleCode) {
        return roles.value.some((r) => r.code === roleCode);
    }

    function hasAnyRole(...codes) {
        return codes.some((code) => hasRole(code));
    }

    return {
        auth,
        permissions,
        roles,
        isAdmin,
        can,
        hasRole,
        hasAnyRole,
    };
}