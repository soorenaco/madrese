<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import StatCard from '../../Components/Ui/StatCard.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import SecondaryButton from '../../Components/Ui/SecondaryButton.vue';
import Icon from '../../Components/Icon.vue';
import { useJalali } from '../../composables/useJalali';
import { useToast } from '../../composables/useToast';

const props = defineProps({
    classItem: { type: Object, required: true },
    sessionDate: { type: String, required: true },  // 'Y-M-D' میلادی
    slot: { type: Number, default: 1 },
    roster: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ present: 0, absent: 0, late: 0, excused: 0, total: 0 }) },
    recordedDays: { type: Array, default: () => [] }, // [{date, slot, recorded}]
    statuses: { type: Array, default: () => [] },    // [{code,label,variant}]
    canRecord: { type: Boolean, default: true },
    activeCode: { type: Object, default: null },     // {code, expires_at, generated_at}
});

const { formatJalali, formatJalaliShort, weekdayName, recentDays, toPersianDigits } = useJalali();
const toast = useToast();
const page = usePage();

// وضعیت محلی هر دانش‌آموز: { [userId]: { status, note } }
const localState = ref({});
const saving = ref(false);

// ─── کد چک‌این دانش‌آموز ───
const generatingCode = ref(false);
const codeExpiresIn = ref(null); // ثانیه‌ی باقی‌مانده
let codeTimer = null;

function updateCodeCountdown() {
    if (!props.activeCode?.expires_at) {
        codeExpiresIn.value = null;
        return;
    }
    const expires = new Date(props.activeCode.expires_at).getTime();
    const now = Date.now();
    const diff = Math.floor((expires - now) / 1000);
    codeExpiresIn.value = diff > 0 ? diff : 0;
}

onMounted(() => {
    updateCodeCountdown();
    codeTimer = setInterval(updateCodeCountdown, 1000);
});
onUnmounted(() => {
    if (codeTimer) clearInterval(codeTimer);
});
watch(() => props.activeCode?.expires_at, updateCodeCountdown);

function generateCode() {
    generatingCode.value = true;
    router.post(
        `/classes/${props.classItem.id}/attendance/code`,
        { session_date: props.sessionDate, slot: props.slot },
        {
            preserveScroll: true,
            onFinish: () => (generatingCode.value = false),
            onSuccess: () => toast.success('کد چک‌این ساخته شد.'),
            onError: () => toast.error('ساخت کد ناموفق بود.'),
        },
    );
}

const codeDisplay = computed(() => {
    if (!props.activeCode?.code) return '';
    return props.activeCode.code.split('').join(' ');
});

const codeMinutes = computed(() => {
    if (codeExpiresIn.value === null) return null;
    return Math.floor(codeExpiresIn.value / 60);
});
const codeSeconds = computed(() => {
    if (codeExpiresIn.value === null) return null;
    return codeExpiresIn.value % 60;
});
const codeExpired = computed(() => codeExpiresIn.value !== null && codeExpiresIn.value <= 0);

// مقداردهی اولیه از داده‌ی سرور
function initLocalState() {
    const next = {};
    for (const item of props.roster) {
        const uid = item.user.id;
        const att = item.attendance;
        next[uid] = {
            status: att?.status || 'present',
            note: att?.note || '',
        };
    }
    localState.value = next;
}
initLocalState();

// وقتی props عوض شد (تغییر تاریخ) دوباره مقداردهی کن
watch(
    () => props.sessionDate + ':' + props.slot,
    initLocalState,
);

// تعداد تغییرات نسبت به حالت ذخیره‌شده (برای نمایش دکمه‌ی ذخیره)
const dirtyCount = computed(() => {
    let n = 0;
    for (const item of props.roster) {
        const uid = item.user.id;
        const cur = localState.value[uid];
        const orig = item.attendance?.status || 'present';
        const origNote = item.attendance?.note || '';
        if (!cur) continue;
        if (cur.status !== orig || (cur.note || '') !== origNote) n++;
    }
    return n;
});

const hasRoster = computed(() => props.roster.length > 0);
const isToday = computed(() => {
    const t = new Date();
    const today = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
    return props.sessionDate === today;
});

// خلاصه‌ی لحظه‌ای (از state محلی، نه سرور)
const liveSummary = computed(() => {
    const s = { present: 0, absent: 0, late: 0, excused: 0, unrecorded: 0 };
    for (const item of props.roster) {
        const cur = localState.value[item.user.id];
        if (!cur) { s.unrecorded++; continue; }
        if (s[cur.status] !== undefined) s[cur.status]++;
    }
    s.total = props.roster.length;
    return s;
});

