<script setup>
import JobAlertsNav from '@/Components/JobAlertsNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    matches: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('app.job_alerts.matches_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.job_alerts.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <JobAlertsNav />

                <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_alerts.matches_empty_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_alerts.matches_empty_help') }}
                    </p>
                    <p class="mt-4 text-sm text-gray-400 dark:text-gray-500">
                        {{ t('app.job_alerts.matches_coming_soon') }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
