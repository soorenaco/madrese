<script setup>
defineProps({
    title: { type: String, default: 'موردی یافت نشد' },
    description: String,
})
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
        <div v-if="$slots.icon" class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
            <slot name="icon" />
        </div>
        <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>