<script setup>
import { watch, onUnmounted, ref, nextTick } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    maxWidth: {
        type: String,
        default: 'lg',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl'].includes(v),
    },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};

const panelRef = ref(null);

function onKeydown(e) {
    if (!props.show) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        emit('close');
        return;
    }
    if (e.key === 'Tab' && panelRef.value) {
        // simple focus trap
        const focusable = panelRef.value.querySelectorAll(
            'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
}

watch(() => props.show, async (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
    if (val) {
        await nextTick();
        panelRef.value?.focus();
        document.addEventListener('keydown', onKeydown);
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

function onBackdropClick() {
    if (props.closeOnBackdrop) emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-brand-950/40 backdrop-blur-sm" @click="onBackdropClick" />
                <Transition
                    enter-active-class="transition duration-220 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        ref="panelRef"
                        tabindex="-1"
                        :class="['clay-card relative w-full overflow-hidden outline-none', widths[maxWidth]]"
                    >
                        <header v-if="title || $slots.header" class="flex items-center justify-between gap-3 px-5 py-4">
                            <slot name="header">
                                <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                            </slot>
                            <button
                                type="button"
                                class="clay-btn-ghost -ml-2 -mt-1 p-1.5 text-slate-400 hover:text-slate-700"
                                aria-label="بستن"
                                @click="emit('close')"
                            >
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                                </svg>
                            </button>
                        </header>
                        <div :class="title || $slots.header ? 'p-5 pt-0' : 'p-5'">
                            <slot />
                        </div>
                        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-clay-200 bg-clay-50/60 px-5 py-3">
                            <slot name="footer" />
                        </footer>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
