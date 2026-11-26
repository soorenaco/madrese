<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import PanelLayout from '../../Layouts/PanelLayout.vue';
import PageHeader from '../../Components/Ui/PageHeader.vue';
import SectionCard from '../../Components/Ui/SectionCard.vue';
import Pagination from '../../Components/Ui/Pagination.vue';
import StatusBadge from '../../Components/Ui/StatusBadge.vue';
import PrimaryButton from '../../Components/Ui/PrimaryButton.vue';
import TextInput from '../../Components/Ui/TextInput.vue';
import SelectInput from '../../Components/Ui/SelectInput.vue';
import Icon from '../../Components/Icon.vue';
import ConfirmDialog from '../../Components/Ui/ConfirmDialog.vue';
import { useToast } from '../../composables/useToast';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    classes: { type: Object, required: true },
    schools: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', school_id: '' }) },
});

const toast = useToast();

const q = ref(props.filters.q || '');
const schoolId = ref(props.filters.school_id || '');

let searchTimer = null;
function search() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/classes', {
            q: q.value || undefined,
            school_id: schoolId.value || undefined,
        }, { preserveState: true, replace: true, preserveScroll: true });
    }, 300);
}

const confirmState = ref({ show: false, id: null, name: '', loading: false });
function askDelete(item) {
    confirmState.value = { show: true, id: item.id, name: item.name, loading: false };
}
function doDelete() {
    confirmState.value.loading = true;
    router.delete(`/classes/${confirmState.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            confirmState.value.show = false;
            confirmState.value.loading = false;
        },
        onSuccess: () => toast.success('کلاس حذف شد.'),
        onError: () => toast.error('حذف کلاس با خطا مواجه شد.'),
    });
}

const rows = props.classes.data || [];
</script>

<template>
    <PanelLayout>
        <PageHeader title="کلاس‌ها" description="مدیریت کلاس‌های مدارس">
            <template #icon><Icon name="layers" :size="22" /></template>
            <template #actions>
                <PrimaryButton @click="$inertia.visit('/classes/create')">
                    <Icon name="plus" :size="16" />
                    کلاس جدید
                </PrimaryButton>
            </template>
        </PageHeader>

        <!-- فیلترها -->
        <div class="mt-6 mb-4 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1 sm:max-w-xs">
                <TextInput
                    v-model="q"
                    type="search"
                    placeholder="جستجو نام یا پایه..."
                    icon="search"
                    @input="search"
                />
            </div>
            <div class="sm:w-56">
                <SelectInput v-model="schoolId" placeholder="همه مدارس" @change="search">
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </SelectInput>
            </div>
        </div>

        <SectionCard flat>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-clay-200 bg-clay-100/60 text-slate-600">
                            <th class="px-4 py-3 text-right font-semibold">نام کلاس</th>
                            <th class="hidden px-4 py-3 text-right font-semibold sm:table-cell">مدرسه</th>
                            <th class="hidden px-4 py-3 text-right font-semibold md:table-cell">پایه</th>
                            <th class="hidden px-4 py-3 text-right font-semibold md:table-cell">سال</th>
                            <th class="px-4 py-3 text-center font-semibold">وضعیت</th>
                            <th class="px-4 py-3 text-center font-semibold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-clay-200/70">
                        <tr v-for="item in rows" :key="item.id" class="transition-colors hover:bg-clay-100/50">
                            <td class="px-4 py-3.5 font-medium text-slate-800">{{ item.name }}</td>
                            <td class="hidden px-4 py-3.5 text-slate-600 sm:table-cell">{{ item.school?.name || '—' }}</td>
                            <td class="hidden px-4 py-3.5 text-slate-600 md:table-cell">{{ item.grade || '—' }}</td>
                            <td class="hidden px-4 py-3.5 text-slate-600 md:table-cell">{{ item.academic_year || '—' }}</td>
                            <td class="px-4 py-3.5 text-center">
                                <StatusBadge :variant="item.status === 'active' ? 'success' : 'neutral'">
                                    {{ item.status === 'active' ? 'فعال' : 'بایگانی' }}
                                </StatusBadge>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap items-center justify-center gap-1">
                                    <Link :href="`/classes/${item.id}/roster`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="users" :size="14" /> اعضا
                                    </Link>
                                    <Link :href="`/classes/${item.id}/attendance`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="check-circle" :size="14" /> حضور و غیاب
                                    </Link>
                                    <Link :href="`/classes/${item.id}/online`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="video" :size="14" /> آنلاین
                                    </Link>
                                    <Link :href="`/classes/${item.id}/schedule`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="calendar" :size="14" /> برنامه
                                    </Link>
                                    <Link :href="`/classes/${item.id}/promote`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="trending-up" :size="14" /> سال
                                    </Link>
                                    <Link :href="`/classes/${item.id}/edit`" class="clay-btn-ghost px-2 py-1 text-xs">
                                        <Icon name="edit" :size="14" />
                                    </Link>
                                    <button type="button" class="clay-btn-ghost px-2 py-1 text-xs !text-danger-600" @click="askDelete(item)">
                                        <Icon name="trash" :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center gap-2">
                                    <Icon name="layers" :size="28" />
                                    <span>کلاسی ثبت نشده است.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template v-if="classes.links?.length > 1" #footer>
                <Pagination :links="classes.links" />
            </template>
        </SectionCard>

        <ConfirmDialog
            :show="confirmState.show"
            title="حذف کلاس"
            :message="`آیا از حذف کلاس «${confirmState.name}» مطمئن هستید؟`"
            confirm-text="حذف کن"
            :loading="confirmState.loading"
            @confirm="doDelete"
            @cancel="confirmState.show = false"
        />
    </PanelLayout>
</template>
