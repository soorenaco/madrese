<script setup>
import { computed } from 'vue';

/**
 * نمودار خطی/میهانی سبک SVG — بدون وابستگی.
 * data: آرایه‌ای از عدد. labels: اختیاری برای محور X.
 */
const props = defineProps({
    data: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    height: { type: Number, default: 80 },
    color: { type: String, default: '#7c3aed' },
    fill: { type: String, default: 'rgba(124, 58, 237, 0.14)' },
    type: { type: String, default: 'line' }, // 'line' | 'area' | 'bars'
});

const W = 100;            // viewBox width (scaled)
const H = 40;             // viewBox height (scaled)

const values = computed(() => {
    const d = props.data.filter((v) => typeof v === 'number' && !isNaN(v));
    return d.length ? d : [0, 0];
});

const max = computed(() => Math.max(...values.value, 1));
const min = computed(() => Math.min(...values.value, 0));
const range = computed(() => Math.max(max.value - min.value, 1));

const points = computed(() => {
    const n = values.value.length;
    if (n === 1) return [{ x: W / 2, y: H / 2 }];
    return values.value.map((v, i) => ({
        x: (i / (n - 1)) * W,
        y: H - ((v - min.value) / range.value) * (H - 6) - 3,
    }));
});

const linePath = computed(() => {
    return points.value
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(2)},${p.y.toFixed(2)}`)
        .join(' ');
});

const areaPath = computed(() => {
    if (points.value.length < 2) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `${linePath.value} L${last.x.toFixed(2)},${H} L${first.x.toFixed(2)},${H} Z`;
});

const barWidth = computed(() => {
    const n = values.value.length || 1;
    return Math.max(W / n - 3, 2);
});
</script>

<template>
    <div class="w-full">
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            :style="{ height: height + 'px' }"
            preserveAspectRatio="none"
            class="w-full"
            role="img"
            aria-label="نمودار"
        >
            <defs>
                <linearGradient :id="`g-${color.slice(1)}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.32" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0.02" />
                </linearGradient>
            </defs>

            <!-- bars -->
            <template v-if="type === 'bars'">
                <rect
                    v-for="(p, i) in points"
                    :key="i"
                    :x="p.x - barWidth / 2"
                    :y="p.y"
                    :width="barWidth"
                    :height="H - p.y"
                    :rx="1.5"
                    :fill="color"
                    opacity="0.85"
                />
            </template>

            <!-- area + line -->
            <template v-else>
                <path
                    v-if="type === 'area'"
                    :d="areaPath"
                    :fill="`url(#g-${color.slice(1)})`"
                    stroke="none"
                />
                <path
                    :d="linePath"
                    fill="none"
                    :stroke="color"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                />
                <circle
                    v-if="points.length"
                    :cx="points[points.length - 1].x"
                    :cy="points[points.length - 1].y"
                    r="2.5"
                    :fill="color"
                    vector-effect="non-scaling-stroke"
                />
            </template>
        </svg>
        <div v-if="labels.length" class="mt-1.5 flex justify-between text-[10px] text-slate-400">
            <span v-for="(l, i) in labels" :key="i">{{ l }}</span>
        </div>
    </div>
</template>
