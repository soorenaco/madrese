<script setup>
import { ref, watch } from 'vue';
import Modal from './Modal.vue';
import PrimaryButton from './PrimaryButton.vue';
import SecondaryButton from './SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    title: { type: String, default: 'تأیید عملیات' },
    message: { type: String, default: 'آیا از انجام این عملیات مطمئن هستید؟' },
    confirmText: { type: String, default: 'تأیید' },
    cancelText: { type: String, default: 'انصراف' },
    variant: {
        type: String,
        default: 'danger',
        validator: (v) => ['danger', 'warning', 'primary'].includes(v),
    },
    loading: Boolean,
});

const emit = defineEmits(['confirm', 'cancel']);

function onConfirm() {
    emit('confirm');
}
function onCancel() {
    emit('cancel');
}

const iconTone = {
    danger: 'bg-danger-50 text-danger-600',
    warning: 'bg-warning-50 text-warning-600',
    primary: 'bg-brand-50 text-brand-600',
};
</script>

<template>
    <Modal :show="show" :title="title" max-width="md" :close-on-backdrop="false" @close="onCancel">
        <div class="flex gap-4">
            <div
                :class="['clay-card-flat flex h-12 w-12 shrink-0 items-center justify-center', iconTone[variant]]"
            >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                    <path d="M12 9v4"/><path d="M12 17h.01"/>
                </svg>
            </div>
            <p class="pt-1 text-sm leading-relaxed text-slate-600">{{ message }}</p>
        </div>
        <template #footer>
            <SecondaryButton @click="onCancel">{{ cancelText }}</SecondaryButton>
            <PrimaryButton :loading="loading" @click="onConfirm">{{ confirmText }}</PrimaryButton>
        </template>
    </Modal>
</template>
