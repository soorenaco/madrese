<script setup>
defineProps({
    type: { type: String, default: 'submit' },
    disabled: Boolean,
})
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-violet-500/40',
            disabled
                ? 'cursor-not-allowed bg-slate-100 text-slate-400'
                : 'bg-violet-600 text-white hover:bg-violet-700'
        ]"
    >
        <slot />
    </button>
</template>