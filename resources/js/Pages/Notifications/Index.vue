<script setup>
import { Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import Pagination from '../../Components/Ui/Pagination.vue';
import Icon from '../../Components/Icon.vue';
import { router, usePage } from '@inertiajs/vue3';
import { useJalali } from '../../composables/useJalali';

const props = defineProps({
    items: { type: Object, default: () => ({ data: [], links: [] }) },  // paginator
});

const page = usePage();
const { formatJalali, toPersianDigits } = useJalali();

function notifIcon(type) {
    return {
        'attendance.absent': 'x-circle',
        'attendance.late': 'clock',
        'general': 'bell',
    }[type] || 'bell';
}

function notifColor(type) {
    return {
        'attendance.absent': 'text-danger-600',
        'attendance.late': 'text-warning-600',
        'general': 'text-brand-600',
    }[type] || 'text-brand-600';
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

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}

function openNotif(n) {
    if (n.link) {
        router.visit(`/notifications/${n.id}`);
    }
}
</script>

<template>
    <PanelLayout>
        <PageHeader
            title="اعلان‌ها"
            description="همه‌ی اعلان‌های دریافت‌شده توسط شما"
        >
            <template #icon><Icon name="bell" :size="22" /></template>
            <template #actions>
                <PrimaryButton @click="markAllRead" class="!clay-btn-secondary">
                    <Icon name="check" :size="16" />
                    خواندن همه
                </PrimaryButton>
            </template>
        </PageHeader>

        <SectionCard class="mt-6" flat>
            <div v-if="!items.data.length" class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="clay-card-flat flex h-14 w-14 items-center justify-center text-slate-400">
                    <Icon name="bell" :size="28" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-700">اعلانی وجود ندارد.</p>
                    <p class="mt-1 text-xs text-slate-500">اعلان‌های جدید در اینجا نمایش داده می‌شوند.</p>
                </div>
            </div>

            <ul v-else class="divide-y divide-clay-200/70">
                <li
                    v-for="n in items.data"
                    :key="n.id"
                    class="flex items-start gap-3 py-4 transition-colors hover:bg-clay-100/40 cursor-pointer"
                    :class="!n.read_at ? 'bg-brand-50/40' : ''"
                    @click="openNotif(n)"
                >
                    <div
                        class="clay-card-flat flex h-10 w-10 shrink-0 items-center justify-center"
                        :class="notifColor(n.type)"
                    >
                        <Icon :name="notifIcon(n.type)" :size="18" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-bold text-slate-800">{{ n.title }}</span>
                            <span class="text-[10px] text-slate-400 shrink-0">{{ notifTime(n.created_at) }}</span>
                        </div>
                        <p v-if="n.body" class="mt-1 text-sm text-slate-600">{{ n.body }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span v-if="!n.read_at" class="inline-block h-2 w-2 rounded-full bg-brand-500"></span>
                            <span v-if="!n.read_at" class="text-[10px] font-medium text-brand-700">خوانده‌نشده</span>
                            <span v-if="n.link" class="text-[10px] text-brand-600">مشاهده ←</span>
                        </div>
                    </div>
                </li>
            </ul>
        </SectionCard>

        <Pagination v-if="items.links && items.links.length > 3" :links="items.links" class="mt-6" />
    </PanelLayout>
</template>
