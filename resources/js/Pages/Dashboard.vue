<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PanelLayout from '../Layouts/PanelLayout.vue';
import StatCard from '../Components/Ui/StatCard.vue';
import SectionCard from '../Components/Ui/SectionCard.vue';
import MiniChart from '../Components/Ui/MiniChart.vue';
import StatusBadge from '../Components/Ui/StatusBadge.vue';
import Icon from '../Components/Icon.vue';
import { usePermission } from '../composables/usePermission';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { can, hasRole, isAdmin, roles } = usePermission();

const user = computed(() => page.props.auth?.user ?? page.props.user ?? null);

// داده‌های داشبورد — از سرور به‌صورت واقعی ارسال می‌شود
const dashboard = computed(() => page.props.dashboard ?? {});
const stats = computed(() => dashboard.value.stats ?? []);
const enrollmentTrend = computed(() => dashboard.value.enrollmentTrend ?? []);
const trendLabels = computed(() => dashboard.value.trendLabels ?? []);
const recentActivity = computed(() => dashboard.value.recentActivity ?? []);
const onlineUpcoming = computed(() => dashboard.value.onlineUpcoming ?? []);
const roleKey = computed(() => dashboard.value.role ?? 'guest');

// عنوان و توضیح بر اساس نقش
const roleTitle = computed(() => ({
    admin: 'داشبورد مدیر سامانه',
    school_manager: 'داشبورد مدیر مدرسه',
    teacher: 'داشبورد معلم',
    parent: 'داشبورد والد',
    student: 'داشبورد دانش‌آموز',
    guest: 'داشبورد',
}[roleKey.value] ?? 'داشبورد'));

const roleDesc = computed(() => ({
    admin: 'نمای کلی کل سامانه و آمار مدارس',
    school_manager: 'نمای کلی مدارس و کلاس‌های تحت مدیریت شما',
    teacher: 'کلاس‌ها، جلسات و حضور و غیاب امروز شما',
    parent: 'وضعیت فرزندان و غیبت‌های اخیر',
    student: 'برنامه، حضور و غیاب و جلسات امروز شما',
    guest: 'به پنل خوش آمدید',
}[roleKey.value] ?? ''));

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'صبح بخیر';
    if (h < 18) return 'ظهر بخیر';
    return 'عصر بخیر';
});
const today = computed(() => new Date().toLocaleDateString('fa-IR', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}));

// میانبرهای سریع بر اساس نقش
const quickActions = computed(() => {
    const list = [];
    if (roleKey.value === 'student') {
        list.push({ label: 'ثبت حضور', href: '/student/check-in', icon: 'check-circle', tone: 'primary' });
        list.push({ label: 'برنامه هفتگی', href: '/student/schedule', icon: 'calendar', tone: 'info' });
        list.push({ label: 'کلاس آنلاین', href: '/online-classes', icon: 'video', tone: 'success' });
        list.push({ label: 'اعلان‌ها', href: '/notifications', icon: 'bell', tone: 'warning' });
    } else if (roleKey.value === 'parent') {
        list.push({ label: 'داشبورد فرزند', href: '/parent/dashboard', icon: 'users', tone: 'primary' });
        list.push({ label: 'اعلان‌ها', href: '/notifications', icon: 'bell', tone: 'info' });
    } else if (roleKey.value === 'teacher') {
        list.push({ label: 'کلاس‌ها', href: '/classes', icon: 'class', tone: 'primary' });
        list.push({ label: 'کلاس آنلاین', href: '/online-classes', icon: 'video', tone: 'success' });
        list.push({ label: 'اعلان‌ها', href: '/notifications', icon: 'bell', tone: 'warning' });
    } else if (roleKey.value === 'school_manager' || roleKey.value === 'admin') {
        if (can('class.view')) list.push({ label: 'کلاس جدید', href: '/classes/create', icon: 'plus', tone: 'primary' });
        list.push({ label: 'کاربران', href: '/users', icon: 'user-plus', tone: 'info' });
        list.push({ label: 'کلاس آنلاین', href: '/online-classes', icon: 'video', tone: 'success' });
        if (isAdmin.value) list.push({ label: 'تنظیمات', href: '/settings', icon: 'settings', tone: 'warning' });
    }
    return list;
});

