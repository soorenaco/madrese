<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '', status: '' }) },
    roles: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
});

const page = usePage();
const q = ref(props.filters.q || '');
const status = ref(props.filters.status || '');

function search() {
    router.get('/users', {
        q: q.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}

let searchTimer = null;
watch(q, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 300);
});
watch(status, search);

function setStatus(userId, newStatus) {
    router.put(`/users/${userId}/status`, { status: newStatus }, { preserveScroll: true });
}

const membershipForm = useForm({
    school_id: '',
    role_id: '',
    status: 'active',
});

const activeUserId = ref(null);

function openAssign(userId) {
    activeUserId.value = userId;
    membershipForm.reset();
    membershipForm.status = 'active';
}

function submitMembership() {
    if (!activeUserId.value) return;
    membershipForm.post(`/users/${activeUserId.value}/membership`, {
        preserveScroll: true,
        onSuccess: () => {
            activeUserId.value = null;
            membershipForm.reset();
        },
    });
}
</script>

<template>
    <PanelLayout>
        <div class="mb-6">
            <h1 class="text-xl font-bold">کاربران</h1>
            <p class="text-sm text-slate-500">مدیریت وضعیت و عضویت کاربران</p>
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm px-4 py-3 border border-emerald-100">
            {{ page.props.flash.success }}
        </div>

        <div class="mb-4 flex flex-col sm:flex-row gap-3">
            <input
                v-model="q"
                type="search"
                placeholder="جستجو نام یا موبایل..."
                class="w-full sm:w-64 rounded-xl border border-slate-200 px-3 py-2 text-sm"
            />
            <select v-model="status" class="w-full sm:w-40 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">همه وضعیت‌ها</option>
                <option value="active">فعال</option>
                <option value="invited">دعوت‌شده</option>
                <option value="blocked">مسدود</option>
            </select>
        </div>

        <div class="space-y-3">
            <div
                v-for="user in users.data"
                :key="user.id"
                class="bg-white rounded-2xl border border-slate-200 p-4"
            >
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ user.display_name || 'بدون نام' }}</div>
                        <div class="text-xs font-mono text-slate-500 mt-0.5" dir="ltr">{{ user.mobile_e164 }}</div>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <template v-for="m in user.memberships || []" :key="m.id">
                                <span
                                    v-for="role in m.roles || []"
                                    :key="role.id"
                                    class="text-[11px] px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-100"
                                >
                                    {{ role.title }} · {{ m.school?.name || 'مدرسه' }}
                                </span>
                            </template>
                            <span v-if="!(user.memberships || []).length" class="text-xs text-slate-400">بدون عضویت</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 items-center">
                        <select
                            class="rounded-lg border border-slate-200 text-xs px-2 py-1.5"
                            :value="user.status"
                            @change="setStatus(user.id, $event.target.value)"
                        >
                            <option value="active">فعال</option>
                            <option value="invited">دعوت‌شده</option>
                            <option value="blocked">مسدود</option>
                        </select>
                        <button
                            type="button"
                            class="text-xs px-3 py-1.5 rounded-lg border border-violet-200 text-violet-700 hover:bg-violet-50"
                            @click="openAssign(user.id)"
                        >
                            عضویت / نقش
                        </button>
                    </div>
                </div>

                <form
                    v-if="activeUserId === user.id"
                    class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-4 gap-3"
                    @submit.prevent="submitMembership"
                >
                    <select v-model="membershipForm.school_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="" disabled>مدرسه</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select v-model="membershipForm.role_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="" disabled>نقش</option>
                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.title }}</option>
                    </select>
                    <select v-model="membershipForm.status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="active">فعال</option>
                        <option value="invited">دعوت‌شده</option>
                        <option value="suspended">معلق</option>
                        <option value="ended">پایان‌یافته</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 rounded-xl bg-violet-700 text-white text-sm py-2" :disabled="membershipForm.processing">
                            ثبت
                        </button>
                        <button type="button" class="px-3 rounded-xl border border-slate-200 text-sm" @click="activeUserId = null">بستن</button>
                    </div>
                </form>
            </div>

            <div v-if="!users.data?.length" class="text-center text-slate-400 py-10">کاربری یافت نشد.</div>
        </div>
    </PanelLayout>
</template>