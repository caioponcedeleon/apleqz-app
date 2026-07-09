<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    jobSources: { type: Array, default: () => [] },
});

const { t } = useI18n();

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head :title="t('app.job_sources.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ t('app.job_sources.title') }}
                </h2>
                <Link :href="route('job-sources.create')">
                    <PrimaryButton type="button">{{ t('app.job_sources.add') }}</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p
                    class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm leading-relaxed text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400"
                >
                    {{ t('app.job_sources.description') }}
                </p>

                <div
                    v-if="!jobSources.length"
                    class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ t('app.job_sources.empty') }}
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-medium">{{ t('app.job_sources.name') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('app.job_sources.url') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('app.job_sources.active') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('app.job_sources.last_scraped') }}</th>
                                <th class="px-4 py-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="source in jobSources" :key="source.id">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ source.name }}
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ source.url }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="source.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200'
                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300'"
                                    >
                                        {{ source.is_active ? t('app.job_sources.yes') : t('app.job_sources.no') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    {{ formatDate(source.last_scraped_at) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <Link
                                            :href="route('job-sources.configure', source.id)"
                                            class="text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            {{ t('app.job_sources.configure') }}
                                        </Link>
                                        <Link
                                            :href="route('job-sources.edit', source.id)"
                                            class="text-gray-600 hover:underline dark:text-gray-300"
                                        >
                                            {{ t('app.job_sources.edit') }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
