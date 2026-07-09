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
const bulkScrapeNotice = ref('');
const bulkScrapeSummary = ref(null);
const bulkScrape = ref({
    active: false,
    total: 0,
    completed: 0,
    currentName: '',
    succeeded: 0,
    failed: 0,
    found: 0,
    newListings: 0,
    startedAt: null,
});

const activeSources = computed(() => props.jobSources.filter((source) => source.is_active));

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

const bulkProgressPercent = computed(() => {
    if (!bulkScrape.value.total) {
        return 0;
    }

    return Math.min(100, Math.round((bulkScrape.value.completed / bulkScrape.value.total) * 100));
});

const bulkInProgress = computed(() => bulkScrape.value.active);

const estimatedSecondsRemaining = computed(() => {
    const { completed, total, startedAt, active } = bulkScrape.value;

    if (!active || !startedAt || completed === 0 || completed >= total) {
        return null;
    }

    const elapsedSeconds = (Date.now() - startedAt) / 1000;
    const averageSeconds = elapsedSeconds / completed;

    return Math.max(1, Math.ceil(averageSeconds * (total - completed)));
});

const bulkEtaLabel = computed(() => {
    const seconds = estimatedSecondsRemaining.value;

    if (seconds === null) {
        return bulkScrape.value.active && bulkScrape.value.completed === bulkScrape.value.total
            ? t('app.job_sources.bulk_scrape_finishing')
            : '';
    }

    if (seconds < 60) {
        return t('app.job_sources.bulk_scrape_eta_seconds', { seconds });
    }

    return t('app.job_sources.bulk_scrape_eta_minutes', { minutes: Math.ceil(seconds / 60) });
});

const bulkStatusLabel = computed(() => {
    if (!bulkScrape.value.active) {
        return '';
    }

    const current = Math.min(bulkScrape.value.completed + 1, bulkScrape.value.total);

    return t('app.job_sources.bulk_scrape_running', {
        current,
        total: bulkScrape.value.total,
        name: bulkScrape.value.currentName,
    });
});

const bulkProgressStatsLabel = computed(() => {
    if (!bulkScrape.value.active) {
        return '';
    }

    return t('app.job_sources.bulk_scrape_progress_stats', {
        success: bulkScrape.value.succeeded,
        failed: bulkScrape.value.failed,
    });
});

const bulkScrapeSummaryLabel = computed(() => {
    if (!bulkScrapeSummary.value) {
        return '';
    }

    const summary = bulkScrapeSummary.value;

    return t('app.job_sources.bulk_scrape_summary', {
        success: summary.succeeded,
        failed: summary.failed,
        found: summary.found,
        new: summary.newListings,
    });
});

const dismissBulkScrapeSummary = () => {
    bulkScrapeSummary.value = null;
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const scrapeNow = (source) => {
    if (bulkInProgress.value) {
        return;
    }

    scrapingId.value = source.id;

    router.post(route('job-sources.scrape', source.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            scrapingId.value = null;
        },
    });
};

const scrapeAllNow = async () => {
    if (bulkInProgress.value) {
        return;
    }

    bulkScrapeNotice.value = '';
    bulkScrapeSummary.value = null;

    const sources = activeSources.value;

    if (sources.length === 0) {
        bulkScrapeNotice.value = t('app.job_sources.bulk_scrape_no_active');

        return;
    }

    bulkScrape.value = {
        active: true,
        total: sources.length,
        completed: 0,
        currentName: sources[0].name,
        succeeded: 0,
        failed: 0,
        found: 0,
        newListings: 0,
        startedAt: Date.now(),
    };

    for (const source of sources) {
        bulkScrape.value.currentName = source.name;

        try {
            const { data } = await window.axios.post(route('job-sources.scrape', source.id));

            if (data.status === 'failed') {
                bulkScrape.value.failed += 1;
            } else {
                bulkScrape.value.succeeded += 1;
                bulkScrape.value.found += data.listings_found ?? 0;
                bulkScrape.value.newListings += data.listings_new ?? 0;
            }
        } catch {
            bulkScrape.value.failed += 1;
        }

        bulkScrape.value.completed += 1;
    }

    bulkScrape.value.active = false;
    bulkScrape.value.currentName = '';

    const summary = {
        succeeded: bulkScrape.value.succeeded,
        failed: bulkScrape.value.failed,
        found: bulkScrape.value.found,
        newListings: bulkScrape.value.newListings,
    };

    bulkScrapeSummary.value = summary;

    router.reload({
        only: ['jobSources'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            bulkScrapeSummary.value ??= summary;
        },
    });
};

