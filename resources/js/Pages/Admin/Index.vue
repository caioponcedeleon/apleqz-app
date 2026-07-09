<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('app.administration.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.administration.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ t('app.administration.intro') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        :href="route('job-sources.index')"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-600"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ t('app.administration.job_sources_card_title') }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {{ t('app.administration.job_sources_card_help') }}
                        </p>
                    </Link>

                    <Link
                        :href="route('administration.ai-usage')"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-600"
                    >
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ t('app.administration.ai_usage_card_title') }}
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            {{ t('app.administration.ai_usage_card_help') }}
                        </p>
                    </Link>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a
                        href="/admin"
                        class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        {{ t('app.administration.filament_link') }}
                    </a>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
