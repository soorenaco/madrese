<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    links: { type: Array, default: () => [] },
});

function isDisabled(url) {
    return !url;
}

function stripHtml(html) {
    // پیجینیتور لاراول HTML می‌فرستد؛ فقط متن را برمی‌داریم و سپس خودمان رندر می‌کنیم.
    if (typeof window === 'undefined') {
        return String(html).replace(/<[^>]*>/g, '').trim();
    }
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return (tmp.textContent || tmp.innerText || '').trim();
}

function labelOf(link) {
    const txt = stripHtml(link.label);
    if (txt.includes('Previous')) return 'قبلی';
    if (txt.includes('Next')) return 'بعدی';
    return txt;
}

function isPrev(link) {
    return stripHtml(link.label).includes('Previous');
}
function isNext(link) {
    return stripHtml(link.label).includes('Next');
}
</script>

<template>
    <nav v-if="links.length > 1" class="flex flex-wrap items-center gap-1.5" aria-label="صفحه‌بندی">
        <Link
            v-for="(link, i) in links"
            :key="i"
            :href="link.url || '#'"
            :class="[
                'clay-chip inline-flex h-9 min-w-9 items-center justify-center px-3 text-sm font-medium transition',
                isDisabled(link.url) && 'pointer-events-none opacity-40',
                link.active
                    ? '!bg-brand-600 !text-white shadow-clay-brand'
                    : 'text-slate-600 hover:text-brand-700',
            ]"
            :aria-current="link.active ? 'page' : undefined"
            :tabindex="isDisabled(link.url) ? -1 : 0"
            preserve-scroll
        >
            {{ labelOf(link) }}
        </Link>
    </nav>
</template>
