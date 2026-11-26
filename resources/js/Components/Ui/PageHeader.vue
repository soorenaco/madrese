<script setup>
defineProps({
    title: { type: String, required: true },
    description: String,
})
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>