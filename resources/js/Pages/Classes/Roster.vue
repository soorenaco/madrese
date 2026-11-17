<script setup>
import { ref, watch } from 'vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classItem: { type: Object, required: true },
    enrollments: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ active: 0, invited: 0, ended: 0 }) },
    filters: { type: Object, default: () => ({ q: '', status: 'current' }) },
});

const page = usePage();
const q = ref(props.filters.q || '');
const status = ref(props.filters.status || 'current');

function applyFilters() {
    router.get(`/classes/${props.classItem.id}/roster`, {
        q: q.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}

let searchTimer = null;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch(status, applyFilters);

const studentForm = useForm({
    mobile: '',
    display_name: '',
});

const teacherForm = useForm({
    mobile: '',
    display_name: '',
});

function inviteStudent() {
    studentForm.post(`/classes/${props.classItem.id}/students`, {
        preserveScroll: true,
        onSuccess: () => studentForm.reset(),
    });
}

function assignTeacher() {
    teacherForm.post(`/classes/${props.classItem.id}/teacher`, {
        preserveScroll: true,
        onSuccess: () => teacherForm.reset(),
    });
}

function removeStudent(userId) {
    if (!confirm('خروج این دانش‌آموز از کلاس؟')) return;
    router.delete(`/classes/${props.classItem.id}/students/${userId}`, { preserveScroll: true });
}

function restoreStudent(userId) {
    router.post(`/classes/${props.classItem.id}/students/${userId}/restore`, {}, { preserveScroll: true });
}

function setStatus(userId, newStatus) {
    router.put(`/classes/${props.classItem.id}/students/${userId}/status`, {
        status: newStatus,
    }, { preserveScroll: true });
}

function statusLabel(s) {
    if (s === 'active') return 'فعال';
    if (s === 'invited') return 'دعوت‌شده';
    if (s === 'ended') return 'خارج‌شده';
    return s;
}
</script>

<template>
    <PanelLayout>
        <div class="mb-6">
            <Link href="/classes" class="text-sm text-violet-700 hover:underline">← بازگشت به کلاس‌ها</Link>
            <h1 class="text-xl font-bold mt-2">اعضای کلاس: {{ classItem.name }}</h1>
            <p class="text-sm text-slate-500">
                {{ classItem.school?.name }}
                <span v-if="classItem.grade"> · پایه {{ classItem.grade }}</span>
                <span v-if="classItem.academic_year"> · {{ classItem.academic_year }}</span>
            </p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <!-- معلم -->
        <section class="bg-white rounded-2xl border border-slate-200 p-5 mb-4 space-y-3">
            <h2 class="font-semibold">معلم کلاس</h2>
            <div v-if="classItem.teacher" class="text-sm text-slate-700">
                {{ classItem.teacher.display_name }}
                <span class="font-mono text-slate-500 text-xs mr-2" dir="ltr">{{ classItem.teacher.mobile_e164 }}</span>
            </div>
            <div v-else class="text-sm text-slate-400">هنوز معلمی تعیین نشده است.</div>

            <form class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2" @submit.prevent="assignTeacher">
                <div>
                    <input
                        v-model="teacherForm.mobile"
                        type="text"
                        placeholder="موبایل معلم (09...)"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        dir="ltr"
                    />
                    <p v-if="teacherForm.errors.mobile" class="text-xs text-rose-600 mt-1">{{ teacherForm.errors.mobile }}</p>
                </div>
                <input
                    v-model="teacherForm.display_name"
                    type="text"
                    placeholder="نام نمایشی (اختیاری)"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-violet-700 text-white text-sm py-2 hover:bg-violet-800 disabled:opacity-50"
                    :disabled="teacherForm.processing"
                >
                    ثبت / تغییر معلم
                </button>
            </form>
        </section>

        <!-- دعوت دانش‌آموز -->
        <section class="bg-white rounded-2xl border border-slate-200 p-5 mb-4 space-y-3">
            <h2 class="font-semibold">دعوت دانش‌آموز</h2>
            <p class="text-xs text-slate-500">با موبایل؛ همزمان عضو مدرسه و این کلاس می‌شود. در طول سال هم می‌توانید اضافه/خارج کنید.</p>

            <form class="grid grid-cols-1 sm:grid-cols-3 gap-3" @submit.prevent="inviteStudent">
                <div>
                    <input
                        v-model="studentForm.mobile"
                        type="text"
                        placeholder="موبایل (09...)"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        dir="ltr"
                        required
                    />
                    <p v-if="studentForm.errors.mobile" class="text-xs text-rose-600 mt-1">{{ studentForm.errors.mobile }}</p>
                </div>
                <input
                    v-model="studentForm.display_name"
                    type="text"
                    placeholder="نام (اختیاری)"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-violet-700 text-white text-sm py-2 hover:bg-violet-800 disabled:opacity-50"
                    :disabled="studentForm.processing"
                >
                    دعوت به کلاس
                </button>
            </form>
        </section>

        <!-- فیلتر -->
        <div class="mb-3 flex flex-col sm:flex-row gap-3 sm:items-center justify-between">
            <div class="flex flex-wrap gap-2 text-xs">
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border"
                    :class="status === 'current' ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200'"
                    @click="status = 'current'"
                >
                    جاری ({{ counts.active + counts.invited }})
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border"
                    :class="status === 'active' ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200'"
                    @click="status = 'active'"
                >
                    فعال ({{ counts.active }})
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border"
                    :class="status === 'invited' ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200'"
                    @click="status = 'invited'"
                >
                    دعوت‌شده ({{ counts.invited }})
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border"
                    :class="status === 'ended' ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200'"
                    @click="status = 'ended'"
                >
                    خارج‌شده ({{ counts.ended }})
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border"
                    :class="status === 'all' ? 'bg-violet-700 text-white border-violet-700' : 'bg-white border-slate-200'"
                    @click="status = 'all'"
                >
                    همه
                </button>
            </div>
            <input
                v-model="q"
                type="search"
                placeholder="جستجو نام یا موبایل..."
                class="w-full sm:w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm"
            />
        </div>

        <!-- لیست -->
        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm text-right">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">نام</th>
                        <th class="px-4 py-2 font-medium">موبایل</th>
                        <th class="px-4 py-2 font-medium">وضعیت</th>
                        <th class="px-4 py-2 font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in enrollments" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ row.user?.display_name || '—' }}</td>
                        <td class="px-4 py-2 font-mono text-xs" dir="ltr">{{ row.user?.mobile_e164 }}</td>
                        <td class="px-4 py-2">
                            <select
                                class="text-xs rounded-lg border border-slate-200 px-2 py-1"
                                :value="row.status"
                                @change="setStatus(row.user_id, $event.target.value)"
                            >
                                <option value="active">فعال</option>
                                <option value="invited">دعوت‌شده</option>
                                <option value="ended">خارج‌شده</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <button
                                v-if="row.status !== 'ended'"
                                type="button"
                                class="text-xs text-rose-600 hover:underline"
                                @click="removeStudent(row.user_id)"
                            >
                                خروج از کلاس
                            </button>
                            <button
                                v-else
                                type="button"
                                class="text-xs text-violet-700 hover:underline"
                                @click="restoreStudent(row.user_id)"
                            >
                                بازگشت به کلاس
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!enrollments.length">
                        <td colspan="4" class="px-4 py-8 text-center text-slate-400">موردی یافت نشد.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </PanelLayout>
</template>
