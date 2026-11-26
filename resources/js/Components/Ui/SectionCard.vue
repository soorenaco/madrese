<script setup>
defineProps({
    title: String,
    description: String,
    inset: Boolean,    // use inset style for nested groupings
    flat: Boolean,     // flatter shadow
});
</script>

<template>
    <section
        :class="[
            inset ? 'clay-card-inset' : flat ? 'clay-card-flat' : 'clay-card',
            'overflow-hidden'
        ]"
    >
        <header v-if="title || $slots.header || $slots.actions" class="flex items-start justify-between gap-3 px-5 py-4">
            <div class="min-w-0">
                <slot name="header">
                    <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                    <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </header>
        <div :class="$slots.header || title || $slots.actions ? 'p-5 pt-0' : 'p-5'">
            <slot />
        </div>
        <footer v-if="$slots.footer" class="border-t border-clay-200 bg-clay-50/60 px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>
