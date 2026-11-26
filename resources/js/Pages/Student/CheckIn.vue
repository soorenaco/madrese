<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import Icon from '../../Components/Icon.vue';
import { useToast } from '../../composables/useToast';
import { useJalali } from '../../composables/useJalali';

defineProps({
    todayRecords: { type: Array, default: () => [] },  // [{id, status, slot, schoolClass:{name}, schoolClass.school:{name}}]
});

const toast = useToast();
const page = usePage();
const { formatJalali, toPersianDigits } = useJalali();

// ۶ ورودی مجزا برای هر رقم کد
const digits = ref(['', '', '', '', '', '']);
const inputs = ref([]);
const submitting = ref(false);

function onInput(idx, event) {
    const val = event.target.value.replace(/\D/g, '').slice(-1);
    digits.value[idx] = val;
    // حرکت به فیلد بعدی
    if (val && idx < 5 && inputs.value[idx + 1]) {
        inputs.value[idx + 1].focus();
    }
}

function onKeydown(idx, event) {
    // Backspace: اگر خالی بود، به فیلد قبلی برو
    if (event.key === 'Backspace' && !digits.value[idx] && idx > 0 && inputs.value[idx - 1]) {
        inputs.value[idx - 1].focus();
    }
    // Paste: کد ۶ رقمی را بین فیلدها پخش کن
    if (event.key === 'v' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        navigator.clipboard.readText().then((text) => {
            const clean = text.replace(/\D/g, '').slice(0, 6).padEnd(6, '');
            for (let i = 0; i < 6; i++) digits.value[i] = clean[i] || '';
            inputs.value[5]?.focus();
        });
    }
}

const code = () => digits.value.join('');

const isComplete = () => code().length === 6;

function submit() {
    if (!isComplete()) {
        toast.error('کد را کامل وارد کنید.');
        return;
    }
    submitting.value = true;
    router.post('/student/check-in', { code: code() }, {
        preserveScroll: true,
        onFinish: () => (submitting.value = false),
        onSuccess: () => {
            toast.success('حضور شما ثبت شد.');
            digits.value = ['', '', '', '', '', ''];
            inputs.value[0]?.focus();
        },
        onError: (errors) => {
            const msg = errors.code || 'ثبت حضور ناموفق بود.';
            toast.error(msg);
        },
    });
}

const statusMeta = {
    present: { label: 'حاضر', variant: 'success' },
    absent:  { label: 'غایب', variant: 'danger' },
    late:    { label: 'تأخیر', variant: 'warning' },
    excused: { label: 'موجه', variant: 'info' },
};
</script>

<template>
    <PanelLayout>
        <PageHeader
            title="ثبت حضور با کد"
            description="کد ۶ رقمی را از معلم بگیرید و وارد کنید"
        >
            <template #icon><Icon name="check-circle" :size="22" /></template>
            <template #actions>
                <Link href="/student/dashboard" class="clay-btn-ghost inline-flex items-center gap-1.5 px-3 py-2 text-sm">
                    <Icon name="arrow-right" :size="16" />
                    داشبورد
                </Link>
            </template>
        </PageHeader>

        <!-- فرم کد -->
        <SectionCard class="mt-6" flat>
            <div class="flex flex-col items-center gap-6 py-6">
                <div class="clay-card-flat flex h-16 w-16 items-center justify-center text-brand-600">
                    <Icon name="check-circle" :size="32" />
                </div>
                <div class="text-center">
                    <h2 class="text-base font-bold text-slate-800">کد چک‌این جلسه</h2>
                    <p class="mt-1 text-xs text-slate-500">کد ۶ رقمی روی تخته یا گروه کلاس توسط معلم نوشته می‌شود.</p>
                </div>

                <!-- ۶ فیلد کد -->
                <form @submit.prevent="submit" class="flex flex-col items-center gap-5">
                    <div class="flex gap-2" dir="ltr">
                        <input
                            v-for="(d, idx) in digits"
                            :key="idx"
                            :ref="(el) => { if (el) inputs[idx] = el; }"
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            :value="d"
                            class="h-14 w-12 rounded-clay border-2 border-clay-200 bg-white text-center text-2xl font-bold text-slate-800 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                            :class="d ? 'border-brand-300 bg-brand-50' : ''"
                            @input="onInput(idx, $event)"
                            @keydown="onKeydown(idx, $event)"
                            @paste.prevent
                        />
                    </div>

                    <PrimaryButton :loading="submitting" :disabled="!isComplete() || submitting" type="submit">
                        <Icon name="check" :size="16" />
                        ثبت حضور
                    </PrimaryButton>
                </form>

                <div class="text-center text-xs text-slate-400">
                    <p>کد فقط ۱۵ دقیقه اعتبار دارد.</p>
                    <p>اگر کد منقضی شده، از معلم کد جدید بگیرید.</p>
                </div>
            </div>
        </SectionCard>

        <!-- وضعیت امروز -->
        <SectionCard class="mt-6" title="وضعیت حضور امروز شما" flat>
            <div v-if="!todayRecords.length" class="py-8 text-center text-sm text-slate-400">
                <Icon name="calendar" :size="28" />
                <p class="mt-2">هنوز جلسه‌ای برای امروز ثبت نشده است.</p>
            </div>
            <ul v-else class="divide-y divide-clay-200/70">
                <li
                    v-for="rec in todayRecords"
                    :key="rec.id"
                    class="flex items-center justify-between gap-3 py-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="clay-card-flat flex h-9 w-9 items-center justify-center text-brand-600">
                            <Icon name="class" :size="16" />
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ rec.school_class?.name || 'کلاس' }}</div>
                            <div class="text-xs text-slate-400">{{ rec.school_class?.school?.name || '' }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">جلسه‌ی {{ toPersianDigits(rec.slot) }}</span>
                        <StatusBadge :variant="statusMeta[rec.status]?.variant || 'neutral'">
                            {{ statusMeta[rec.status]?.label || rec.status }}
                        </StatusBadge>
                    </div>
                </li>
            </ul>
        </SectionCard>
    </PanelLayout>
</template>
