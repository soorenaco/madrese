<script setup>
import { ref } from 'vue';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import TextInput from '../../Components/Ui/TextInput.vue';
import SelectInput from '../../Components/Ui/SelectInput.vue';
import FormField from '../../Components/Ui/FormField.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import SecondaryButton from '../../Components/Ui/SecondaryButton.vue';
import Icon from '../../Components/Icon.vue';
import { useToast } from '../../composables/useToast';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '', status: '' }) },
    roles: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
});

const toast = useToast();

const q = ref(props.filters.q || '');
const status = ref(props.filters.status || '');

let searchTimer = null;
function search() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/users', {
            q: q.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, replace: true, preserveScroll: true });
    }, 300);
}

function statusVariant(s) {
    return { active: 'success', invited: 'info', suspended: 'warning', blocked: 'danger', ended: 'neutral' }[s] || 'neutral';
}
function statusLabel(s) {
    return { active: 'فعال', invited: 'دعوت‌شده', suspended: 'معلق', blocked: 'مسدود', ended: 'پایان‌یافته' }[s] || s;
}

function setStatus(userId, newStatus) {
    router.put(`/users/${userId}/status`, { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => toast.success('وضعیت کاربر به‌روزرسانی شد.'),
        onError: () => toast.error('به‌روزرسانی وضعیت ناموفق بود.'),
    });
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
function closeAssign() {
    activeUserId.value = null;
}
function submitMembership() {
    if (!activeUserId.value) return;
    membershipForm.post(`/users/${activeUserId.value}/membership`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('عضویت با موفقیت ثبت شد.');
            closeAssign();
        },
        onError: () => toast.error('ثبت عضویت ناموفق بود.'),
    });
}

const rows = props.users.data || [];
</script>

<template>
    <PanelLayout>
        <PageHeader title="کاربران" description="مدیریت وضعیت و عضویت کاربران">
            <template #icon><Icon name="users" :size="22" /></template>
        </PageHeader>

        <!-- فیلترها -->
        <div class="mt-6 mb-4 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1 sm:max-w-xs">
                <TextInput
                    v-model="q"
                    type="search"
                    placeholder="جستجو نام یا موبایل..."
                    icon="search"
                    @input="search"
                />
            </div>
            <div class="sm:w-44">
                <SelectInput v-model="status" placeholder="همه وضعیت‌ها" @change="search">
                    <option value="active">فعال</option>
                    <option value="invited">دعوت‌شده</option>
                    <option value="blocked">مسدود</option>
                </SelectInput>
            </div>
        </div>

        <!-- لیست کاربران -->
        <div class="space-y-3">
            <SectionCard v-for="user in rows" :key="user.id" flat>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <div class="clay-card-flat flex h-10 w-10 items-center justify-center text-sm font-bold text-brand-700">
                                {{ (user.display_name || '؟').charAt(0) }}
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800">{{ user.display_name || 'بدون نام' }}</div>
                                <div class="text-xs font-mono text-slate-500" dir="ltr">{{ user.mobile_e164 }}</div>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template v-for="m in user.memberships || []" :key="m.id">
                                <StatusBadge
                                    v-for="role in m.roles || []"
                                    :key="role.id"
                                    variant="primary"
                                >{{ role.title }} · {{ m.school?.name || 'مدرسه' }}</StatusBadge>
                            </template>
                            <span v-if="!(user.memberships || []).length" class="text-xs text-slate-400">بدون عضویت</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="w-32">
                            <SelectInput
                                :model-value="user.status"
                                @change="setStatus(user.id, $event.target.value)"
                            >
                                <option value="active">فعال</option>
                                <option value="invited">دعوت‌شده</option>
                                <option value="blocked">مسدود</option>
                            </SelectInput>
                        </div>
                        <SecondaryButton @click="openAssign(user.id)">
                            <Icon name="user-plus" :size="15" />
                            عضویت / نقش
                        </SecondaryButton>
                    </div>
                </div>

                <!-- فرم تخصیص عضویت -->
                <form
                    v-if="activeUserId === user.id"
                    class="clay-card-inset mt-4 p-4"
                    @submit.prevent="submitMembership"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <FormField label="مدرسه" required>
                            <template #default="{ id, invalid }">
                                <SelectInput :id="id" :invalid="invalid" v-model="membershipForm.school_id" placeholder="مدرسه">
                                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </SelectInput>
                            </template>
                        </FormField>
                        <FormField label="نقش" required>
                            <template #default="{ id, invalid }">
                                <SelectInput :id="id" :invalid="invalid" v-model="membershipForm.role_id" placeholder="نقش">
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.title }}</option>
                                </SelectInput>
                            </template>
                        </FormField>
                        <FormField label="وضعیت عضویت">
                            <template #default="{ id }">
                                <SelectInput :id="id" v-model="membershipForm.status">
                                    <option value="active">فعال</option>
                                    <option value="invited">دعوت‌شده</option>
                                    <option value="suspended">معلق</option>
                                    <option value="ended">پایان‌یافته</option>
                                </SelectInput>
                            </template>
                        </FormField>
                        <div class="flex items-end gap-2">
                            <PrimaryButton :loading="membershipForm.processing" :disabled="membershipForm.processing" block>
                                ثبت
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="closeAssign">بستن</SecondaryButton>
                        </div>
                    </div>
                </form>
            </SectionCard>

            <div v-if="!rows.length" class="clay-card flex flex-col items-center justify-center gap-2 py-14 text-center">
                <Icon name="users" :size="32" class="text-slate-400" />
                <p class="text-sm text-slate-500">کاربری یافت نشد.</p>
            </div>
        </div>
    </PanelLayout>
</template>
