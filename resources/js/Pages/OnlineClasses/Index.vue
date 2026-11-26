<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import { usePermission } from '../../composables/usePermission';

const props = defineProps({
    onlineClasses: { type: Object, required: true },
});

const page = usePage();
const { isAdmin, hasAnyRole } = usePermission();

const canManageAny = computed(() => isAdmin.value || hasAnyRole('teacher', 'school_manager'));

function statusLabel(s) {
    return ({ idle: 'آماده', live: 'در حال برگزاری', ended: 'پایان‌یافته', cancelled: 'لغوشده' })[s] || s;
}
function statusClass(s) {
    return ({
        idle: 'bg-slate-50 text-slate-600 border-slate-200',
        live: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        ended: 'bg-slate-100 text-slate-500 border-slate-200',
        cancelled: 'bg-rose-50 text-rose-700 border-rose-100',
    })[s] || '';
}
function providerLabel(p) {
    return p === 'bbb' ? 'BigBlueButton' : 'Skyroom';
}
</script>

<template>
    <PanelLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold">کلاس‌های آنلاین</h1>
            <p class="text-sm text-slate-500">ورود به جلسه‌های آنلاین کلاس‌های شما.</p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">کلاس</th>
                            <th class="px-4 py-3 font-medium hidden sm:table-cell">مدرسه</th>
                            <th class="px-4 py-3 font-medium hidden md:table-cell">ارائه‌دهنده</th>
                            <th class="px-4 py-3 font-medium">وضعیت</th>
                            <th class="px-4 py-3 font-medium">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in onlineClasses.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">{{ row.school_class?.name || '—' }}</td>
                            <td class="px-4 py-3 hidden sm:table-cell">{{ row.school_class?.school?.name || '—' }}</td>
                            <td class="px-4 py-3 hidden md:table-cell">{{ providerLabel(row.provider) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block text-xs px-2 py-0.5 rounded-md border" :class="statusClass(row.status)">
                                    {{ statusLabel(row.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 justify-end">
                                    <a
                                        :href="`/classes/${row.school_class_id}/online/join`"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium"
                                        :class="row.status === 'live' || canManageAny
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                            : 'bg-slate-100 text-slate-400 pointer-events-none'"
                                    >
                                        ورود
                                    </a>
                                    <Link
                                        v-if="canManageAny"
                                        :href="`/classes/${row.school_class_id}/online`"
                                        class="text-violet-700 hover:underline text-xs"
                                    >
                                        مدیریت
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!onlineClasses.data?.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">کلاس آنلاینی برای شما ثبت نشده است.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- پیجینیشن -->
        <div v-if="onlineClasses.links?.length > 3" class="mt-4 flex flex-wrap gap-1 justify-center text-xs">
            <Link
                v-for="(link, i) in onlineClasses.links"
                :key="i"
                :href="link.url || '#'"
                v-html="link.label"
                class="px-3 py-1.5 rounded-lg border" :class="[ link.active ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200', !link.url ? 'pointer-events-none text-slate-300' : '', ]"  
                />
        </div>
    </PanelLayout>
</template>
