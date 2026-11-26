<script setup>
defineProps({
    title: String,
    description: String,
})
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header v-if="title || $slots.header" class="border-b border-slate-200 px-5 py-4">
            <slot name="header">
                <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </slot>
        </header>
        <div class="p-5">
            <slot />
        </div>
        <footer v-if="$slots.footer" class="border-t border-slate-200 bg-slate-50 px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>