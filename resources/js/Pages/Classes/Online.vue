<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classItem: { type: Object, required: true },
    online: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    canJoin: { type: Boolean, default: false },
    providers: { type: Array, default: () => [] },
});

const page = usePage();

const form = useForm({
    provider: props.providers[0]?.code || '',
    title: '',
    record: false,
});

const editForm = useForm({
    title: props.online?.title || '',
    record: Boolean(props.online?.record),
});

function register() {
    form.post(`/classes/${props.classItem.id}/online`, { preserveScroll: true });
}
function start() {
    router.post(`/classes/${props.classItem.id}/online/start`, {}, { preserveScroll: true });
}
function end() {
    if (!confirm('پایان جلسه؟')) return;
    router.post(`/classes/${props.classItem.id}/online/end`, {}, { preserveScroll: true });
}
function saveEdit() {
    editForm.put(`/classes/${props.classItem.id}/online`, { preserveScroll: true });
}
function unregister() {
    if (!confirm('حذف کلاس آنلاین؟')) return;
    router.delete(`/classes/${props.classItem.id}/online`, { preserveScroll: true });
}

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
            <Link href="/classes" class="text-sm text-violet-700 hover:underline">← بازگشت به کلاس‌ها</Link>
            <h1 class="text-xl font-bold mt-2">کلاس آنلاین: {{ classItem.name }}</h1>
            <p class="text-sm text-slate-500">
                {{ classItem.school?.name }}
                <span v-if="classItem.grade"> · پایه {{ classItem.grade }}</span>
            </p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <!-- ثبت نشده -->
        <section v-if="!online" class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <template v-if="canManage">
                <h2 class="font-semibold">ثبت کلاس آنلاین</h2>
                <div v-if="!providers.length" class="text-sm text-rose-600">
                    هیچ ارائه‌دهنده‌ای توسط ادمین پیکربندی نشده است. ابتدا در «تنظیمات سیستم» یک ارائه‌دهنده را فعال کنید.
                </div>
                <form v-else class="space-y-3" @submit.prevent="register">
                    <div>
                        <label class="block text-xs text-slate-600 mb-1">ارائه‌دهنده</label>
                        <select v-model="form.provider" class="w-full sm:w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option v-for="p in providers" :key="p.code" :value="p.code">{{ p.title }}</option>
                        </select>
                        <p v-if="form.errors.provider" class="text-xs text-rose-600 mt-1">{{ form.errors.provider }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 mb-1">عنوان جلسه (اختیاری)</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                        <p v-if="form.errors.title" class="text-xs text-rose-600 mt-1">{{ form.errors.title }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.record" /> ضبط جلسه (BigBlueButton)
                    </label>
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm font-medium hover:bg-violet-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        ثبت کلاس آنلاین
                    </button>
                </form>
            </template>
            <div v-else class="text-sm text-slate-500">برای این کلاس هنوز کلاس آنلاینی ثبت نشده است.</div>
        </section>

        <!-- ثبت شده -->
        <section v-else class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="flex flex-wrap items-center gap-3 justify-between">
                    <div>
                        <div class="font-semibold">{{ online.title || classItem.name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ providerLabel(online.provider) }} · معلم: {{ classItem.teacher?.display_name || '—' }}
                        </div>
                    </div>
                    <span class="inline-block text-xs px-2 py-1 rounded-md border" :class="statusClass(online.status)">
                        {{ statusLabel(online.status) }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="canJoin || canManage"
                        :href="`/classes/${classItem.id}/online/join`"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                    >
                        ورود به جلسه
                    </a>
                    <button
                        v-if="canManage && online.status !== 'live'"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm hover:bg-violet-800"
                        @click="start"
                    >
                        شروع جلسه
                    </button>
                    <button
                        v-if="canManage && online.status === 'live'"
                        type="button"
                        class="px-4 py-2 rounded-xl border border-rose-200 text-rose-700 text-sm hover:bg-rose-50"
                        @click="end"
                    >
                        پایان جلسه
                    </button>
                    <a
                        v-if="online.recording_url"
                        :href="online.recording_url"
                        target="_blank"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-sm hover:bg-slate-50"
                    >
                        مشاهده ضبط
                    </a>
                </div>
            </div>

            <!-- ویرایش -->
            <form v-if="canManage" class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3" @submit.prevent="saveEdit">
                <h2 class="font-semibold text-sm">ویرایش</h2>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">عنوان</label>
                    <input v-model="editForm.title" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="editForm.record" :disabled="Boolean(online.provider_room_id)" />
                    ضبط جلسه (فقط پیش از شروع قابل تغییر است)
                </label>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm hover:bg-violet-800"
                        :disabled="editForm.processing"
                    >
                        ذخیره
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-rose-200 text-rose-700 text-sm hover:bg-rose-50"
                        @click="unregister"
                    >
                        حذف کلاس آنلاین
                    </button>
                </div>
            </form>
        </section>
    </PanelLayout>
</template>
