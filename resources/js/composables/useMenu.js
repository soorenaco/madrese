import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { menuItems } from '../config/menu';
import { usePermission } from './usePermission';

/**
 * فیلتر منو بر اساس نقش کاربر.
 *
 * قواعد:
 *   - adminOnly: فقط ادمین پلتفرم
 *   - roles: کاربر باید حداقل یکی از نقش‌های لیست‌شده را داشته باشد
 *   - بدون roles و adminOnly: برای همه‌ی کاربران
 *
 * ادمین پلتفرم به همه‌ی آیتم‌ها دسترسی دارد (با منطق بای‌پس سرور هم‌خوانی دارد).
 */
export function useMenu() {
    const page = usePage();
    const { hasAnyRole, isAdmin } = usePermission();

    const items = computed(() => {
        return menuItems.filter((item) => {
            // آیتم مخصوص ادمین
            if (item.adminOnly) {
                return isAdmin.value;
            }

            // ادمین پلتفرم به همه‌ی آیتم‌ها دسترسی دارد
            if (isAdmin.value) {
                return true;
            }

            // آیتم محدود به نقش‌های خاص
            if (item.roles?.length) {
                return hasAnyRole(...item.roles);
            }

            // آیتم عمومی (برای همه)
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
        // برای آیتم‌هایی که به /classes اشاره می‌کنند (کلاس‌ها و حضور و غیاب)
        // هر دو فعال نشوند — فقط دقیقاً /classes یا زیرمسیرهای آن
        if (href === '/classes') {
            return path === '/classes' || path.startsWith('/classes/');
        }
        return path === href || path.startsWith(href + '/');
    }

    return {
        items,
        currentPath,
        isActive,
    };
}