const setActive = (source, isActive) => {
    if (bulkInProgress.value) {
        return;
    }

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
                <div class="flex flex-wrap items-center gap-3">
                    <SecondaryButton
                        type="button"
                        :disabled="bulkInProgress || activeSources.length === 0"
                        @click="scrapeAllNow"
                    >
                        {{ bulkInProgress ? t('app.job_sources.scraping') : t('app.job_sources.scrape_all_now') }}
                    </SecondaryButton>
                    <Link :href="route('job-sources.create')">
                        <PrimaryButton type="button">{{ t('app.job_sources.add') }}</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="bulkInProgress"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-4 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/30"
                    role="status"
                    aria-live="polite"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="mt-0.5 size-5 shrink-0 animate-spin text-indigo-600 dark:text-indigo-400"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-indigo-950 dark:text-indigo-100">
                                {{ bulkStatusLabel }}
                            </p>
                            <p class="mt-1 text-xs text-indigo-800/90 dark:text-indigo-200/90">
                                {{ bulkProgressStatsLabel }}
                            </p>
                            <p
                                v-if="bulkEtaLabel"
                                class="mt-1 text-xs text-indigo-800/80 dark:text-indigo-200/80"
                            >
                                {{ bulkEtaLabel }}
                            </p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900/50">
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all duration-500 ease-out dark:bg-indigo-400"
                                    :style="{ width: `${bulkProgressPercent}%` }"
                                />
                            </div>
                            <p class="mt-2 text-xs text-indigo-800/70 dark:text-indigo-200/70">
                                {{ bulkProgressPercent }}%
                            </p>
                        </div>
                    </div>
                </div>

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
                    v-if="bulkScrapeSummary && !bulkInProgress"
                    class="rounded-xl border px-4 py-4 shadow-sm"
                    :class="bulkScrapeSummary.failed > 0
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30'
                        : 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-950/30'"
                    role="status"
                    aria-live="polite"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm font-medium"
                                :class="bulkScrapeSummary.failed > 0
                                    ? 'text-amber-950 dark:text-amber-100'
                                    : 'text-green-950 dark:text-green-100'"
                            >
                                {{ bulkScrapeSummaryLabel }}
                            </p>
                            <p
                                class="mt-1 text-xs"
                                :class="bulkScrapeSummary.failed > 0
                                    ? 'text-amber-800/80 dark:text-amber-200/80'
                                    : 'text-green-800/80 dark:text-green-200/80'"
                            >
                                {{ t('app.job_sources.bulk_scrape_progress_stats', {
                                    success: bulkScrapeSummary.succeeded,
                                    failed: bulkScrapeSummary.failed,
                                }) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-xs font-medium underline-offset-2 hover:underline"
                            :class="bulkScrapeSummary.failed > 0
                                ? 'text-amber-900 dark:text-amber-200'
                                : 'text-green-900 dark:text-green-200'"
                            @click="dismissBulkScrapeSummary"
                        >
                            {{ t('app.job_sources.bulk_scrape_dismiss') }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="bulkScrapeNotice && !bulkInProgress && !bulkScrapeSummary"
                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200"
                >
                    {{ bulkScrapeNotice }}
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
                                            :disabled="togglingId === source.id || bulkInProgress"
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
                                                <SecondaryButton type="button" :disabled="bulkInProgress">
                                                    {{ t('app.job_sources.configure') }}
                                                </SecondaryButton>
                                            </Link>
                                            <Link :href="route('job-sources.edit', source.id)">
                                                <SecondaryButton type="button" :disabled="bulkInProgress">
                                                    {{ t('app.job_sources.edit') }}
                                                </SecondaryButton>
                                            </Link>
                                            <SecondaryButton
                                                type="button"
                                                :disabled="scrapingId === source.id || bulkInProgress"
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
