<script setup>
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    schools: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '' }) },
    canCreate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const page = usePage();
const q = ref(props.filters.q || '');

let searchTimer = null;
watch(q, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/school', { q: value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

function destroySchool(id) {
    if (!confirm('حذف این مدرسه؟')) return;
    router.delete(`/school/${id}`);
}
</script>

<template>
    <PanelLayout>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-bold">مدارس</h1>
                <p class="text-sm text-slate-500">مدیریت مدارس ثبت‌شده در سامانه</p>
            </div>
            <Link
                v-if="canCreate"
                href="/school/create"
                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-violet-700 text-white text-sm font-medium hover:bg-violet-800"
            >
                مدرسه جدید
            </Link>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <div class="mb-4">
            <input
                v-model="q"
                type="search"
                placeholder="جستجو نام، کد یا شهر..."
                class="w-full sm:w-80 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-200"
            />
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">لوگو</th>
                        <th class="px-4 py-3 font-medium">نام</th>
                        <th class="px-4 py-3 font-medium hidden sm:table-cell">کد</th>
                        <th class="px-4 py-3 font-medium hidden md:table-cell">شهر</th>
                        <th class="px-4 py-3 font-medium hidden lg:table-cell">تلفن</th>
                        <th class="px-4 py-3 font-medium">وضعیت</th>
                        <th class="px-4 py-3 font-medium hidden sm:table-cell">کلاس / عضو</th>
                        <th class="px-4 py-3 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="school in schools.data" :key="school.id" class="border-t border-slate-100">
                        <!-- لوگو -->
                        <td class="px-4 py-3">
                            <img v-if="school.logo" :src="school.logo" :alt="school.name"
                                class="w-9 h-9 rounded-lg object-cover border border-slate-100" />
                            <div v-else
                                class="w-9 h-9 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center text-xs font-bold">
                                {{ school.name?.charAt(0) }}
                            </div>
                        </td>

                        <!-- نام -->
                        <td class="px-4 py-3 font-medium">{{ school.name }}</td>

                        <!-- کد -->
                        <td class="px-4 py-3 hidden sm:table-cell font-mono text-xs" dir="ltr">{{ school.code || '—' }}</td>

                        <!-- شهر -->
                        <td class="px-4 py-3 hidden md:table-cell">{{ school.city || '—' }}</td>

                        <!-- تلفن -->
                        <td class="px-4 py-3 hidden lg:table-cell font-mono text-xs" dir="ltr">{{ school.phone || '—' }}</td>

                        <!-- وضعیت -->
                        <td class="px-4 py-3">
                            <span
                                class="inline-block text-xs px-2 py-0.5 rounded-md border"
                                :class="school.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100'"
                            >
                                {{ school.status === 'active' ? 'فعال' : 'معلق' }}
                            </span>
                        </td>

                        <!-- آمار -->
                        <td class="px-4 py-3 hidden sm:table-cell text-slate-500">
                            {{ school.classes_count ?? 0 }} / {{ school.memberships_count ?? 0 }}
                        </td>

                        <!-- عملیات -->
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2 justify-end">
                                <Link :href="`/school/${school.id}/deputies`" class="text-violet-700 hover:underline text-xs">مدیران</Link>
                                <Link :href="`/school/${school.id}/edit`" class="text-violet-700 hover:underline text-xs">ویرایش</Link>
                                <button v-if="canDelete" type="button" class="text-rose-600 hover:underline text-xs" @click="destroySchool(school.id)">حذف</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!schools.data?.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">مدرسه‌ای ثبت نشده است.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PanelLayout>
</template>