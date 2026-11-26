<script setup>
import { useToast } from '../../composables/useToast';

const { state, dismiss } = useToast();

const tones = {
    success: 'bg-success-50 text-success-700 ring-success-500/30',
    danger:  'bg-danger-50 text-danger-700 ring-danger-500/30',
    warning: 'bg-warning-50 text-warning-700 ring-warning-500/30',
    info:    'bg-info-50 text-info-700 ring-info-500/30',
};

function iconFor(type) {
    return {
        success: 'M22 11.1V12a10 10 0 1 1-5.9-9.1 M9 11l3 3L22 4',
        danger:  'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z M15 9l-6 6 M9 9l6 6',
        warning: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z M12 9v4 M12 17h.01',
        info:    'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z M12 16v-4 M12 8h.01',
    }[type];
}
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:left-4 sm:items-start"
        >
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-3 sm:-translate-x-3"
                enter-to-class="opacity-100 translate-y-0 sm:translate-x-0"
                leave-active-class="transition duration-200 ease-in absolute"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 translate-y-2"
                move-class="transition duration-200"
            >
                <div
                    v-for="t in state.items"
                    :key="t.id"
                    :class="[
                        'clay-card pointer-events-auto flex w-full max-w-sm items-start gap-3 px-4 py-3 ring-1',
                        tones[t.type],
                    ]"
                    role="alert"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="mt-0.5 shrink-0" aria-hidden="true">
                        <path :d="iconFor(t.type)" />
                    </svg>
                    <p class="flex-1 text-sm font-medium leading-relaxed">{{ t.message }}</p>
                    <button type="button" class="shrink-0 opacity-60 hover:opacity-100"
                        aria-label="بستن" @click="dismiss(t.id)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
