<script setup>
import { reactive } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    classItem: { type: Object, required: true },
    slots: { type: Array, default: () => [] },
    dayLabels: { type: Object, default: () => ({}) },
});

const page = usePage();

const form = useForm({
    slots: props.slots.length
        ? props.slots.map((s) => ({ ...s }))
        : [{ day_of_week: 0, start_time: '08:00', end_time: '09:00', room: '' }],
});

function addSlot() {
    form.slots.push({ day_of_week: 0, start_time: '08:00', end_time: '09:00', room: '' });
}

function removeSlot(i) {
    form.slots.splice(i, 1);
}

function submit() {
    form.post(`/classes/${props.classItem.id}/schedule`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <div class="mb-6">
            <Link :href="`/classes/${classItem.id}/roster`" class="text-sm text-violet-700 hover:underline">← بازگشت</Link>
            <h1 class="text-xl font-bold mt-2">برنامه کلاسی: {{ classItem.name }}</h1>
            <p class="text-sm text-slate-500">{{ classItem.school?.name }}</p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-amber-50 text-amber-900 text-sm px-4 py-3 border border-amber-100">
            {{ page.props.flash.error }}
        </div>
        <p v-if="form.errors.slots" class="mb-3 text-sm text-rose-600">{{ form.errors.slots }}</p>

        <form class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3" @submit.prevent="submit">
            <div
                v-for="(slot, i) in form.slots"
                :key="i"
                class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-end border-b border-slate-100 pb-3"
            >
                <div>
                    <label class="text-xs text-slate-500">روز</label>
                    <select v-model.number="slot.day_of_week" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm">
                        <option v-for="(label, d) in dayLabels" :key="d" :value="Number(d)">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">از</label>
                    <input v-model="slot.start_time" type="time" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" dir="ltr" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">تا</label>
                    <input v-model="slot.end_time" type="time" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" dir="ltr" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">اتاق</label>
                    <input v-model="slot.room" type="text" class="w-full rounded-xl border border-slate-200 px-2 py-2 text-sm" />
                </div>
                <button type="button" class="text-xs text-rose-600 py-2" @click="removeSlot(i)">حذف</button>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" class="px-3 py-2 rounded-xl border border-slate-200 text-sm" @click="addSlot">+ ردیف</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm" :disabled="form.processing">ذخیره برنامه</button>
            </div>
        </form>
    </PanelLayout>
</template>
