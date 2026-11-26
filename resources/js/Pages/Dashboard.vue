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

// داده‌های داشبورد — در حال حاضر از props پشتیبانی می‌کند و در صورت نبود، نمونه نمایش می‌دهد.
const stats = computed(() => page.props.dashboard?.stats ?? {
    schools: 0,
    classes: 0,
    users: 0,
    onlineClasses: 0,
});
const enrollmentTrend = computed(() => page.props.dashboard?.enrollmentTrend ?? [4, 6, 5, 8, 7, 10, 9, 12, 11, 14]);
const trendLabels = computed(() => page.props.dashboard?.trendLabels ?? ['ف۱','ف۲','ف۳','ف۴','ف۵','ف۶','ف۷','ف۸','ف۹','ف۱۰']);
const recentActivity = computed(() => page.props.dashboard?.recentActivity ?? []);
const onlineUpcoming = computed(() => page.props.dashboard?.onlineUpcoming ?? []);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'صبح بخیر';
    if (h < 18) return 'ظهر بخیر';
    return 'عصر بخیر';
});
const today = computed(() => new Date().toLocaleDateString('fa-IR', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}));

const quickActions = computed(() => {
    const list = [];
    if (can('class.view')) list.push({ label: 'کلاس جدید', href: '/classes/create', icon: 'plus', tone: 'primary' });
    if (hasRole('school_manager') || isAdmin.value) list.push({ label: 'افزودن معاون/معلم', href: '/users', icon: 'user-plus', tone: 'info' });
    if (can('online_class.view')) list.push({ label: 'کلاس آنلاین', href: '/online-classes', icon: 'video', tone: 'success' });
    if (isAdmin.value) list.push({ label: 'تنظیمات سیستم', href: '/settings', icon: 'settings', tone: 'warning' });
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
function timeAgo(ts) {
    if (!ts) return '';
    // ساده‌سازی: اگر فرمت نسبتاً جدید باشد، فقط نمایش می‌دهیم
    return ts;
}
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
                    <p class="mt-0.5 text-sm text-slate-500">به پنل مدیریت مدرسه خوش آمدید.</p>
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

        <!-- کارت‌های آماری -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-if="isAdmin || hasRole('school_manager')"
                label="مدارس"
                :value="stats.schools"
                icon="building"
                variant="primary"
                :trend="8"
                trend-label="نسبت به ماه قبل"
                :to="hasRole('school_manager') || isAdmin ? '/school' : undefined"
            >
                <template #icon><Icon name="building" :size="22" /></template>
            </StatCard>
            <StatCard
                label="کلاس‌ها"
                :value="stats.classes"
                variant="info"
                :trend="12"
                trend-label="نسبت به ماه قبل"
                to="/classes"
            >
                <template #icon><Icon name="layers" :size="22" /></template>
            </StatCard>
            <StatCard
                label="کاربران"
                :value="stats.users"
                variant="success"
                :trend="5"
                trend-label="نسبت به ماه قبل"
                to="/users"
            >
                <template #icon><Icon name="users" :size="22" /></template>
            </StatCard>
            <StatCard
                label="کلاس‌های آنلاین"
                :value="stats.onlineClasses"
                variant="warning"
                :trend="-3"
                trend-label="نسبت به هفته قبل"
                to="/online-classes"
            >
                <template #icon><Icon name="video" :size="22" /></template>
            </StatCard>
        </div>

        <!-- نمودار + میانبرها -->
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- نمودار روند ثبت‌نام -->
            <SectionCard
                class="lg:col-span-2"
                title="روند ثبت‌نام"
                description="تعداد ثبت‌نام‌های جدید در ۱۰ هفته گذشته"
            >
                <template #actions>
                    <StatusBadge variant="success">
                        <Icon name="trending-up" :size="12" />
                        +۲۴٪
                    </StatusBadge>
                </template>
                <div class="pt-2">
                    <MiniChart
                        :data="enrollmentTrend"
                        :labels="trendLabels"
                        :height="180"
                        type="area"
                        color="#7c3aed"
                    />
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
                            <p class="text-xs text-slate-400 mt-0.5">{{ timeAgo(act.time) }}</p>
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

            <SectionCard title="جلسات آنلاین پیش‌رو" description="برنامه‌ی کلاس‌های آنلاین بعدی">
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
                            <p class="text-xs text-slate-400">{{ sess.when }}</p>
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
                    <p class="text-sm text-slate-500">جلسه‌ی آنلاین پیش‌رویی وجود ندارد.</p>
                    <Link href="/online-classes" class="clay-btn-ghost mt-3 px-3 py-1.5 text-xs">
                        مشاهده همه
                    </Link>
                </div>
            </SectionCard>
        </div>
    </PanelLayout>
</template>
