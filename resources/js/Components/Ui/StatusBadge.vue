<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'danger', 'info', 'primary'].includes(v),
    },
    dot: { type: Boolean, default: true },
});

// clay-toned badges: soft bg + inset highlight + subtle ring
const styles = {
    neutral:  'bg-slate-100 text-slate-700 ring-slate-200/70',
    success:  'bg-success-50 text-success-700 ring-success-500/30',
    warning:  'bg-warning-50 text-warning-700 ring-warning-500/30',
    danger:   'bg-danger-50 text-danger-700 ring-danger-500/30',
    info:     'bg-info-50 text-info-700 ring-info-500/30',
    primary:  'bg-brand-50 text-brand-700 ring-brand-500/30',
};
const dotColor = {
    neutral: 'bg-slate-400',
    success: 'bg-success-500',
    warning: 'bg-warning-500',
    danger:  'bg-danger-500',
    info:    'bg-info-500',
    primary: 'bg-brand-500',
};
</script>

<template>
    <span
        :class="['clay-chip inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', styles[variant]]"
    >
        <span v-if="dot" :class="['h-1.5 w-1.5 rounded-full', dotColor[variant]]" />
        <slot />
    </span>
</template>
