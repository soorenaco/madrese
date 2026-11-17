import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { menuItems } from '../config/menu';
import { usePermission } from './usePermission';

export function useMenu() {
    const page = usePage();
    const { can, hasAnyRole, isAdmin } = usePermission();

    const items = computed(() => {
        return menuItems.filter((item) => {
            if (item.adminOnly) {
                return isAdmin.value;
            }

            if (item.roles?.length) {
                const hasRole = hasAnyRole(...item.roles);
                if (item.permission) {
                    return hasRole || can(item.permission);
                }
                return hasRole;
            }

            if (item.permission) {
                return can(item.permission);
            }

            return true;
        });
    });

    const currentPath = computed(() => {
        const url = page.url || '';
        try {
            return new URL(url, 'http://local').pathname;
        } catch {
            return url.split('?')[0] || '/';
        }
    });

    function isActive(href) {
        const path = currentPath.value;
        if (href === '/dashboard') {
            return path === '/dashboard' || path === '/';
        }
        return path === href || path.startsWith(href + '/');
    }

    return {
        items,
        currentPath,
        isActive,
    };
}