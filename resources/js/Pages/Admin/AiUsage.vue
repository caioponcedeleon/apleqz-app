<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    driver: { type: String, required: true },
    model: { type: String, required: true },
    summary: {
        type: Object,
        required: true,
    },
    allTime: {
        type: Object,
        required: true,
    },
    recent: {
        type: Array,
        default: () => [],
    },
    pricingAvailable: { type: Boolean, default: false },
});

const { t } = useI18n();

const formatNumber = (value) => new Intl.NumberFormat().format(value ?? 0);

const formatCost = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return `€${value.toFixed(4)}`;
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head :title="t('app.administration.ai_usage_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <Link :href="route('administration.index')" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                    {{ t('app.administration.title') }}
                </Link>
                <span>/</span>
                <span class="text-gray-800 dark:text-gray-200">{{ t('app.administration.ai_usage_title') }}</span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ t('app.administration.ai_usage_intro') }}
                    </p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                        {{ t('app.administration.ai_usage_active_driver', { driver, model }) }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ t('app.administration.ai_usage_requests') }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ formatNumber(summary.request_count) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ t('app.administration.ai_usage_prompt_tokens') }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ formatNumber(summary.prompt_tokens) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ t('app.administration.ai_usage_completion_tokens') }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ formatNumber(summary.completion_tokens) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ t('app.administration.ai_usage_estimated_cost') }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ pricingAvailable ? formatCost(summary.estimated_cost_eur) : '—' }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="!pricingAvailable"
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t('app.administration.ai_usage_no_pricing') }}
                </p>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ t('app.administration.ai_usage_all_time') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ t('app.administration.ai_usage_all_time_stats', {
                            requests: formatNumber(allTime.request_count),
                            tokens: formatNumber(allTime.total_tokens),
                        }) }}
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ t('app.administration.ai_usage_recent') }}
                        </h3>
                    </div>

                    <div
                        v-if="!recent.length"
                        class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t('app.administration.ai_usage_empty') }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                                        {{ t('app.administration.ai_usage_col_when') }}
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                                        {{ t('app.administration.ai_usage_col_user') }}
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                                        {{ t('app.administration.ai_usage_col_tokens') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="row in recent" :key="row.id">
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ formatDate(row.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ row.user?.name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ row.prompt_tokens }} / {{ row.completion_tokens }} ({{ row.total_tokens }})
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