// روزهایی که ثبت شده (برای های‌لایت در انتخاب سریع)
const recordedDateSet = computed(() => new Set(props.recordedDays.map((d) => d.date)));

const recent = computed(() => recentDays(10));

function changeDate(iso) {
    router.get(
        `/classes/${props.classItem.id}/attendance`,
        { date: iso, slot: props.slot },
        { preserveState: false, replace: true },
    );
}

function changeSlot(s) {
    router.get(
        `/classes/${props.classItem.id}/attendance`,
        { date: props.sessionDate, slot: s },
        { preserveState: false, replace: true },
    );
}

// ست کردن گروهی همه به یک وضعیت
function markAll(status) {
    const next = { ...localState.value };
    for (const uid of Object.keys(next)) {
        next[uid] = { ...next[uid], status };
    }
    localState.value = next;
}

function setStatus(uid, status) {
    localState.value = {
        ...localState.value,
        [uid]: { ...localState.value[uid], status },
    };
}

function save() {
    const records = props.roster.map((item) => ({
        user_id: item.user.id,
        status: localState.value[item.user.id]?.status || 'present',
        note: localState.value[item.user.id]?.note || null,
    }));

    saving.value = true;
    router.post(
        `/classes/${props.classItem.id}/attendance`,
        {
            session_date: props.sessionDate,
            slot: props.slot,
            records,
        },
        {
            preserveScroll: true,
            onFinish: () => (saving.value = false),
            onSuccess: () => toast.success('حضور و غیاب ثبت شد.'),
            onError: () => toast.error('ثبت حضور و غیاب با خطا مواجه شد.'),
        },
    );
}

// متادیتای دکمه‌های وضعیت (آیکون + رنگ)
const statusBtnMeta = {
    present: { icon: 'check', activeBg: 'bg-success-500 text-white', idleBg: 'bg-clay-100 text-success-700' },
    absent:  { icon: 'x-circle', activeBg: 'bg-danger-500 text-white', idleBg: 'bg-clay-100 text-danger-700' },
    late:    { icon: 'clock', activeBg: 'bg-warning-500 text-white', idleBg: 'bg-clay-100 text-warning-700' },
    excused: { icon: 'info', activeBg: 'bg-info-500 text-white', idleBg: 'bg-clay-100 text-info-700' },
};
const statusLabel = computed(() => {
    const m = {};
    for (const s of props.statuses) m[s.code] = s.label;
    return m;
});
</script>

