<script setup>
defineProps({
    type: { type: String, default: 'submit' },
    disabled: Boolean,
    loading: Boolean,
    icon: String,
    block: Boolean,
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="['clay-btn-primary inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold', block && 'w-full']"
    >
        <svg
            v-if="loading"
            class="animate-spin"
            width="16" height="16" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2.5"
            stroke-linecap="round" aria-hidden="true"
        >
            <path d="M21 12a9 9 0 1 1-6.2-8.5" />
        </svg>
        <slot name="icon" v-else-if="$slots.icon" />
        <slot />
    </button>
</template>
