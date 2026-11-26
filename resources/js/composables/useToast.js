import { reactive } from 'vue';

/**
 * سیستم نوتیفیکیشن سراسری (toast).
 * استفاده:
 *   const toast = useToast();
 *   toast.success('ذخیره شد');
 *   toast.error('خطا');
 *   toast.warning('توجه');
 *   toast.info('اطلاع');
 *
 * هر toast بعد از duration (پیش‌فرض ۴ ثانیه) محو می‌شود.
 */
const state = reactive({
    items: [],
});

let _seq = 0;

function push(message, type = 'info', duration = 4000) {
    const id = ++_seq;
    state.items.push({ id, message, type });
    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }
    return id;
}

function dismiss(id) {
    const idx = state.items.findIndex((t) => t.id === id);
    if (idx !== -1) state.items.splice(idx, 1);
}

function clear() {
    state.items.splice(0, state.items.length);
}

export function useToast() {
    return {
        state,
        success: (m, d) => push(m, 'success', d),
        error:   (m, d) => push(m, 'danger', d),
        warning: (m, d) => push(m, 'warning', d),
        info:    (m, d) => push(m, 'info', d),
        dismiss,
        clear,
    };
}
