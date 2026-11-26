<script setup>
defineProps({
    modelValue: [String, Number],
    invalid: Boolean,
    disabled: Boolean,
    id: String,
    placeholder: String,
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <select
        :id="id"
        :value="modelValue"
        :disabled="disabled"
        class="clay-input block w-full px-4 py-2.5 text-sm appearance-none pl-9"
        :class="invalid && '!border-danger-400 ring-2 ring-danger-500/20'"
        @change="emit('update:modelValue', $event.target.value)"
    >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <slot />
    </select>
    <svg
        class="pointer-events-none -mt-7 ml-3 inline-block text-slate-400 align-middle"
        width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
    >
        <path d="m6 9 6 6 6-6" />
    </svg>
</template>
