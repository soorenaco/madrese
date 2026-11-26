import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useDebouncedSearch(options = {}) {
    const { url, params = {}, debounce = 300, initial = '' } = options;
    const q = ref(initial);
    let timer = null;

    function buildQuery(extra = {}) {
        const merged = { q: q.value || undefined, ...params, ...extra };
        for (const k of Object.keys(merged)) {
            if (merged[k] === '' || merged[k] === null || merged[k] === undefined) {
                delete merged[k];
            }
        }
        return merged;
    }

    function submit(extra = {}) {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(url, buildQuery(extra), {
                preserveState: true, replace: true, preserveScroll: true,
            });
        }, debounce);
    }

    function submitNow(extra = {}) {
        clearTimeout(timer);
        router.get(url, buildQuery(extra), {
            preserveState: true, replace: true, preserveScroll: true,
        });
    }

    return { q, submit, submitNow };
}