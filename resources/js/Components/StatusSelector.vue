<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    statuses: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const statusColors = {
    a_candidatar: 'sky',
    esperando: 'amber',
    rejeitado: 'red',
    oferta: 'emerald',
    recusado: 'orange',
    retirada: 'slate',
    cancelada: 'zinc',
};

const selectedRing = {
    sky: 'border-sky-400 ring-2 ring-sky-200 dark:border-sky-500 dark:ring-sky-900/60',
    amber: 'border-amber-400 ring-2 ring-amber-200 dark:border-amber-500 dark:ring-amber-900/60',
    red: 'border-red-400 ring-2 ring-red-200 dark:border-red-500 dark:ring-red-900/60',
    emerald: 'border-emerald-400 ring-2 ring-emerald-200 dark:border-emerald-500 dark:ring-emerald-900/60',
    orange: 'border-orange-400 ring-2 ring-orange-200 dark:border-orange-500 dark:ring-orange-900/60',
    slate: 'border-slate-400 ring-2 ring-slate-200 dark:border-slate-500 dark:ring-slate-900/60',
    zinc: 'border-zinc-400 ring-2 ring-zinc-200 dark:border-zinc-500 dark:ring-zinc-900/60',
};

const optionClasses = (status) => {
    const color = statusColors[status] ?? 'slate';
    const isSelected = props.modelValue === status;

    return [
        'rounded-lg border-2 p-2 text-left transition',
        isSelected
            ? selectedRing[color]
            : 'border-transparent opacity-75 hover:opacity-100 hover:border-gray-200 dark:hover:border-gray-600',
    ];
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-3">
            <StatusBadge
                :status="modelValue"
                :color="statusColors[modelValue] ?? 'slate'"
                class="!px-4 !py-2 !text-sm"
            />
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button
                v-for="status in statuses"
                :key="status"
                type="button"
                :class="optionClasses(status)"
                @click="emit('update:modelValue', status)"
            >
                <StatusBadge :status="status" :color="statusColors[status] ?? 'slate'" />
            </button>
        </div>
    </div>
</template>
