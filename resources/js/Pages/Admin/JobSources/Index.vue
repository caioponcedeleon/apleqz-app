<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    jobSources: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const scrapingId = ref(null);
const togglingId = ref(null);
const activationError = ref(null);

const companyGroups = computed(() => {
    const groups = new Map();

    for (const source of props.jobSources) {
        const company = typeof source.company_name === 'string' ? source.company_name.trim() : '';

        if (!groups.has(company)) {
            groups.set(company, {
                company,
                sources: [],
            });
        }

        groups.get(company).sources.push(source);
    }

    return [...groups.values()].sort((left, right) => {
        const leftLabel = left.company || t('app.job_sources.no_company');
        const rightLabel = right.company || t('app.job_sources.no_company');

        return leftLabel.localeCompare(rightLabel, undefined, { sensitivity: 'base' });
    });
});

const companyLabel = (company) => company || t('app.job_sources.no_company');

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const scrapeNow = (source) => {
    scrapingId.value = source.id;

    router.post(route('job-sources.scrape', source.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            scrapingId.value = null;
        },
    });
};

const setActive = (source, isActive) => {
    togglingId.value = source.id;
    activationError.value = null;

    router.patch(route('job-sources.toggle-active', source.id), {
        is_active: isActive,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            const message = errors.is_active;

            activationError.value = Array.isArray(message)
                ? message[0]
                : (message ?? t('app.job_sources.errors.cannot_activate_before_config'));
        },
        onFinish: () => {
            togglingId.value = null;
        },
    });
};
</script>

<template>
    <Head :title="t('app.job_sources.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('administration.index')" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        {{ t('app.administration.title') }}
                    </Link>
                    <span>/</span>
                    <span class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ t('app.job_sources.title') }}
                    </span>
                </div>
                <Link :href="route('job-sources.create')">
                    <PrimaryButton type="button">{{ t('app.job_sources.add') }}</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="page.props.flash?.error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    {{ page.props.flash.error }}
                </div>

                <div
                    v-if="page.props.flash?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {{ page.props.flash.warning }}
                </div>

                <div
                    v-if="activationError"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    {{ activationError }}
                </div>

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
                    class="space-y-4"
                >
                    <section
                        v-for="group in companyGroups"
                        :key="group.company || '__none__'"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ companyLabel(group.company) }}
                            </h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('app.job_sources.group_source_count', { count: group.sources.length }) }}
                            </span>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-white dark:bg-gray-800">
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3 font-medium">{{ t('app.job_sources.name') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('app.job_sources.url') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('app.job_sources.active') }}</th>
                                    <th class="px-4 py-3 font-medium">{{ t('app.job_sources.last_scraped') }}</th>
                                    <th class="px-4 py-3 font-medium" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="source in group.sources" :key="source.id">
                                    <td class="px-4 py-3 font-medium">
                                        <Link
                                            :href="route('job-sources.edit', source.id)"
                                            class="text-gray-900 hover:text-indigo-600 hover:underline dark:text-white dark:hover:text-indigo-400"
                                        >
                                            {{ source.name }}
                                        </Link>
                                    </td>
                                    <td class="max-w-xs truncate px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <a
                                            :href="source.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-400"
                                        >
                                            {{ source.url }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <ToggleSwitch
                                            :model-value="source.is_active"
                                            :disabled="togglingId === source.id"
                                            :label="source.is_active ? t('app.job_sources.yes') : t('app.job_sources.no')"
                                            @update:model-value="setActive(source, $event)"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ formatDate(source.last_scraped_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <Link :href="route('job-sources.configure', source.id)">
                                                <SecondaryButton type="button">
                                                    {{ t('app.job_sources.configure') }}
                                                </SecondaryButton>
                                            </Link>
                                            <Link :href="route('job-sources.edit', source.id)">
                                                <SecondaryButton type="button">
                                                    {{ t('app.job_sources.edit') }}
                                                </SecondaryButton>
                                            </Link>
                                            <SecondaryButton
                                                type="button"
                                                :disabled="scrapingId === source.id"
                                                @click="scrapeNow(source)"
                                            >
                                                {{ scrapingId === source.id ? t('app.job_sources.scraping') : t('app.job_sources.scrape_now') }}
                                            </SecondaryButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
