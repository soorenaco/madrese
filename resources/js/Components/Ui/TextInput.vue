<script setup>
import { computed } from 'vue';
import Icon from '../Icon.vue';

const props = defineProps({
    modelValue: [String, Number],
    invalid: Boolean,
    type: { type: String, default: 'text' },
    placeholder: String,
    dir: String,            // 'ltr' | 'rtl' | null
    disabled: Boolean,
    id: String,
    autocomplete: String,
    icon: String,           // optional leading icon name (Icon.vue)
});

const emit = defineEmits(['update:modelValue', 'blur', 'focus']);

const resolvedDir = computed(() => {
    if (props.dir) return props.dir;
    // auto-detect LTR for numeric/code/mobile/otp-like types
    if (['tel', 'number', 'otp', 'password'].includes(props.type)) return 'ltr';
    return undefined;
});
</script>

<template>
    <div class="relative">
        <span
            v-if="icon"
            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400"
        >
            <Icon :name="icon" :size="18" />
        </span>
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :dir="resolvedDir"
            class="clay-input block w-full px-4 py-2.5 text-sm"
            :class="[
                icon && 'pr-10',
                invalid && '!border-danger-400 ring-2 ring-danger-500/20',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            @blur="emit('blur', $event)"
            @focus="emit('focus', $event)"
        />
    </div>
</template>
