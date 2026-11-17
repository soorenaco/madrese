<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classItem: { type: Object, default: null },
    schools: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
});

const isEdit = !!props.classItem;

const form = useForm({
    school_id: props.classItem?.school_id ?? '',
    name: props.classItem?.name ?? '',
    grade: props.classItem?.grade ?? '',
    academic_year: props.classItem?.academic_year ?? '',
    teacher_user_id: props.classItem?.teacher_user_id ?? '',
    status: props.classItem?.status ?? 'active',
});

function submit() {
    if (isEdit) {
        form.put(`/classes/${props.classItem.id}`);
    } else {
        form.post('/classes');
    }
}
</script>

<template>
    <PanelLayout>
        <div class="max-w-xl">
            <div class="mb-6">
                <Link href="/classes" class="text-sm text-violet-700 hover:underline">← بازگشت</Link>
                <h1 class="text-xl font-bold mt-2">{{ isEdit ? 'ویرایش کلاس' : 'کلاس جدید' }}</h1>
            </div>

            <form class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">مدرسه *</label>
                    <select v-model="form.school_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="" disabled>انتخاب مدرسه</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="form.errors.school_id" class="text-xs text-rose-600 mt-1">{{ form.errors.school_id }}</p>
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">نام کلاس *</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                    <p v-if="form.errors.name" class="text-xs text-rose-600 mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">پایه</label>
                        <input v-model="form.grade" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="مثلاً دهم" />
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">سال تحصیلی</label>
                        <input v-model="form.academic_year" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="1405-1406" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">معلم (اختیاری)</label>
                    <select v-model="form.teacher_user_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option v-for="t in teachers" :key="t.id" :value="t.id">
                            {{ t.display_name }} ({{ t.mobile_e164 }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-slate-600 mb-1">وضعیت *</label>
                    <select v-model="form.status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="active">فعال</option>
                        <option value="archived">بایگانی</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm font-medium hover:bg-violet-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ isEdit ? 'ذخیره تغییرات' : 'ایجاد کلاس' }}
                    </button>
                    <Link href="/classes" class="px-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-600">انصراف</Link>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>