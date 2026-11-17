<script setup>
defineProps({
    status: { type: String, required: true },
});
</script>

<template>
    <main class="min-h-screen flex items-center justify-center">
        <div class="rounded-2xl bg-white p-8 shadow text-center">
            <h1 class="text-xl font-bold">Inertia + Vue فعال است</h1>
            <p class="mt-2 text-slate-500">وضعیت: {{ status }}</p>
        </div>
    </main>
</template>
