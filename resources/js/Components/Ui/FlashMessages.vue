<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * بنر فلش سراسری — page.props.flash را می‌خواند.
 * فقط وقتی پیام جدید می‌آید نمایش می‌دهد و بعد از چند ثانیه محو می‌شود.
 */
const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');

const flash = computed(() => page.props.flash || {});

watch(flash, (val) => {
    if (val.success) {
        show(val.success, 'success');
    } else if (val.error) {
        show(val.error, 'danger');
    } else if (val.warning) {
        show(val.warning, 'warning');
    } else if (val.info) {
        show(val.info, 'info');
    }
}, { immediate: true, deep: true });

let timer = null;
function show(msg, t) {
    message.value = msg;
    type.value = t;
    visible.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (visible.value = false), 4500);
}

const icons = {
    success: 'check-circle',
    danger: 'x-circle',
    warning: 'alert-triangle',
    info: 'info',
};
const tones = {
    success: 'bg-success-50 text-success-700 ring-success-500/30',
    danger:  'bg-danger-50 text-danger-700 ring-danger-500/30',
    warning: 'bg-warning-50 text-warning-700 ring-warning-500/30',
    info:    'bg-info-50 text-info-700 ring-info-500/30',
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0 -translate-y-3"
    >
        <div
            v-if="visible"
            class="clay-card fixed inset-x-0 top-4 z-[60] mx-auto flex max-w-md items-start gap-3 px-4 py-3 ring-1"
            :class="tones[type]"
            role="alert"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true">
                <template v-if="type === 'success'"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m9 11 3 3L22 4"/></template>
                <template v-else-if="type === 'danger'"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></template>
                <template v-else-if="type === 'warning'"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></template>
                <template v-else><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></template>
            </svg>
            <p class="flex-1 text-sm font-medium leading-relaxed">{{ message }}</p>
            <button type="button" class="shrink-0 opacity-60 hover:opacity-100" aria-label="بستن" @click="visible = false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>