<template>
    <PanelLayout>
        <PageHeader
            :title="`حضور و غیاب — ${classItem.name}`"
            :description="`${classItem.school?.name || ''}${classItem.grade ? ' · پایه ' + classItem.grade : ''}${classItem.academic_year ? ' · ' + classItem.academic_year : ''}`"
        >
            <template #icon><Icon name="check-circle" :size="22" /></template>
            <template #actions>
                <Link :href="`/classes/${classItem.id}/attendance/report`" class="clay-btn-secondary inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold">
                    <Icon name="pie-chart" :size="16" />
                    گزارش کلاس
                </Link>
                <Link href="/classes" class="clay-btn-ghost inline-flex items-center gap-1.5 px-3 py-2 text-sm">
                    <Icon name="arrow-right" :size="16" />
                    کلاس‌ها
                </Link>
            </template>
        </PageHeader>

        <!-- انتخاب تاریخ و جلسه -->
        <SectionCard class="mt-6" flat>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs text-slate-400 mb-1">جلسه‌ی انتخاب‌شده</div>
                    <div class="flex items-center gap-3">
                        <div class="clay-card-flat flex h-12 w-12 items-center justify-center text-brand-600">
                            <Icon name="calendar" :size="22" />
                        </div>
                        <div>
                            <div class="text-base font-bold text-slate-900">{{ formatJalali(sessionDate) }}</div>
                            <div class="text-xs text-slate-500" dir="ltr">{{ sessionDate }} · جلسه‌ی {{ toPersianDigits(slot) }}</div>
                        </div>
                    </div>
                </div>

                <!-- انتخاب جلسه -->
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">جلسه:</span>
                    <div class="flex gap-1.5">
                        <button
                            v-for="s in [1, 2, 3]"
                            :key="s"
                            type="button"
                            :class="[
                                'clay-chip h-8 w-8 text-sm font-semibold',
                                slot === s ? '!bg-brand-600 !text-white' : 'text-slate-600'
                            ]"
                            @click="changeSlot(s)"
                        >{{ toPersianDigits(s) }}</button>
                    </div>
                </div>
            </div>

            <!-- انتخاب سریع تاریخ -->
            <div class="mt-4 flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    :class="[
                        'clay-chip px-3 py-1.5 text-xs font-medium',
                        isToday ? '!bg-brand-600 !text-white' : 'text-slate-600'
                    ]"
                    @click="changeDate(recent[0])"
                >امروز</button>
                <template v-for="d in recent" :key="d">
                    <button
                        v-if="!isToday || d !== recent[0]"
                        type="button"
                        :class="[
                            'clay-chip relative px-3 py-1.5 text-xs font-medium',
                            sessionDate === d ? '!bg-brand-600 !text-white' : 'text-slate-600'
                        ]"
                        @click="changeDate(d)"
                    >
                        {{ formatJalaliShort(d) }}
                        <span
                            v-if="recordedDateSet.has(d) && sessionDate !== d"
                            class="absolute -top-0.5 -left-0.5 h-2 w-2 rounded-full bg-success-500"
                            title="ثبت شده"
                        />
                    </button>
                </template>
                <label class="clay-chip inline-flex cursor-pointer items-center gap-2 px-3 py-1.5 text-xs text-slate-600">
                    <Icon name="calendar" :size="14" />
                    <span>تاریخ دلخواه</span>
                    <input
                        type="date"
                        :value="sessionDate"
                        class="bg-transparent text-xs outline-none"
                        @change="changeDate($event.target.value)"
                    />
                </label>
            </div>
        </SectionCard>

        <!-- کارت‌های خلاصه -->
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard label="کل دانش‌آموزان" :value="toPersianDigits(liveSummary.total)" variant="neutral">
                <template #icon><Icon name="users" :size="22" /></template>
            </StatCard>
            <StatCard label="حاضر" :value="toPersianDigits(liveSummary.present)" variant="success">
                <template #icon><Icon name="check" :size="22" /></template>
            </StatCard>
            <StatCard label="غایب" :value="toPersianDigits(liveSummary.absent)" variant="danger">
                <template #icon><Icon name="x-circle" :size="22" /></template>
            </StatCard>
            <StatCard label="تأخیر" :value="toPersianDigits(liveSummary.late)" variant="warning">
                <template #icon><Icon name="clock" :size="22" /></template>
            </StatCard>
            <StatCard label="موجه" :value="toPersianDigits(liveSummary.excused)" variant="info">
                <template #icon><Icon name="info" :size="22" /></template>
            </StatCard>
        </div>

        <!-- کارت کد چک‌این دانش‌آموز -->
        <SectionCard class="mt-6" flat>
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="clay-card-flat flex h-12 w-12 items-center justify-center text-brand-600">
                        <Icon name="check-circle" :size="24" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">کد چک‌این دانش‌آموز</h2>
                        <p class="text-xs text-slate-500">دانش‌آموزان این کد را در پنل خود وارد می‌کنند تا حضورشان ثبت شود.</p>
                    </div>
                </div>
                <PrimaryButton :loading="generatingCode" :disabled="generatingCode" @click="generateCode">
                    <Icon name="refresh" :size="16" />
                    {{ activeCode && !codeExpired ? 'تولید کد جدید' : 'تولید کد جلسه' }}
                </PrimaryButton>
            </div>

            <!-- نمایش کد فعال -->
            <div v-if="activeCode && !codeExpired" class="mt-4 rounded-clay bg-gradient-to-l from-brand-50 to-clay-100 p-4">
                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                    <div class="text-center sm:text-right">
                        <div class="text-xs text-slate-500 mb-1">کد فعال این جلسه:</div>
                        <div class="font-mono text-3xl font-black tracking-[0.3em] text-brand-700" dir="ltr">
                            {{ codeDisplay }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-slate-500 mb-1">اعتبار باقی‌مانده:</div>
                        <div
                            class="font-mono text-2xl font-bold"
                            :class="codeExpiresIn < 60 ? 'text-danger-600' : 'text-success-600'"
                        >
                            {{ toPersianDigits(String(codeMinutes).padStart(2, '0')) }}:{{ toPersianDigits(String(codeSeconds).padStart(2, '0')) }}
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-center text-xs text-slate-400">
                    💡 این کد را روی تخته بنویسید یا در گروه کلاس اعلام کنید.
                </div>
            </div>

            <!-- کد منقضی شده -->
            <div v-else-if="activeCode && codeExpired" class="mt-4 rounded-clay bg-danger-50 p-4 text-center">
                <div class="text-sm text-danger-700">
                    <Icon name="alert-triangle" :size="16" /> کد منقضی شده است. برای چک‌این دانش‌آموزان، کد جدید بسازید.
                </div>
            </div>

            <!-- حالت اولیه -->
            <div v-else class="mt-4 rounded-clay bg-clay-100/60 p-4 text-center">
                <div class="text-sm text-slate-500">
                    هنوز کدی برای این جلسه ساخته نشده. روی «تولید کد جلسه» بزنید.
                </div>
            </div>
        </SectionCard>

        <!-- نوار ابزار: ست‌کردن گروهی + ذخیره -->
        <div class="mt-6 mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-slate-500">ست کردن همه:</span>
                <button type="button" class="clay-chip px-3 py-1.5 text-xs font-medium text-success-700" @click="markAll('present')">
                    <Icon name="check" :size="13" /> حاضر
                </button>
                <button type="button" class="clay-chip px-3 py-1.5 text-xs font-medium text-danger-700" @click="markAll('absent')">
                    <Icon name="x-circle" :size="13" /> غایب
                </button>
                <button type="button" class="clay-chip px-3 py-1.5 text-xs font-medium text-warning-700" @click="markAll('late')">
                    <Icon name="clock" :size="13" /> تأخیر
                </button>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="dirtyCount > 0" class="text-xs font-medium text-warning-700">
                    {{ toPersianDigits(dirtyCount) }} تغییر ذخیره‌نشده
                </span>
                <PrimaryButton :loading="saving" :disabled="!canRecord || saving" @click="save">
                    <Icon name="check-circle" :size="16" />
                    ثبت حضور و غیاب
                </PrimaryButton>
            </div>
        </div>

        <!-- لیست دانش‌آموزان -->
        <SectionCard flat>
            <div v-if="!hasRoster" class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="clay-card-flat flex h-14 w-14 items-center justify-center text-slate-400">
                    <Icon name="users" :size="28" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-700">دانش‌آموزی در این کلاس ثبت نشده است.</p>
                    <p class="mt-1 text-xs text-slate-500">ابتدا از صفحه‌ی اعضای کلاس، دانش‌آموزان را اضافه کنید.</p>
                </div>
                <Link :href="`/classes/${classItem.id}/roster`" class="clay-btn-ghost mt-2 px-3 py-1.5 text-xs">
                    رفتن به اعضای کلاس
                </Link>
            </div>

            <ul v-else class="divide-y divide-clay-200/70">
                <li
                    v-for="(item, idx) in roster"
                    :key="item.user.id"
                    class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="clay-card-flat flex h-9 w-9 shrink-0 items-center justify-center text-xs font-bold text-brand-700">
                            {{ toPersianDigits(idx + 1) }}
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-800">{{ item.user?.display_name || '—' }}</div>
                            <div class="text-xs font-mono text-slate-400" dir="ltr">{{ item.user?.mobile_e164 }}</div>
                        </div>
                        <StatusBadge
                            v-if="item.attendance"
                            :variant="item.attendance && localState[item.user.id]?.status === item.attendance.status ? 'neutral' : 'info'"
                        >
                            قبلاً: {{ statusLabel[item.attendance.status] || item.attendance.status }}
                        </StatusBadge>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="st in statuses"
                            :key="st.code"
                            type="button"
                            :class="[
                                'inline-flex h-9 items-center gap-1.5 rounded-clay-sm px-3 text-xs font-semibold transition',
                                localState[item.user.id]?.status === st.code
                                    ? statusBtnMeta[st.code].activeBg + ' shadow-clay-sm'
                                    : statusBtnMeta[st.code].idleBg
                            ]"
                            @click="setStatus(item.user.id, st.code)"
                        >
                            <Icon :name="statusBtnMeta[st.code].icon" :size="14" />
                            <span class="hidden sm:inline">{{ st.label }}</span>
                        </button>
                    </div>
                </li>
            </ul>
        </SectionCard>
    </PanelLayout>
</template>
