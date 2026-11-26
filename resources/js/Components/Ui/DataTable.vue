<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    emptyTitle: { type: String, default: 'موردی یافت نشد' },
    emptyDescription: String,
    loading: { type: Boolean, default: false },
    rowKey: { type: String, default: 'id' },
    dense: { type: Boolean, default: false },
});

function getValue(row, col) {
    if (col.getValue) return col.getValue(row);
    return row[col.key];
}

function alignClass(col) {
    if (col.align === 'center') return 'text-center';
    if (col.align === 'left') return 'text-left';
    return 'text-right';
}

const cellPad = computed(() => props.dense ? 'px-4 py-2.5' : 'px-4 py-3.5');
</script>

<template>
    <div class="clay-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-clay-200 bg-clay-100/60">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="['px-4 py-3 font-semibold text-slate-600 whitespace-nowrap', alignClass(col)]"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-clay-200/70">
                    <!-- loading skeleton -->
                    <template v-if="loading">
                        <tr v-for="n in 4" :key="`sk-${n}`">
                            <td v-for="col in columns" :key="col.key" :class="cellPad">
                                <div class="h-3.5 w-3/4 animate-pulse rounded-full bg-clay-200"></div>
                            </td>
                        </tr>
                    </template>
                    <!-- empty -->
                    <tr v-else-if="rows.length === 0">
                        <td :colspan="columns.length" class="p-0">
                            <EmptyState :title="emptyTitle" :description="emptyDescription" />
                        </td>
                    </tr>
                    <!-- rows -->
                    <tr
                        v-for="(row, index) in rows"
                        :key="row[rowKey] ?? index"
                        class="transition-colors hover:bg-clay-100/50"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[cellPad, 'text-slate-700', alignClass(col)]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="getValue(row, col)" :index="index">
                                {{ getValue(row, col) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="$slots.footer" class="border-t border-clay-200 bg-clay-50/60 px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
