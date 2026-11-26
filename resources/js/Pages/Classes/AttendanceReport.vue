<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import StatCard from '../../Components/Ui/StatCard.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import Icon from '../../Components/Icon.vue';
import { useJalali } from '../../composables/useJalali';

const props = defineProps({
    classItem: { type: Object, required: true },
    rows: { type: Array, default: () => [] },      // [{user, present, absent, late, excused, total}]
    totals: { type: Object, default: () => ({ present:0, absent:0, late:0, excused:0, total:0 }) },
    filters: { type: Object, default: () => ({ from: '', to: '' }) },
    statuses: { type: Array, default: () => [] },
});

const { formatJalaliShort, toPersianDigits } = useJalali();

const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

function applyFilters() {
    router.get(
        `/classes/${props.classItem.id}/attendance/report`,
        { from: from.value || undefined, to: to.value || undefined },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function rate(row) {
    if (!row.total) return null;
    const effective = row.present + row.excused + Math.ceil(row.late / 2);
    return Math.round((effective / row.total) * 1000) / 10;
}

function rateVariant(r) {
    if (r === null) return 'neutral';
    if (r >= 90) return 'success';
    if (r >= 75) return 'warning';
    return 'danger';
}

const overallRate = computedOverall();

function computedOverall() {
    const t = props.totals;
    if (!t.total) return null;
    const effective = t.present + t.excused + Math.ceil(t.late / 2);
    return Math.round((effective / t.total) * 1000) / 10;
}
</script>

<template>
    <PanelLayout>
        <PageHeader
            :title="`گزارش حضور و غیاب — ${classItem.name}`"
            :description="classItem.school?.name || ''"
        >
            <template #icon><Icon name="pie-chart" :size="22" /></template>
            <template #actions>
                <Link :href="`/classes/${classItem.id}/attendance`" class="clay-btn-secondary inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold">
                    <Icon name="check-circle" :size="16" />
                    ثبت جلسه
                </Link>
                <Link href="/classes" class="clay-btn-ghost inline-flex items-center gap-1.5 px-3 py-2 text-sm">
                    <Icon name="arrow-right" :size="16" />
                    کلاس‌ها
                </Link>
            </template>
        </PageHeader>

        <!-- فیلتر بازه‌ی تاریخی -->
        <SectionCard class="mt-6" flat>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-xs text-slate-500">از تاریخ</span>
                        <input type="date" v-model="from" class="clay-input px-3 py-2 text-sm" />
                    </label>
                    <span class="pb-2 text-slate-300">—</span>
                    <label class="block">
                        <span class="mb-1 block text-xs text-slate-500">تا تاریخ</span>
                        <input type="date" v-model="to" class="clay-input px-3 py-2 text-sm" />
                    </label>
                    <button type="button" class="clay-btn-primary px-4 py-2 text-sm" @click="applyFilters">
                        <Icon name="filter" :size="15" /> اعمال
                    </button>
                </div>
                <div v-if="filters.from || filters.to" class="text-xs text-slate-500">
                    بازه:
                    <span v-if="filters.from" dir="ltr">{{ formatJalaliShort(filters.from) }}</span>
                    <span v-if="filters.from && filters.to"> تا </span>
                    <span v-if="filters.to" dir="ltr">{{ formatJalaliShort(filters.to) }}</span>
                </div>
            </div>
        </SectionCard>

        <!-- کارت‌های خلاصه‌ی کلی -->
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard label="کل جلسات ثبت‌شده" :value="toPersianDigits(totals.total)" variant="neutral">
                <template #icon><Icon name="layers" :size="22" /></template>
            </StatCard>
            <StatCard label="حضور" :value="toPersianDigits(totals.present)" variant="success">
                <template #icon><Icon name="check" :size="22" /></template>
            </StatCard>
            <StatCard label="غیبت" :value="toPersianDigits(totals.absent)" variant="danger">
                <template #icon><Icon name="x-circle" :size="22" /></template>
            </StatCard>
            <StatCard label="تأخیر" :value="toPersianDigits(totals.late)" variant="warning">
                <template #icon><Icon name="clock" :size="22" /></template>
            </StatCard>
            <StatCard label="نرخ حضور کلی" :value="overallRate !== null ? toPersianDigits(overallRate) + '٪' : '—'" :variant="rateVariant(overallRate)">
                <template #icon><Icon name="trending-up" :size="22" /></template>
            </StatCard>
        </div>

        <!-- جدول گزارش به‌ازای دانش‌آموز -->
        <SectionCard class="mt-6" title="گزارش به‌ازای دانش‌آموز" description="آمار تجمیعی هر دانش‌آموز در بازه‌ی انتخاب‌شده" flat>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-clay-200 bg-clay-100/60 text-slate-600">
                            <th class="px-4 py-3 text-right font-semibold">دانش‌آموز</th>
                            <th class="px-4 py-3 text-center font-semibold">حاضر</th>
                            <th class="px-4 py-3 text-center font-semibold">غایب</th>
                            <th class="px-4 py-3 text-center font-semibold">تأخیر</th>
                            <th class="px-4 py-3 text-center font-semibold">موجه</th>
                            <th class="px-4 py-3 text-center font-semibold">کل</th>
                            <th class="px-4 py-3 text-center font-semibold">نرخ حضور</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-clay-200/70">
                        <tr v-for="row in rows" :key="row.user?.id" class="transition-colors hover:bg-clay-100/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="clay-card-flat flex h-9 w-9 shrink-0 items-center justify-center text-xs font-bold text-brand-700">
                                        {{ (row.user?.display_name || '؟').charAt(0) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-800">{{ row.user?.display_name || '—' }}</div>
                                        <div class="text-xs font-mono text-slate-400" dir="ltr">{{ row.user?.mobile_e164 }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-semibold text-success-700">{{ toPersianDigits(row.present) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-semibold text-danger-700">{{ toPersianDigits(row.absent) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-semibold text-warning-700">{{ toPersianDigits(row.late) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm font-semibold text-info-700">{{ toPersianDigits(row.excused) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-sm text-slate-600">{{ toPersianDigits(row.total) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <StatusBadge :variant="rateVariant(rate(row))">
                                    {{ rate(row) !== null ? toPersianDigits(rate(row)) + '٪' : '—' }}
                                </StatusBadge>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center gap-2">
                                    <Icon name="pie-chart" :size="28" />
                                    <span>داده‌ای برای نمایش وجود ندارد.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </SectionCard>
    </PanelLayout>
</template>
