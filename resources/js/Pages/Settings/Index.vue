<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    providers: { type: Object, required: true },
    defaultProvider: { type: [String, null], default: null },
});

const page = usePage();

const form = useForm({
    bbb: {
        base_url: props.providers.bbb.base_url,
        secret: '',
        checksum_algo: props.providers.bbb.checksum_algo,
    },
    skyroom: {
        base_url: props.providers.skyroom.base_url,
        api_key: '',
    },
    default_provider: props.defaultProvider || '',
});

function submit() {
    form.put('/settings/providers', { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <div class="space-y-2 mb-6">
            <h1 class="text-xl font-bold">تنظیمات سیستم</h1>
            <p class="text-slate-500 text-sm">پیکربندی ارائه‌دهنده‌های کلاس آنلاین.</p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="mb-4 rounded-xl bg-rose-50 text-rose-800 text-sm px-4 py-3 border border-rose-100">
            {{ page.props.flash.error }}
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- BigBlueButton -->
            <section class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3">
                <h2 class="font-semibold">BigBlueButton</h2>
                <p class="text-xs text-slate-500">
                    سرور BBB که کلاس‌های آنلاین به آن وصل می‌شوند. آدرس و shared secret را از دستور
                    <code class="font-mono" dir="ltr">bbb-conf --secret</code> دریافت کنید.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-600 mb-1">آدرس سرور</label>
                        <input
                            v-model="form.bbb.base_url"
                            type="text"
                            placeholder="https://bbb.example.com"
                            dir="ltr"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                        />
                        <p v-if="form.errors['bbb.base_url']" class="text-xs text-rose-600 mt-1">{{ form.errors['bbb.base_url'] }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 mb-1">الگوریتم checksum</label>
                        <select v-model="form.bbb.checksum_algo" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="sha1">sha1</option>
                            <option value="sha256">sha256</option>
                            <option value="sha384">sha384</option>
                            <option value="sha512">sha512</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">
                        Shared Secret
                        <span v-if="providers.bbb.secret_set" class="text-emerald-600">(تنظیم‌شده — برای تغییر وارد کنید)</span>
                    </label>
                    <input
                        v-model="form.bbb.secret"
                        type="password"
                        placeholder="••••••••"
                        dir="ltr"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors['bbb.secret']" class="text-xs text-rose-600 mt-1">{{ form.errors['bbb.secret'] }}</p>
                </div>
            </section>

            <!-- Skyroom -->
            <section class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3">
                <h2 class="font-semibold">Skyroom</h2>
                <p class="text-xs text-slate-500">API Key از پنل Skyroom دریافت می‌شود (رشتهٔ ۵۰ کاراکتری).</p>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">آدرس پایه</label>
                    <input
                        v-model="form.skyroom.base_url"
                        type="text"
                        dir="ltr"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">
                        API Key
                        <span v-if="providers.skyroom.api_key_set" class="text-emerald-600">(تنظیم‌شده — برای تغییر وارد کنید)</span>
                    </label>
                    <input
                        v-model="form.skyroom.api_key"
                        type="password"
                        placeholder="••••••••"
                        dir="ltr"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    />
                    <p v-if="form.errors['skyroom.api_key']" class="text-xs text-rose-600 mt-1">{{ form.errors['skyroom.api_key'] }}</p>
                </div>
            </section>

            <!-- ارائه‌دهندهٔ پیش‌فرض -->
            <section class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3">
                <h2 class="font-semibold">ارائه‌دهندهٔ پیش‌فرض</h2>
                <p class="text-xs text-slate-500">پیش‌نهاد نمایش در فرم ثبت کلاس آنلاین.</p>
                <select v-model="form.default_provider" class="w-full sm:w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">— انتخاب نشده —</option>
                    <option value="bbb">BigBlueButton</option>
                    <option value="skyroom">Skyroom</option>
                </select>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-violet-700 text-white text-sm font-medium hover:bg-violet-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    ذخیره تنظیمات
                </button>
            </div>
        </form>
    </PanelLayout>
</template>