<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classItem: { type: Object, required: true },
});

const form = useForm({
    academic_year: props.classItem.academic_year || '',
    grade: props.classItem.grade || '',
    mode: 'update',
    carry_students: true,
    teacher_mobile: '',
    teacher_name: '',
});

function submit() {
    form.post(`/classes/${props.classItem.id}/promote`);
}
</script>

<template>
    <PanelLayout>
        <div class="max-w-xl">
            <Link :href="`/classes/${classItem.id}/roster`" class="text-sm text-violet-700 hover:underline">← بازگشت</Link>
            <h1 class="text-xl font-bold mt-2">سال تحصیلی / ارتقای کلاس</h1>
            <p class="text-sm text-slate-500 mb-4">{{ classItem.name }} · {{ classItem.school?.name }}</p>

            <form class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-slate-600 mb-1">سال تحصیلی جدید *</label>
                    <input v-model="form.academic_year" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="1406-1407" required />
                    <p v-if="form.errors.academic_year" class="text-xs text-rose-600 mt-1">{{ form.errors.academic_year }}</p>
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">پایه</label>
                    <input v-model="form.grade" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="یازدهم" />
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">حالت</label>
                    <select v-model="form.mode" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="update">به‌روزرسانی همین کلاس</option>
                        <option value="clone">کلاس جدید + بایگانی قبلی</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.carry_students" type="checkbox" class="rounded" />
                    انتقال دانش‌آموزان فعال به کلاس جدید (فقط در حالت کلاس جدید)
                </label>
                <div class="border-t border-slate-100 pt-3 space-y-3">
                    <div class="text-sm font-medium">معلم جدید (اختیاری)</div>
                    <input v-model="form.teacher_mobile" type="text" dir="ltr" placeholder="09..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                    <input v-model="form.teacher_name" type="text" placeholder="نام معلم" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm" :disabled="form.processing">اعمال</button>
            </form>
        </div>
    </PanelLayout>
</template>
