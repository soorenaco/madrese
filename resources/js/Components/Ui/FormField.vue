<script>
let fieldCounter = 0
</script>

<script setup>
defineProps({
    label: String,
    description: String,
    error: String,
    required: Boolean,
})

const uid = `field-${++fieldCounter}`
</script>

<template>
    <div>
        <label v-if="label" :for="uid" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-rose-500">*</span>
        </label>
        <p v-if="description" class="mb-1.5 text-xs text-slate-500">{{ description }}</p>
        <slot :id="uid" :invalid="!!error" />
        <p v-if="error" class="mt-1 text-xs text-rose-600">{{ error }}</p>
    </div>
</template>