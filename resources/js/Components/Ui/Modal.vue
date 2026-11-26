<script setup>
import { watch, onUnmounted } from 'vue'

const props = defineProps({
    show: Boolean,
    title: String,
    maxWidth: { type: String, default: 'lg' },
})

const emit = defineEmits(['close'])

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
}

watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
})

onUnmounted(() => {
    document.body.style.overflow = ''
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="emit('close')" />
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="show" :class="['relative w-full overflow-hidden rounded-xl bg-white shadow-xl', widths[maxWidth]]">
                        <header v-if="title || $slots.header" class="border-b border-slate-200 px-5 py-4">
                            <slot name="header">
                                <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                            </slot>
                        </header>
                        <div class="p-5">
                            <slot />
                        </div>
                        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                            <slot name="footer" />
                        </footer>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>