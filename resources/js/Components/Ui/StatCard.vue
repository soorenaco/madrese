<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: String,
    hint: String,
    trend: Number,           // درصد تغییر (مثلاً +12 یا -5)
    trendLabel: String,      // برچسب اختیاری مثل «نسبت به هفته گذشته»
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
    to: String,              // تبدیل کارت به لینک
});

const tone = computed(() => {
    const map = {
        primary:  { iconBg: 'bg-brand-100 text-brand-700' },
        success:  { iconBg: 'bg-success-50 text-success-700' },
        warning:  { iconBg: 'bg-warning-50 text-warning-700' },
        danger:   { iconBg: 'bg-danger-50 text-danger-700' },
        info:     { iconBg: 'bg-info-50 text-info-700' },
        neutral:  { iconBg: 'bg-slate-100 text-slate-700' },
    };
    return map[props.variant];
});

const trendUp = computed(() => typeof props.trend === 'number' && props.trend >= 0);

const tag = computed(() => (props.to ? Link : 'div'));
</script>

<template>
    <component
        :is="tag"
        :href="to"
        class="clay-card group relative flex flex-col gap-4 p-5 transition-transform duration-200 hover:-translate-y-0.5"
        :class="to && 'cursor-pointer'"
    >
        <div class="flex items-start justify-between gap-3">
            <div :class="['clay-card-flat flex h-11 w-11 items-center justify-center', tone.iconBg]">
                <slot name="icon">
                    <svg v-if="icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>
                    </svg>
                </slot>
            </div>
            <div
                v-if="trend !== undefined"
                :class="[
                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                    trendUp ? 'bg-success-50 text-success-700' : 'bg-danger-50 text-danger-700'
                ]"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <template v-if="trendUp">
                        <path d="m6 17 11-11"/><path d="M11 6h6v6"/>
                    </template>
                    <template v-else>
                        <path d="m6 7 11 11"/><path d="M17 18h-6v-6"/>
                    </template>
                </svg>
                {{ Math.abs(trend) }}٪
            </div>
        </div>
        <div>
            <div class="text-2xl font-bold text-slate-900 leading-none">{{ value }}</div>
            <div class="mt-1.5 text-sm font-medium text-slate-600">{{ label }}</div>
            <div v-if="hint || trendLabel" class="mt-0.5 text-xs text-slate-400">
                {{ hint || trendLabel }}
            </div>
        </div>
    </component>
</template>
