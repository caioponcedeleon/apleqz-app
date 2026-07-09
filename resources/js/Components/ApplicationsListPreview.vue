<script setup>
import StatusBadge from '@/Components/StatusBadge.vue';
import { useI18n } from 'vue-i18n';
import { buildApplicationsPreviewRows } from '@/composables/useOnboardingPreview';
import { computed } from 'vue';

const props = defineProps({
    sortableColumns: { type: Array, required: true },
    statusColors: { type: Object, required: true },
});

const { t } = useI18n();

const rows = computed(() => buildApplicationsPreviewRows(t));

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};
</script>

<template>
    <div
        class="pointer-events-none select-none overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        aria-hidden="true"
    >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-dashed border-gray-200 px-4 py-3 dark:border-gray-700">
            <span
                class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
            >
                {{ t('app.onboarding.preview.badge') }}
            </span>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.onboarding.preview.disclaimer') }}
            </p>
        </div>

        <div class="overflow-x-auto overscroll-x-contain">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="w-10 px-2 py-3 text-left">
                            <span class="sr-only">{{ t('app.applications.favourite') }}</span>
                        </th>
                        <th
                            v-for="column in sortableColumns"
                            :key="column.key"
                            class="px-4 py-3 text-left"
                            :class="column.key === 'status' ? 'min-w-[11rem]' : ''"
                        >
                            <span class="font-medium text-gray-600 dark:text-gray-300">
                                {{ t(column.label) }}
                            </span>
                        </th>
                        <th class="whitespace-nowrap px-4 py-3 text-right">
                            <span class="sr-only">{{ t('app.actions.edit') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="app in rows" :key="app.id">
                        <td class="px-2 py-3">
                            <span
                                class="inline-flex rounded-md p-1.5"
                                :class="app.is_favourite ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600'"
                            >
                                <svg
                                    class="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    :fill="app.is_favourite ? 'currentColor' : 'none'"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.563.563 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"
                                    />
                                </svg>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ app.position }}
                        </td>
                        <td class="px-4 py-3">{{ app.company }}</td>
                        <td class="px-4 py-3">{{ app.wave?.name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ app.area?.name }}</td>
                        <td class="px-4 py-3">{{ formatDate(app.applied_at) }}</td>
                        <td class="min-w-[11rem] px-4 py-3">
                            <StatusBadge
                                :status="app.status"
                                :color="statusColors[app.status] ?? 'slate'"
                            />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center justify-end gap-1 opacity-60">
                                <span
                                    class="inline-flex rounded-md p-1.5 text-indigo-600 dark:text-indigo-400"
                                    :title="t('app.actions.edit')"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                        />
                                    </svg>
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
