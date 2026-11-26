<script setup>
defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'danger', 'info', 'primary'].includes(v),
    },
})

const styles = {
    neutral:  'bg-slate-100 text-slate-700 ring-slate-200',
    success:  'bg-emerald-50 text-emerald-700 ring-emerald-200',
    warning:  'bg-amber-50 text-amber-700 ring-amber-200',
    danger:   'bg-rose-50 text-rose-700 ring-rose-200',
    info:     'bg-sky-50 text-sky-700 ring-sky-200',
    primary:  'bg-violet-50 text-violet-700 ring-violet-200',
}
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', styles[variant]]">
        <slot />
    </span>
</template>