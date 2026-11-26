<script setup>
import EmptyState from './EmptyState.vue'

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    emptyTitle: { type: String, default: 'موردی یافت نشد' },
    emptyDescription: String,
})

function getValue(row, col) {
    if (col.getValue) return col.getValue(row)
    return row[col.key]
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-5 py-3 font-semibold text-slate-600',
                                col.align === 'center' ? 'text-center' : col.align === 'left' ? 'text-left' : 'text-right'
                            ]"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length" class="p-0">
                            <EmptyState :title="emptyTitle" :description="emptyDescription" />
                        </td>
                    </tr>
                    <tr v-for="(row, index) in rows" :key="row.id ?? index" class="transition hover:bg-slate-50/60">
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-5 py-3.5 text-slate-700',
                                col.align === 'center' ? 'text-center' : col.align === 'left' ? 'text-left' : 'text-right'
                            ]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="getValue(row, col)">
                                {{ getValue(row, col) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="$slots.footer" class="border-t border-slate-200 bg-slate-50 px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>