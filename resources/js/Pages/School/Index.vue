<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import DataTable from '../../Components/Ui/DataTable.vue';
import Pagination from '../../Components/Ui/Pagination.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import TextInput from '../../Components/Ui/TextInput.vue';
import Icon from '../../Components/Icon.vue';
import ConfirmDialog from '../../Components/Ui/ConfirmDialog.vue';
import { useToast } from '../../composables/useToast';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    schools: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '' }) },
    canCreate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const toast = useToast();

const q = ref(props.filters.q || '');
let searchTimer = null;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/school', { q: q.value || undefined }, { preserveState: true, replace: true, preserveScroll: true });
    }, 300);
}

// دیالوگ تأیید حذف
const confirmState = ref({ show: false, id: null, name: '', loading: false });
function askDelete(school) {
    confirmState.value = { show: true, id: school.id, name: school.name, loading: false };
}
function doDelete() {
    confirmState.value.loading = true;
    router.delete(`/school/${confirmState.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmState.value.show = false;
            confirmState.value.loading = false;
        },
        onSuccess: () => toast.success('مدرسه حذف شد.'),
        onError: () => toast.error('حذف مدرسه با خطا مواجه شد.'),
    });
}

const columns = [
    { key: 'logo', label: 'لوگو', align: 'center' },
    { key: 'name', label: 'نام' },
    { key: 'code', label: 'کد' },
    { key: 'city', label: 'شهر' },
    { key: 'phone', label: 'تلفن' },
    { key: 'status', label: 'وضعیت', align: 'center' },
    { key: 'stats', label: 'کلاس / عضو', align: 'center' },
    { key: 'actions', label: 'عملیات', align: 'center' },
];
</script>

<template>
    <PanelLayout>
        <PageHeader
            title="مدارس"
            description="مدیریت مدارس ثبت‌شده در سامانه"
        >
            <template #icon><Icon name="building" :size="22" /></template>
            <template #actions>
                <PrimaryButton v-if="canCreate" @click="$inertia.visit('/school/create')">
                    <Icon name="plus" :size="16" />
                    مدرسه جدید
                </PrimaryButton>
            </template>
        </PageHeader>

        <div class="mt-6 mb-4">
            <div class="max-w-sm">
                <TextInput
                    v-model="q"
                    type="search"
                    placeholder="جستجو نام، کد یا شهر..."
                    icon="search"
                    @input="onSearch"
                />
            </div>
        </div>

        <DataTable
            :columns="columns"
            :rows="schools.data || []"
            empty-title="مدرسه‌ای ثبت نشده است"
            empty-description="برای شروع، اولین مدرسه را ثبت کنید."
            row-key="id"
        >
            <template #cell-logo="{ row }">
                <img v-if="row.logo" :src="row.logo" :alt="row.name"
                    class="h-9 w-9 rounded-clay-sm object-cover" />
                <div v-else class="clay-card-flat mx-auto flex h-9 w-9 items-center justify-center text-xs font-bold text-brand-700">
                    {{ row.name?.charAt(0) }}
                </div>
            </template>
            <template #cell-name="{ row }">
                <span class="font-medium text-slate-800">{{ row.name }}</span>
            </template>
            <template #cell-code="{ row }">
                <span class="font-mono text-xs text-slate-500" dir="ltr">{{ row.code || '—' }}</span>
            </template>
            <template #cell-city="{ row }">{{ row.city || '—' }}</template>
            <template #cell-phone="{ row }">
                <span class="font-mono text-xs text-slate-500" dir="ltr">{{ row.phone || '—' }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :variant="row.status === 'active' ? 'success' : 'warning'">
                    {{ row.status === 'active' ? 'فعال' : 'معلق' }}
                </StatusBadge>
            </template>
            <template #cell-stats="{ row }">
                <span class="text-xs text-slate-500">
                    {{ row.classes_count ?? 0 }} / {{ row.memberships_count ?? 0 }}
                </span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex flex-wrap items-center justify-center gap-1">
                    <Link :href="`/school/${row.id}/deputies`" class="clay-btn-ghost px-2 py-1 text-xs">
                        <Icon name="users" :size="14" /> مدیران
                    </Link>
                    <Link :href="`/school/${row.id}/edit`" class="clay-btn-ghost px-2 py-1 text-xs">
                        <Icon name="edit" :size="14" /> ویرایش
                    </Link>
                    <button v-if="canDelete" type="button" class="clay-btn-ghost px-2 py-1 text-xs !text-danger-600" @click="askDelete(row)">
                        <Icon name="trash" :size="14" /> حذف
                    </button>
                </div>
            </template>

            <template v-if="schools.links?.length > 1" #footer>
                <Pagination :links="schools.links" />
            </template>
        </DataTable>

        <ConfirmDialog
            :show="confirmState.show"
            title="حذف مدرسه"
            :message="`آیا از حذف مدرسه «${confirmState.name}» مطمئن هستید؟ این عملیات قابل بازگشت نیست.`"
            confirm-text="حذف کن"
            :loading="confirmState.loading"
            @confirm="doDelete"
            @cancel="confirmState.show = false"
        />
    </PanelLayout>
</template>
