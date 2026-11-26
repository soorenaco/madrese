<script setup>
defineProps({
    title: { type: String, default: 'موردی یافت نشد' },
    description: String,
    icon: { type: String, default: 'search' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="clay-card-flat mb-4 flex h-16 w-16 items-center justify-center text-slate-400">
            <slot name="icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 21l-4.3-4.3"/><circle cx="11" cy="11" r="7"/>
                </svg>
            </slot>
        </div>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-slate-500 max-w-xs">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
