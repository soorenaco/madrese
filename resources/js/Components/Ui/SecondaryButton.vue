<script setup>
defineProps({
    type: { type: String, default: 'button' },
    disabled: Boolean,
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="clay-btn-ghost inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium"
    >
        <slot />
    </button>
</template>
