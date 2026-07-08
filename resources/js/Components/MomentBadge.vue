<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    type: { type: String, required: true },
    color: { type: String, default: 'slate' },
});

const { t } = useI18n();

const classes = computed(() => {
    const map = {
        indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
        violet: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        slate: 'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-200',
    };

    return `inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium leading-tight ${map[props.color] ?? map.slate}`;
});

const label = computed(() => t(`app.moment_types.${props.type}`));
</script>

<template>
    <span :class="classes">{{ label }}</span>
</template>
