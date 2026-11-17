<script setup>
import PanelLayout from '../Layouts/PanelLayout.vue';
import { usePermission } from '../composables/usePermission';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const { can, hasRole, isAdmin, roles } = usePermission();

// پشتیبانی از prop قدیمی + shared auth
const user = computed(() => page.props.auth?.user ?? page.props.user ?? null);
</script>

<template>
    <PanelLayout>
        <div class="max-w-lg mx-auto text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-violet-700 text-white text-xl font-black mb-4">
                م
            </div>
            <h1 class="text-2xl font-bold mb-2">ورود موفق</h1>
            <p class="text-slate-600 mb-6">
                خوش آمدید
                <strong>{{ user?.display_name }}</strong>
            </p>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-right text-sm space-y-3 mb-6">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">موبایل</span>
                    <span class="font-mono" dir="ltr">{{ user?.mobile }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">وضعیت</span>
                    <span>{{ user?.status }}</span>
                </div>
                <div class="flex justify-between gap-4 items-start">
                    <span class="text-slate-500 shrink-0">نقش‌ها</span>
                    <div class="flex flex-wrap gap-1 justify-end">
                        <template v-if="roles.length">
                            <span
                                v-for="role in roles"
                                :key="role.code"
                                class="inline-block text-xs px-2 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-100"
                            >
                                {{ role.title }}
                            </span>
                        </template>
                        <span v-else class="text-slate-400">—</span>
                    </div>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">ادمین پلتفرم</span>
                    <span>{{ isAdmin ? 'بله' : 'خیر' }}</span>
                </div>
            </div>

            <!-- نمونه استفاده از composable (فقط UI) -->
            <div class="text-xs text-slate-400 space-y-1">
                <div v-if="hasRole('teacher')">نمایش ویژه معلم (hasRole)</div>
                <div v-if="can('class.view')">دسترسی مشاهده کلاس (can)</div>
            </div>
        </div>
    </PanelLayout>
</template>