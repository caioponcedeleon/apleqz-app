<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
});
</script>

<template>
    <div
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ label }}
        </p>
        <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
            {{ value ?? '—' }}
        </p>
    </div>
</template>
