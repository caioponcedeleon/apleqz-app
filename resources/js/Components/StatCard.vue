<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) =>
            ['neutral', 'red', 'amber', 'emerald', 'slate'].includes(value),
    },
});

const cardClasses = computed(() => {
    const map = {
        neutral:
            'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800',
        red: 'border-red-200 bg-red-50 dark:border-red-900/60 dark:bg-red-950/40',
        amber: 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/40',
        emerald:
            'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/40',
        slate: 'border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800/80',
    };

    return `rounded-xl border p-5 shadow-sm ${map[props.tone] ?? map.neutral}`;
});

const labelClasses = computed(() => {
    const map = {
        neutral: 'text-gray-500 dark:text-gray-400',
        red: 'text-red-700/80 dark:text-red-300/90',
        amber: 'text-amber-800/80 dark:text-amber-300/90',
        emerald: 'text-emerald-800/80 dark:text-emerald-300/90',
        slate: 'text-slate-600 dark:text-slate-400',
    };

    return `text-sm font-medium ${map[props.tone] ?? map.neutral}`;
});

const valueClasses = computed(() => {
    const map = {
        neutral: 'text-gray-900 dark:text-white',
        red: 'text-red-900 dark:text-red-100',
        amber: 'text-amber-950 dark:text-amber-50',
        emerald: 'text-emerald-950 dark:text-emerald-50',
        slate: 'text-slate-900 dark:text-slate-100',
    };

    return `mt-2 text-3xl font-semibold tracking-tight ${map[props.tone] ?? map.neutral}`;
});
</script>

<template>
    <div :class="cardClasses">
        <p :class="labelClasses">
            {{ label }}
        </p>
        <p :class="valueClasses">
            {{ value ?? '—' }}
        </p>
    </div>
</template>
