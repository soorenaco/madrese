<script setup>
import { useSlots } from 'vue';

const props = defineProps({
    label: String,
    description: String,
    error: String,
    required: Boolean,
    hint: String,
});

const slots = useSlots();
let fieldCounter = 0;
const uid = `field-${++fieldCounter}`;
</script>

<template>
    <div class="space-y-1.5">
        <div v-if="label" class="flex items-baseline justify-between gap-2">
            <label :for="uid" class="text-sm font-medium text-slate-700">
                {{ label }}
                <span v-if="required" class="text-danger-500">*</span>
            </label>
            <span v-if="hint" class="text-xs text-slate-400">{{ hint }}</span>
        </div>
        <p v-if="description" class="text-xs text-slate-500 leading-relaxed">{{ description }}</p>
        <slot :id="uid" :invalid="!!error" />
        <p v-if="error" class="flex items-center gap-1 text-xs text-danger-600">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
            </svg>
            {{ error }}
        </p>
    </div>
</template>