function activityIcon(type) {
    return {
        class_created: 'class',
        user_invited: 'user-plus',
        online_started: 'video',
        school_updated: 'building',
        enrollment: 'users',
    }[type] || 'activity';
}

// برچسب فارسی روند کلی (با مقایسه هفته‌ی اخیر و هفته‌ی قبل)
const trendSummary = computed(() => {
    const t = enrollmentTrend.value;
    if (! t.length || t.length < 2) return null;
    const last = t[t.length - 1];
    const prev = t[t.length - 2];
    if (prev === 0) return { delta: last > 0 ? '+' + last : '۰', label: 'ثبت‌نام جدید در این هفته' };
    const pct = Math.round(((last - prev) / prev) * 100);
    return {
        delta: (pct >= 0 ? '+' : '') + pct + '٪',
        label: 'نسبت به هفته قبل',
        positive: pct >= 0,
    };
});
</script>

<template>
    <PanelLayout>
        <!-- خوش‌آمد + تاریخ -->
        <div class="clay-card mb-6 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="clay-btn-primary flex h-14 w-14 shrink-0 items-center justify-center text-2xl font-black !p-0">
                    {{ (user?.display_name || 'م').charAt(0) }}
                </div>
                <div>
                    <div class="text-xs text-slate-400">{{ today }}</div>
                    <h1 class="text-xl font-bold text-slate-900">{{ greeting }}، {{ user?.display_name || 'کاربر' }} 👋</h1>
                    <p class="mt-0.5 text-sm text-slate-500">{{ roleTitle }} — {{ roleDesc }}</p>
                </div>
            </div>
            <div v-if="roles.length" class="flex flex-wrap gap-1.5">
                <StatusBadge
                    v-for="role in roles"
                    :key="role.code"
                    variant="primary"
                >{{ role.title }}</StatusBadge>
            </div>
        </div>

        <!-- کارت‌های آماری (پویا بر اساس نقش) -->
        <div v-if="stats.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="stat in stats"
                :key="stat.key"
                :label="stat.label"
                :value="stat.value"
                :variant="stat.variant || 'neutral'"
                :hint="stat.hint"
                :to="stat.to"
            >
                <template #icon>
                    <Icon :name="stat.icon || 'activity'" :size="22" />
                </template>
            </StatCard>
        </div>

        <!-- نمودار + میانبرها (فقط برای ادمین/مدیر که روند ثبت‌نام دارند) -->
        <div v-if="roleKey === 'admin' || roleKey === 'school_manager'" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- نمودار روند ثبت‌نام -->
            <SectionCard
                class="lg:col-span-2"
                title="روند ثبت‌نام"
                description="تعداد ثبت‌نام‌های جدید در ۱۰ هفته گذشته"
            >
                <template v-if="trendSummary" #actions>
                    <StatusBadge :variant="trendSummary.positive ? 'success' : 'danger'">
                        <Icon :name="trendSummary.positive ? 'trending-up' : 'trending-down'" :size="12" />
                        {{ trendSummary.delta }}
                    </StatusBadge>
                </template>
                <div class="pt-2">
                    <MiniChart
                        v-if="enrollmentTrend.length"
                        :data="enrollmentTrend"
                        :labels="trendLabels"
                        :height="180"
                        type="area"
                        color="#7c3aed"
                    />
                    <div v-else class="flex items-center justify-center py-12 text-sm text-slate-400">
                        هنوز داده‌ای ثبت نشده است.
                    </div>
                </div>
            </SectionCard>

            <!-- میانبرهای سریع -->
            <SectionCard title="میانبرهای سریع" description="دسترسی پرکاربرد">
                <div class="grid grid-cols-2 gap-3">
                    <Link
                        v-for="action in quickActions"
                        :key="action.href"
                        :href="action.href"
                        class="clay-card-flat flex flex-col items-center gap-2 p-4 text-center transition-transform hover:-translate-y-0.5"
                    >
                        <span
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-clay-sm',
                                action.tone === 'primary' && 'bg-brand-100 text-brand-700',
                                action.tone === 'info' && 'bg-info-50 text-info-700',
                                action.tone === 'success' && 'bg-success-50 text-success-700',
                                action.tone === 'warning' && 'bg-warning-50 text-warning-700',
                            ]"
                        >
                            <Icon :name="action.icon" :size="20" />
                        </span>
                        <span class="text-xs font-medium text-slate-600">{{ action.label }}</span>
                    </Link>
                </div>
            </SectionCard>
        </div>

        <!-- میانبرهای سریع برای سایر نقش‌ها -->
        <div v-else-if="quickActions.length" class="mt-6">
            <SectionCard title="میانبرهای سریع" description="دسترسی پرکاربرد">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Link
                        v-for="action in quickActions"
                        :key="action.href"
                        :href="action.href"
                        class="clay-card-flat flex flex-col items-center gap-2 p-4 text-center transition-transform hover:-translate-y-0.5"
                    >
                        <span
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-clay-sm',
                                action.tone === 'primary' && 'bg-brand-100 text-brand-700',
                                action.tone === 'info' && 'bg-info-50 text-info-700',
                                action.tone === 'success' && 'bg-success-50 text-success-700',
                                action.tone === 'warning' && 'bg-warning-50 text-warning-700',
                            ]"
                        >
                            <Icon :name="action.icon" :size="20" />
                        </span>
                        <span class="text-xs font-medium text-slate-600">{{ action.label }}</span>
                    </Link>
                </div>
            </SectionCard>
        </div>

        <!-- فعالیت‌های اخیر + جلسات آنلاین پیش‌رو -->
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <SectionCard title="فعالیت‌های اخیر" description="رویدادهای اخیر سامانه">
                <ul v-if="recentActivity.length" class="space-y-3">
                    <li
                        v-for="(act, i) in recentActivity"
                        :key="i"
                        class="flex items-start gap-3"
                    >
                        <span class="clay-card-flat flex h-9 w-9 shrink-0 items-center justify-center text-brand-600">
                            <Icon :name="activityIcon(act.type)" :size="16" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-slate-700">{{ act.message }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ act.time }}</p>
                        </div>
                    </li>
                </ul>
                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="clay-card-flat mb-3 flex h-12 w-12 items-center justify-center text-slate-400">
                        <Icon name="activity" :size="22" />
                    </div>
                    <p class="text-sm text-slate-500">هنوز فعالیتی ثبت نشده است.</p>
                </div>
            </SectionCard>

            <SectionCard title="کلاس‌های آنلاین" description="کلاس‌های فعال یا اخیراً برگزارشده">
                <ul v-if="onlineUpcoming.length" class="space-y-3">
                    <li
                        v-for="(sess, i) in onlineUpcoming"
                        :key="i"
                        class="clay-card-flat flex items-center gap-3 p-3"
                    >
                        <span class="clay-card-inset flex h-11 w-11 shrink-0 flex-col items-center justify-center text-brand-700">
                            <Icon name="clock" :size="16" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">{{ sess.title }}</p>
                            <p class="text-xs text-slate-400" dir="ltr">{{ sess.when }}</p>
                        </div>
                        <Link
                            :href="sess.href || '/online-classes'"
                            class="clay-btn-ghost px-2.5 py-1.5 text-xs"
                        >
                            ورود
                        </Link>
                    </li>
                </ul>
                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="clay-card-flat mb-3 flex h-12 w-12 items-center justify-center text-slate-400">
                        <Icon name="calendar" :size="22" />
                    </div>
                    <p class="text-sm text-slate-500">هنوز کلاس آنلاینی برگزار نشده است.</p>
                    <Link href="/online-classes" class="clay-btn-ghost mt-3 px-3 py-1.5 text-xs">
                        مشاهده همه
                    </Link>
                </div>
            </SectionCard>
        </div>
    </PanelLayout>
</template>
