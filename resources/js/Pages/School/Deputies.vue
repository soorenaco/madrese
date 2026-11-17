<script setup>
import { reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    school: { type: Object, required: true },
    managers: { type: Array, default: () => [] },
    limitKeys: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();

const labels = {
    can_invite_students: 'دعوت دانش‌آموز',
    can_manage_schedule: 'مدیریت برنامه',
    can_promote_class: 'ارتقای کلاس / سال',
    can_edit_school: 'ویرایش مدرسه',
    can_manage_deputies: 'مدیریت معاونان',
};

const addForm = reactive({
    mobile: '',
    display_name: '',
    processing: false,
    errors: {},
});

function addDeputy() {
    addForm.processing = true;
    addForm.errors = {};

    router.post(`/school/${props.school.id}/deputies`, {
        mobile: addForm.mobile,
        display_name: addForm.display_name || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.mobile = '';
            addForm.display_name = '';
            addForm.processing = false;
        },
        onError: (errors) => {
            addForm.errors = errors;
            addForm.processing = false;
        },
    });
}

function setPrimary(userId) {
    if (!confirm('آیا از تعیین مدیر اصلی مطمئن هستید؟')) return;
    router.post(`/school/${props.school.id}/primary-manager`, { user_id: userId }, { preserveScroll: true });
}

function saveLimits(manager) {
    const limits = {};
    for (const key of props.limitKeys) {
        limits[key] = !!(manager._limits && manager._limits[key]);
    }
    if (!manager._limits) {
        for (const key of props.limitKeys) {
            limits[key] = !!(manager.manager_limits && manager.manager_limits[key]);
        }
    }
    router.post(`/school/${props.school.id}/deputy-limits`, {
        user_id: manager.user_id,
        limits: manager._limits || limits,
    }, { preserveScroll: true });
}

function ensureLimits(manager) {
    if (!manager._limits) {
        manager._limits = {};
        for (const key of props.limitKeys) {
            manager._limits[key] = !!(manager.manager_limits && manager.manager_limits[key]);
        }
    }
    return manager._limits;
}

props.managers.forEach((m) => ensureLimits(m));
</script>

<template>
    <PanelLayout>
        <div class="mb-6">
            <Link href="/school" class="text-sm text-violet-700 hover:underline">← بازگشت</Link>
            <h1 class="text-xl font-bold mt-2">مدیران و معاونان: {{ school.name }}</h1>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <div v-if="canManage" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6">
            <h2 class="font-medium mb-3">افزودن معاون جدید</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">موبایل <span class="text-red-500">*</span></label>
                    <input v-model="addForm.mobile" type="text" placeholder="09123456789"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
                    <div v-if="addForm.errors.mobile" class="text-red-500 text-xs mt-1">{{ addForm.errors.mobile }}</div>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">نام (اختیاری)</label>
                    <input v-model="addForm.display_name" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>
            </div>
            <button type="button" @click="addDeputy" :disabled="addForm.processing"
                class="mt-3 bg-violet-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
                {{ addForm.processing ? 'در حال افزودن...' : 'افزودن معاون' }}
            </button>
        </div>

        <div class="space-y-4">
            <div v-for="m in managers" :key="m.user_id"
                class="bg-white rounded-2xl border border-slate-200 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                    <div>
                        <div class="font-medium">{{ m.display_name }}</div>
                        <div class="text-xs font-mono text-slate-500" dir="ltr">{{ m.mobile }}</div>
                        <span class="inline-block text-xs px-2 py-0.5 rounded-md border mt-1"
                            :class="m.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100'">
                            {{ m.status === 'active' ? 'فعال' : 'دعوت‌شده' }}
                        </span>
                    </div>
                    <div class="flex gap-2 items-center">
                        <span v-if="m.is_primary_manager"
                            class="text-xs px-2 py-1 rounded-md bg-violet-50 text-violet-700 border border-violet-100">
                            مدیر اصلی
                        </span>
                        <button v-if="canManage && !m.is_primary_manager" type="button"
                            class="text-xs px-3 py-1.5 rounded-lg border border-violet-200 text-violet-700"
                            @click="setPrimary(m.user_id)">
                            تعیین به‌عنوان مدیر اصلی
                        </button>
                    </div>
                </div>

                <div v-if="canManage && !m.is_primary_manager" class="border-t border-slate-100 pt-3">
                    <div class="text-xs text-slate-500 mb-2">محدودیت‌های معاون</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                        <label v-for="key in limitKeys" :key="key" class="flex items-center gap-2 text-sm">
                            <input v-model="m._limits[key]" type="checkbox" class="rounded" />
                            {{ labels[key] || key }}
                        </label>
                    </div>
                    <button type="button"
                        class="text-xs px-3 py-1.5 rounded-lg bg-violet-700 text-white"
                        @click="saveLimits(m)">
                        ذخیره محدودیت
                    </button>
                </div>
            </div>

            <div v-if="!managers.length" class="text-center text-slate-400 py-8">
                مدیریتی برای این مدرسه ثبت نشده است.
            </div>
        </div>
    </PanelLayout>
</template>