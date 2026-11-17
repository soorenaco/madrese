<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    school: { type: Object, default: null },
});

const isEdit = !!props.school;

const form = useForm({
    name: props.school?.name ?? '',
    code: props.school?.code ?? '',
    city: props.school?.city ?? '',
    address: props.school?.address ?? '',
    status: props.school?.status ?? 'active',
    phone: props.school?.phone ?? '',
    email: props.school?.email ?? '',
    website: props.school?.website ?? '',
    description: props.school?.description ?? '',
    logo: null,
    manager_mobile: '',
    manager_display_name: '',
});

function submit() {
    if (isEdit) {
        form.put(`/school/${props.school.id}`, { forceFormData: true });
    } else {
        form.post('/school', { forceFormData: true });
    }
}
</script>

<template>
    <PanelLayout>
        <div class="max-w-xl">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-bold">
                    {{ isEdit ? 'ویرایش مدرسه' : 'ساخت مدرسه' }}
                </h1>
                <Link href="/school" class="text-sm text-slate-500 hover:text-violet-700">بازگشت</Link>
            </div>

            <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-xl p-5 space-y-4">

                <!-- نام -->
                <div>
                    <label class="block text-sm mb-1">نام مدرسه <span class="text-red-500">*</span></label>
                    <input v-model="form.name" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                    <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                </div>

                <!-- کد -->
                <div>
                    <label class="block text-sm mb-1">کد مدرسه</label>
                    <input v-model="form.code" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                    <div v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</div>
                </div>

                <!-- شهر -->
                <div>
                    <label class="block text-sm mb-1">شهر</label>
                    <input v-model="form.city" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>

                <!-- آدرس -->
                <div>
                    <label class="block text-sm mb-1">آدرس</label>
                    <input v-model="form.address" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>

                <!-- تلفن -->
                <div>
                    <label class="block text-sm mb-1">تلفن</label>
                    <input v-model="form.phone" type="text"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>

                <!-- ایمیل -->
                <div>
                    <label class="block text-sm mb-1">ایمیل</label>
                    <input v-model="form.email" type="email"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>

                <!-- وب‌سایت -->
                <div>
                    <label class="block text-sm mb-1">وب‌سایت</label>
                    <input v-model="form.website" type="url"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                </div>

                <!-- توضیحات -->
                <div>
                    <label class="block text-sm mb-1">توضیحات</label>
                    <textarea v-model="form.description" rows="3"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
                </div>

                <!-- لوگو -->
                <div>
                    <label class="block text-sm mb-1">لوگو</label>
                    <div v-if="school?.logo" class="mb-2">
                        <img :src="school.logo" alt="لوگو" class="w-16 h-16 rounded-lg object-cover border" />
                    </div>
                    <input type="file" accept="image/*" @input="form.logo = $event.target.files[0]"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                    <div v-if="form.errors.logo" class="text-red-500 text-xs mt-1">{{ form.errors.logo }}</div>
                </div>

                <!-- وضعیت -->
                <div>
                    <label class="block text-sm mb-1">وضعیت <span class="text-red-500">*</span></label>
                    <select v-model="form.status"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="active">فعال</option>
                        <option value="suspended">تعلیق</option>
                    </select>
                </div>

                <!-- مدیر اصلی (فقط برای ساخت) -->
                <div v-if="!isEdit" class="border-t border-slate-200 pt-4 mt-4">
                    <h3 class="font-medium mb-3">مدیر اصلی مدرسه</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm mb-1">موبایل مدیر <span class="text-red-500">*</span></label>
                            <input v-model="form.manager_mobile" type="text" placeholder="09123456789"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" dir="ltr" />
                            <div v-if="form.errors.manager_mobile" class="text-red-500 text-xs mt-1">{{ form.errors.manager_mobile }}</div>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">نام مدیر (اختیاری)</label>
                            <input v-model="form.manager_display_name" type="text"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" />
                        </div>
                    </div>
                </div>

                <!-- دکمه‌ها -->
                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" :disabled="form.processing"
                        class="bg-violet-700 text-white px-4 py-2 rounded-lg text-sm disabled:opacity-50">
                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره' }}
                    </button>
                    <Link href="/school" class="text-sm text-slate-500 hover:text-violet-700">انصراف</Link>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>