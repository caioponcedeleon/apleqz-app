<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-3">
        <button
            type="button"
            role="switch"
            class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-800"
            :class="modelValue ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'"
            :aria-checked="modelValue"
            :disabled="disabled"
            @click="$emit('update:modelValue', !modelValue)"
        >
            <span
                class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition"
                :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
            />
        </button>
        <span
            v-if="label"
            class="text-sm text-gray-700 dark:text-gray-200"
        >
            {{ label }}
        </span>
    </label>
</template>
