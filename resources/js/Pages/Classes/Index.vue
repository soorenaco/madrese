<script setup>
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classes: { type: Object, required: true },
    schools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', school_id: '' }) },
});

const page = usePage();
const q = ref(props.filters.q || '');
const schoolId = ref(props.filters.school_id || '');

function search() {
    router.get('/classes', {
        q: q.value || undefined,
        school_id: schoolId.value || undefined,
    }, { preserveState: true, replace: true });
}

let searchTimer = null;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 300);
});
watch(schoolId, search);

function destroyClass(id) {
    if (!confirm('حذف این کلاس؟')) return;
    router.delete(`/classes/${id}`);
}
</script>

<template>
    <PanelLayout>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-bold">کلاس‌ها</h1>
                <p class="text-sm text-slate-500">مدیریت کلاس‌های مدارس</p>
            </div>
            <Link
                href="/classes/create"
                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-violet-700 text-white text-sm font-medium hover:bg-violet-800"
            >
                کلاس جدید
            </Link>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>

        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <input
                v-model="q"
                type="search"
                placeholder="جستجو نام یا پایه..."
                class="w-full sm:w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm"
            />
            <select v-model="schoolId" class="w-full sm:w-56 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">همه مدارس</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">نام کلاس</th>
                        <th class="px-4 py-3 font-medium hidden sm:table-cell">مدرسه</th>
                        <th class="px-4 py-3 font-medium hidden md:table-cell">پایه</th>
                        <th class="px-4 py-3 font-medium hidden md:table-cell">سال</th>
                        <th class="px-4 py-3 font-medium">وضعیت</th>
                        <th class="px-4 py-3 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in classes.data" :key="item.id" class="border-t border-slate-100">
                        <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                        <td class="px-4 py-3 hidden sm:table-cell">{{ item.school?.name || '—' }}</td>
                        <td class="px-4 py-3 hidden md:table-cell">{{ item.grade || '—' }}</td>
                        <td class="px-4 py-3 hidden md:table-cell">{{ item.academic_year || '—' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-block text-xs px-2 py-0.5 rounded-md border"
                                :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-200'"
                            >
                                {{ item.status === 'active' ? 'فعال' : 'بایگانی' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2 justify-end">
                                <Link :href="`/classes/${item.id}/roster`" class="text-violet-700 hover:underline text-xs">اعضا</Link>
                                <Link :href="`/classes/${item.id}/schedule`" class="text-violet-700 hover:underline text-xs">برنامه</Link>
                                <Link :href="`/classes/${item.id}/promote`" class="text-violet-700 hover:underline text-xs">سال تحصیلی</Link>
                                <Link :href="`/classes/${item.id}/edit`" class="text-violet-700 hover:underline text-xs">ویرایش</Link>
                                <button type="button" class="text-rose-600 hover:underline text-xs" @click="destroyClass(item.id)">حذف</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!classes.data?.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">کلاسی ثبت نشده است.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PanelLayout>
</template>
