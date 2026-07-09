<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <nav class="flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-900/50">
        <Link
            :href="route('job-alerts.settings')"
            class="rounded-md px-4 py-2 text-sm font-medium transition"
            :class="
                route().current('job-alerts.settings')
                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white'
                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
            "
        >
            {{ t('app.job_alerts.nav_settings') }}
        </Link>
        <Link
            :href="route('job-alerts.matches')"
            class="rounded-md px-4 py-2 text-sm font-medium transition"
            :class="
                route().current('job-alerts.matches')
                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white'
                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
            "
        >
            {{ t('app.job_alerts.nav_matches') }}
        </Link>
    </nav>
</template>
