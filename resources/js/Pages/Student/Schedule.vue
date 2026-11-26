<script setup>
import { Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import WeeklyScheduleGrid from '../../Components/WeeklyScheduleGrid.vue';
import Icon from '../../Components/Icon.vue';
import { useJalali } from '../../composables/useJalali';

defineProps({
    week: { type: Array, default: () => [] },
    weekRange: { type: Object, default: null },
    summary: { type: Object, default: () => ({ present:0, absent:0, total:0, attendance_rate:null }) },
    hasAbsence: { type: Boolean, default: false },
});

const { formatJalali } = useJalali();
</script>

<template>
    <PanelLayout>
        <PageHeader
            title="برنامه هفتگی من"
            description="کلاس‌های هفته جاری و وضعیت حضور و غیاب هر جلسه"
        >
            <template #icon><Icon name="calendar" :size="22" /></template>
            <template #actions>
                <Link href="/student/dashboard" class="clay-btn-ghost inline-flex items-center gap-1.5 px-3 py-2 text-sm">
                    <Icon name="arrow-right" :size="16" />
                    داشبورد
                </Link>
            </template>
        </PageHeader>

        <!-- بنر بازه‌ی هفته -->
        <div class="mt-6 mb-3 flex items-center gap-2 text-xs text-slate-500">
            <Icon name="calendar" :size="14" />
            <span>هفته‌ی جاری:</span>
            <span v-if="weekRange" dir="ltr" class="font-medium text-slate-700">
                {{ formatJalali(weekRange.start) }} تا {{ formatJalali(weekRange.end) }}
            </span>
        </div>

        <WeeklyScheduleGrid
            :week="week"
            :week-range="weekRange"
            :summary="summary"
        />

        <!-- راهنمای رنگ -->
        <div class="mt-6 flex flex-wrap items-center gap-4 rounded-clay bg-clay-100/60 px-4 py-3 text-xs text-slate-600">
            <span class="font-semibold">راهنما:</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-danger-200 border border-danger-300"></span> روز دارای غیبت</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-success-100 border border-success-200"></span> جلسه حاضر</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-slate-100 border border-slate-200"></span> جلسه ثبت‌نشده</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-brand-300 border border-brand-400"></span> امروز</span>
        </div>
    </PanelLayout>
</template>
