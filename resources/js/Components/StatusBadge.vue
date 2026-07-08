<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: { type: String, required: true },
    color: { type: String, default: 'slate' },
    inline: { type: Boolean, default: false },
});

const { t } = useI18n();

const classes = computed(() => {
    const map = {
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
        slate: 'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-200',
        zinc: 'bg-zinc-100 text-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-200',
        sky: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    };

    const layout = props.inline
        ? 'inline-flex'
        : 'flex w-full items-center justify-center';

    return `${layout} rounded-full px-2.5 py-1 text-xs font-medium leading-tight ${map[props.color] ?? map.slate}`;
});

const label = computed(() => t(`app.status.${props.status}`));
</script>

<template>
    <span :class="classes">{{ label }}</span>
</template>
