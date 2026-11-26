<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import WeeklyScheduleGrid from '../../Components/WeeklyScheduleGrid.vue';
import Icon from '../../Components/Icon.vue';
import { useJalali } from '../../composables/useJalali';

const props = defineProps({
    children: { type: Array, default: () => [] },
    selectedChild: { type: String, default: null },
    week: { type: Array, default: () => [] },
    weekRange: { type: Object, default: null },
    summary: { type: Object, default: () => ({ present:0, absent:0, total:0, attendance_rate:null }) },
    hasAbsence: { type: Boolean, default: false },
    recentNotifications: { type: Array, default: () => [] },
});

const page = usePage();
const { formatJalali, toPersianDigits } = useJalali();

const selected = ref(props.selectedChild);

watch(selected, (val) => {
    if (val && val !== props.selectedChild) {
        router.get('/parent/dashboard', { child: val }, { preserveScroll: true, replace: true });
    }
});

const selectedChildObj = computed(() => props.children.find((c) => c.id === props.selectedChild));

function notifIcon(type) {
    return {
        'attendance.absent': 'x-circle',
        'attendance.late': 'clock',
        'general': 'bell',
    }[type] || 'bell';
}

function notifTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return 'همین حالا';
    if (diff < 3600) return toPersianDigits(Math.floor(diff/60)) + ' دقیقه پیش';
    if (diff < 86400) return toPersianDigits(Math.floor(diff/3600)) + ' ساعت پیش';
    return formatJalali(iso, { digits: true });
}
</script>

<template>
    <PanelLayout>
        <PageHeader
            :title="selectedChildObj ? `برنامه‌ی فرزند: ${selectedChildObj.display_name}` : 'داشبورد والد'"
            description="مشاهده برنامه هفتگی، وضعیت حضور و غیاب و اعلان‌های فرزند"
        >
            <template #icon><Icon name="users" :size="22" /></template>
            <template #actions>
                <Link href="/notifications" class="clay-btn-secondary inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold">
                    <Icon name="bell" :size="16" />
                    همه‌ی اعلان‌ها
                </Link>
            </template>
        </PageHeader>

        <!-- انتخاب فرزند -->
        <div v-if="children.length > 1" class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="child in children"
                :key="child.id"
                type="button"
                :class="[
                    'clay-chip px-4 py-2 text-sm font-medium',
                    selected === child.id ? '!bg-brand-600 !text-white' : 'text-slate-600'
                ]"
                @click="selected = child.id"
            >
                <Icon name="user" :size="14" />
                {{ child.display_name }}
            </button>
        </div>

        <!-- حالت بدون فرزند -->
        <SectionCard v-if="!children.length" class="mt-6" flat>
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="clay-card-flat flex h-14 w-14 items-center justify-center text-slate-400">
                    <Icon name="users" :size="28" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-700">هنوز فرزندی به حسابتان متصل نیست.</p>
                    <p class="mt-1 text-xs text-slate-500">برای اتصال فرزند، با مدیر مدرسه تماس بگیرید.</p>
                </div>
            </div>
        </SectionCard>

        <template v-else>
            <!-- نوتیف‌های اخیر -->
            <SectionCard v-if="recentNotifications.length" class="mt-6" title="اعلان‌های اخیر" flat>
                <ul class="divide-y divide-clay-200/70">
                    <li
                        v-for="n in recentNotifications"
                        :key="n.id"
                        class="flex items-start gap-3 py-3"
                    >
                        <div
                            class="clay-card-flat flex h-9 w-9 shrink-0 items-center justify-center"
                            :class="n.type === 'attendance.absent' ? 'text-danger-600' : n.type === 'attendance.late' ? 'text-warning-600' : 'text-brand-600'"
                        >
                            <Icon :name="notifIcon(n.type)" :size="16" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800">{{ n.title }}</span>
                                <span class="text-[10px] text-slate-400 shrink-0">{{ notifTime(n.created_at) }}</span>
                            </div>
                            <p v-if="n.body" class="mt-0.5 text-xs text-slate-600">{{ n.body }}</p>
                            <span
                                v-if="!n.read_at"
                                class="mt-1 inline-block rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-medium text-brand-700"
                            >جدید</span>
                        </div>
                    </li>
                </ul>
            </SectionCard>

            <!-- بنر بازه‌ی هفته -->
            <div class="mt-6 mb-3 flex items-center gap-2 text-xs text-slate-500">
                <Icon name="calendar" :size="14" />
                <span>برنامه‌ی هفته‌ی جاری:</span>
                <span v-if="weekRange" dir="ltr" class="font-medium text-slate-700">
                    {{ formatJalali(weekRange.start) }} تا {{ formatJalali(weekRange.end) }}
                </span>
            </div>

            <!-- گرید هفتگی -->
            <WeeklyScheduleGrid
                :week="week"
                :week-range="weekRange"
                :summary="summary"
            />
        </template>
    </PanelLayout>
</template>
