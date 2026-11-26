<script setup>
import Icon from './Icon.vue';
import { useJalali } from '../composables/useJalali';

const props = defineProps({
    week: { type: Array, default: () => [] },          // [{day_of_week,label,date_iso,is_today,is_future,sessions[],has_absence}]
    weekRange: { type: Object, default: null },         // {start, end}
    summary: { type: Object, default: () => ({ present:0, absent:0, total:0, attendance_rate:null }) },
});

const { formatJalaliShort, toPersianDigits } = useJalali();

const statusMeta = {
    present: { label: 'حاضر', dot: 'bg-success-500', text: 'text-success-700' },
    absent:  { label: 'غایب', dot: 'bg-danger-500', text: 'text-danger-700' },
    late:    { label: 'تأخیر', dot: 'bg-warning-500', text: 'text-warning-700' },
    excused: { label: 'موجه', dot: 'bg-info-500', text: 'text-info-700' },
};
</script>

<template>
    <div>
        <!-- بنر هشدار غیبت -->
        <div
            v-if="summary.absent > 0"
            class="mb-4 flex items-center gap-3 rounded-clay bg-danger-50 px-4 py-3 border border-danger-200"
        >
            <div class="clay-card-flat flex h-9 w-9 shrink-0 items-center justify-center text-danger-600">
                <Icon name="alert-triangle" :size="18" />
            </div>
            <div class="text-sm text-danger-800">
                در این هفته <strong>{{ toPersianDigits(summary.absent) }}</strong> غیبت ثبت شده است.
                لطفاً دلیل غیبت‌ها را پیگیری کنید.
            </div>
        </div>

        <!-- کارت‌های خلاصه -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="clay-card-flat px-4 py-3 text-center">
                <div class="text-2xl font-bold text-success-700">{{ toPersianDigits(summary.present) }}</div>
                <div class="text-xs text-slate-500 mt-1">حضور</div>
            </div>
            <div class="clay-card-flat px-4 py-3 text-center" :class="summary.absent > 0 ? 'ring-2 ring-danger-200' : ''">
                <div class="text-2xl font-bold text-danger-700">{{ toPersianDigits(summary.absent) }}</div>
                <div class="text-xs text-slate-500 mt-1">غیبت</div>
            </div>
            <div class="clay-card-flat px-4 py-3 text-center">
                <div class="text-2xl font-bold text-slate-700">{{ toPersianDigits(summary.total) }}</div>
                <div class="text-xs text-slate-500 mt-1">کل جلسات</div>
            </div>
            <div class="clay-card-flat px-4 py-3 text-center">
                <div class="text-2xl font-bold" :class="summary.attendance_rate === null ? 'text-slate-400' : (summary.attendance_rate >= 90 ? 'text-success-700' : summary.attendance_rate >= 75 ? 'text-warning-700' : 'text-danger-700')">
                    {{ summary.attendance_rate !== null ? toPersianDigits(summary.attendance_rate) + '٪' : '—' }}
                </div>
                <div class="text-xs text-slate-500 mt-1">نرخ حضور</div>
            </div>
        </div>

        <!-- گرید هفتگی -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <div
                v-for="day in week"
                :key="day.date_iso"
                class="clay-card-flat p-3 transition-all"
                :class="[
                    day.has_absence ? '!bg-danger-50 !ring-2 !ring-danger-200' : '',
                    day.is_today ? 'ring-2 ring-brand-300' : '',
                    day.is_future ? 'opacity-60' : '',
                ]"
            >
                <!-- سرستون روز -->
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-bold text-slate-800">{{ day.label }}</div>
                        <div class="text-xs text-slate-400" dir="ltr">{{ formatJalaliShort(day.date_iso) }}</div>
                    </div>
                    <span v-if="day.is_today" class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">امروز</span>
                    <span v-else-if="day.is_future" class="text-[10px] text-slate-400">آینده</span>
                </div>

                <!-- جلسات روز -->
                <div v-if="day.sessions.length === 0" class="py-4 text-center text-xs text-slate-300">
                    <Icon name="calendar" :size="20" />
                    <div class="mt-1">جلسه‌ای نیست</div>
                </div>

                <ul v-else class="space-y-2">
                    <li
                        v-for="(s, idx) in day.sessions"
                        :key="idx"
                        class="rounded-clay-sm p-2 transition"
                        :class="s.has_absence
                            ? 'bg-danger-100 border border-danger-200'
                            : s.attendance_status === 'present'
                                ? 'bg-success-50 border border-success-100'
                                : s.attendance_status
                                    ? 'bg-slate-50 border border-slate-100'
                                    : 'bg-clay-100/50 border border-transparent'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-xs font-semibold text-slate-800">{{ s.class_name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono" dir="ltr">{{ s.start_time }} - {{ s.end_time }}</div>
                            </div>
                            <div v-if="s.attendance_status" class="flex shrink-0 items-center gap-1">
                                <span class="h-2 w-2 rounded-full" :class="statusMeta[s.attendance_status]?.dot || 'bg-slate-300'"></span>
                                <span class="text-[10px] font-medium" :class="statusMeta[s.attendance_status]?.text || 'text-slate-500'">
                                    {{ statusMeta[s.attendance_status]?.label || s.attendance_status }}
                                </span>
                            </div>
                            <span v-else-if="s.is_future" class="text-[10px] text-slate-300">—</span>
                            <span v-else class="text-[10px] text-slate-300">ثبت‌نشده</span>
                        </div>
                        <div v-if="s.room" class="mt-1 text-[10px] text-slate-400">📍 {{ s.room }}</div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
