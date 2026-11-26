<script setup>
defineProps({
    title: { type: String, required: true },
    description: String,
    icon: String,
});
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3 min-w-0">
            <div v-if="icon || $slots.icon"
                 class="clay-card-flat flex h-11 w-11 shrink-0 items-center justify-center text-brand-600">
                <slot name="icon">
                    <svg v-if="icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>
                    </svg>
                </slot>
            </div>
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight text-slate-900 text-balance">{{ title }}</h1>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2 flex-wrap">
            <slot name="actions" />
        </div>
    </div>
</template>
