<script setup>
defineProps({
    modelValue: [String, Number],
    invalid: Boolean,
    type: { type: String, default: 'text' },
    placeholder: String,
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        @input="emit('update:modelValue', $event.target.value)"
        :class="[
            'block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset',
            invalid
                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/30'
                : 'border-slate-300 focus:border-violet-500 focus:ring-violet-500/30'
        ]"
    />
</template>