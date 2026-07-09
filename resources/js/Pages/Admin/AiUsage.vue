<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const MATCH_RUN_STORAGE_KEY = 'apleqz.matchRunId';

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
    queuedMatchJobs: { type: Number, default: 0 },
    queueConnection: { type: String, default: 'database' },
});

const { t } = useI18n();
const page = usePage();

const matchModalOpen = ref(false);
const matchPreviewLoading = ref(false);
const matchPreviewError = ref('');
const matchPreview = ref(null);
const matchSubmitting = ref(false);
const matchRun = ref(null);
const matchRunSummary = ref(null);
let matchRunPollTimer = null;

const matchRunActive = computed(() => matchRun.value !== null && !matchRun.value.finished);

const matchRunProgressPercent = computed(() => {
    if (!matchRun.value?.total) {
        return 0;
    }

    return Math.min(100, Math.round((matchRun.value.processed / matchRun.value.total) * 100));
});

const matchRunSummaryLabel = computed(() => {
    if (!matchRunSummary.value) {
        return '';
    }

    return t('app.administration.ai_usage_match_finished', {
        completed: formatNumber(matchRunSummary.value.completed),
        failed: formatNumber(matchRunSummary.value.failed),
    });
});

const showIdleQueueWarning = computed(() => {
    return !matchRunActive.value
        && props.queueConnection !== 'sync'
        && props.queuedMatchJobs > 0;
});

const dismissMatchRunSummary = () => {
    matchRunSummary.value = null;
};

const stopMatchRunPolling = () => {
    if (matchRunPollTimer !== null) {
        clearInterval(matchRunPollTimer);
        matchRunPollTimer = null;
    }
};

const finishMatchRun = (status) => {
    stopMatchRunPolling();
    sessionStorage.removeItem(MATCH_RUN_STORAGE_KEY);

    matchRunSummary.value = {
        completed: status.completed ?? 0,
        failed: status.failed ?? 0,
    };
    matchRun.value = null;

    router.reload({
        only: ['summary', 'allTime', 'recent'],
        preserveScroll: true,
    });
};

const pollMatchRunStatus = async () => {
    if (!matchRun.value?.runId) {
        return;
    }

    try {
        const { data } = await window.axios.get(route('administration.ai-usage.match-status', {
            run: matchRun.value.runId,
        }));

        if (!data.found) {
            stopMatchRunPolling();
            sessionStorage.removeItem(MATCH_RUN_STORAGE_KEY);
            matchRun.value = null;

            return;
        }

        matchRun.value = {
            ...matchRun.value,
            ...data,
        };

        if (data.finished) {
            finishMatchRun(data);
        }
    } catch {
        // Keep polling — transient network errors should not stop the banner.
    }
};

const startMatchRunPolling = (runId) => {
    stopMatchRunPolling();
    sessionStorage.setItem(MATCH_RUN_STORAGE_KEY, runId);

    matchRun.value = {
        runId,
        total: 0,
        completed: 0,
        failed: 0,
        queued: 0,
        processed: 0,
        finished: false,
        worker_needed: false,
    };

    pollMatchRunStatus();
    matchRunPollTimer = setInterval(pollMatchRunStatus, 2000);
};

onMounted(() => {
    const flashRunId = page.props.flash?.match_run_id;

    if (typeof flashRunId === 'string' && flashRunId !== '') {
        startMatchRunPolling(flashRunId);

        return;
    }

    const storedRunId = sessionStorage.getItem(MATCH_RUN_STORAGE_KEY);

    if (storedRunId) {
        startMatchRunPolling(storedRunId);
    }
});

onUnmounted(() => {
    stopMatchRunPolling();
});

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

const formatEstimatedDuration = (seconds) => {
    if (seconds >= 60) {
        return t('app.administration.ai_usage_run_matches_estimated_time_minutes', {
            minutes: Math.ceil(seconds / 60),
        });
    }

    return t('app.administration.ai_usage_run_matches_estimated_time_seconds', { seconds });
};

const canConfirmMatchRun = computed(() => {
    return !matchPreviewLoading.value
        && !matchSubmitting.value
        && matchPreview.value
        && matchPreview.value.evaluations > 0;
});

const openMatchModal = async () => {
    matchModalOpen.value = true;
    matchPreviewLoading.value = true;
    matchPreviewError.value = '';
    matchPreview.value = null;

    try {
        const { data } = await window.axios.get(route('administration.ai-usage.match-preview'));
        matchPreview.value = data;
    } catch {
        matchPreviewError.value = t('app.administration.ai_usage_run_matches_preview_error');
    } finally {
        matchPreviewLoading.value = false;
    }
};

const closeMatchModal = () => {
    if (matchSubmitting.value) {
        return;
    }

    matchModalOpen.value = false;
};

watch(matchModalOpen, (open) => {
    if (!open) {
        matchPreview.value = null;
        matchPreviewError.value = '';
        matchPreviewLoading.value = false;
    }
});

const confirmMatchRun = async () => {
    if (!canConfirmMatchRun.value) {
        return;
    }

    matchSubmitting.value = true;

    try {
        const { data } = await window.axios.post(route('administration.ai-usage.run-matches'));
        matchModalOpen.value = false;
        startMatchRunPolling(data.run_id);
    } catch {
        matchPreviewError.value = t('app.administration.ai_usage_run_matches_preview_error');
    } finally {
        matchSubmitting.value = false;
    }
};
</script>

<template>
    <Head :title="t('app.administration.ai_usage_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('administration.index')" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                        {{ t('app.administration.title') }}
                    </Link>
                    <span>/</span>
                    <span class="text-gray-800 dark:text-gray-200">{{ t('app.administration.ai_usage_title') }}</span>
                </div>
                <PrimaryButton type="button" :disabled="matchRunActive" @click="openMatchModal">
                    {{ matchRunActive ? t('app.administration.ai_usage_run_matches_loading') : t('app.administration.ai_usage_run_matches') }}
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="matchRunActive"
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
                                {{ t('app.administration.ai_usage_match_running', {
                                    completed: formatNumber(matchRun.processed),
                                    total: formatNumber(matchRun.total),
                                }) }}
                            </p>
                            <p
                                v-if="matchRun.queued > 0"
                                class="mt-1 text-xs text-indigo-800/90 dark:text-indigo-200/90"
                            >
                                {{ t('app.administration.ai_usage_match_queued', { count: formatNumber(matchRun.queued) }) }}
                            </p>
                            <p
                                v-if="matchRun.failed > 0"
                                class="mt-1 text-xs text-amber-800 dark:text-amber-200"
                            >
                                {{ t('app.administration.ai_usage_match_failed', { count: formatNumber(matchRun.failed) }) }}
                            </p>
                            <p
                                v-if="matchRun.worker_needed"
                                class="mt-2 rounded-lg border border-amber-300/80 bg-amber-100/80 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
                            >
                                {{ t('app.administration.ai_usage_match_worker_needed') }}
                            </p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900/50">
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all duration-500 ease-out dark:bg-indigo-400"
                                    :style="{ width: `${matchRunProgressPercent}%` }"
                                />
                            </div>
                            <p class="mt-2 text-xs text-indigo-800/70 dark:text-indigo-200/70">
                                {{ matchRunProgressPercent }}%
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="matchRunSummary && !matchRunActive"
                    class="rounded-xl border px-4 py-4 shadow-sm"
                    :class="matchRunSummary.failed > 0
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30'
                        : 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-950/30'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p
                            class="text-sm font-medium"
                            :class="matchRunSummary.failed > 0
                                ? 'text-amber-950 dark:text-amber-100'
                                : 'text-green-950 dark:text-green-100'"
                        >
                            {{ matchRunSummaryLabel }}
                        </p>
                        <button
                            type="button"
                            class="shrink-0 text-xs font-medium underline-offset-2 hover:underline"
                            :class="matchRunSummary.failed > 0
                                ? 'text-amber-900 dark:text-amber-200'
                                : 'text-green-900 dark:text-green-200'"
                            @click="dismissMatchRunSummary"
                        >
                            {{ t('app.administration.ai_usage_match_dismiss') }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.success && !matchRunActive && !matchRunSummary"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="showIdleQueueWarning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <p>
                        {{ t('app.administration.ai_usage_match_queued', { count: formatNumber(queuedMatchJobs) }) }}
                    </p>
                    <p class="mt-2">
                        {{ t('app.administration.ai_usage_match_worker_needed') }}
                    </p>
                </div>

                <div
                    v-if="page.props.flash?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {{ page.props.flash.warning }}
                </div>

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

        <Modal :show="matchModalOpen" max-width="lg" @close="closeMatchModal">
            <div class="px-6 py-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('app.administration.ai_usage_run_matches_title') }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    {{ t('app.administration.ai_usage_run_matches_intro') }}
                </p>

                <div
                    v-if="matchPreviewLoading"
                    class="mt-5 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t('app.administration.ai_usage_run_matches_loading') }}
                </div>

                <div
                    v-else-if="matchPreviewError"
                    class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    {{ matchPreviewError }}
                </div>

                <div
                    v-else-if="matchPreview"
                    class="mt-5 space-y-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm dark:border-gray-700 dark:bg-gray-900/50"
                >
                    <p class="font-medium text-gray-900 dark:text-white">
                        {{ t('app.administration.ai_usage_run_matches_evaluations', { count: formatNumber(matchPreview.evaluations) }) }}
                    </p>

                    <p
                        v-if="matchPreview.evaluations > 0"
                        class="text-gray-600 dark:text-gray-300"
                    >
                        {{ t('app.administration.ai_usage_run_matches_scope', {
                            users: formatNumber(matchPreview.users),
                            listings: formatNumber(matchPreview.listings),
                        }) }}
                    </p>

                    <p
                        v-if="matchPreview.skipped_cached > 0"
                        class="text-gray-500 dark:text-gray-400"
                    >
                        {{ t('app.administration.ai_usage_run_matches_skipped_cached', { count: formatNumber(matchPreview.skipped_cached) }) }}
                    </p>

                    <p
                        v-if="matchPreview.skipped_no_profile > 0"
                        class="text-gray-500 dark:text-gray-400"
                    >
                        {{ t('app.administration.ai_usage_run_matches_skipped_no_profile', { count: formatNumber(matchPreview.skipped_no_profile) }) }}
                    </p>

                    <template v-if="matchPreview.evaluations > 0">
                        <p class="text-gray-600 dark:text-gray-300">
                            {{ t('app.administration.ai_usage_run_matches_estimated_tokens', {
                                input: formatNumber(matchPreview.estimated_prompt_tokens),
                                output: formatNumber(matchPreview.estimated_completion_tokens),
                                total: formatNumber(matchPreview.estimated_total_tokens),
                            }) }}
                        </p>

                        <p
                            v-if="matchPreview.pricing_available"
                            class="text-gray-600 dark:text-gray-300"
                        >
                            {{ t('app.administration.ai_usage_run_matches_estimated_cost', {
                                cost: formatCost(matchPreview.estimated_cost_eur),
                            }) }}
                        </p>
                        <p
                            v-else
                            class="text-gray-500 dark:text-gray-400"
                        >
                            {{ t('app.administration.ai_usage_run_matches_no_pricing') }}
                        </p>

                        <p class="text-gray-600 dark:text-gray-300">
                            {{ t('app.administration.ai_usage_run_matches_estimated_time', {
                                duration: formatEstimatedDuration(matchPreview.estimated_seconds),
                            }) }}
                        </p>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{
                                matchPreview.uses_historical_averages
                                    ? t('app.administration.ai_usage_run_matches_uses_history')
                                    : t('app.administration.ai_usage_run_matches_uses_defaults')
                            }}
                        </p>
                    </template>

                    <p
                        v-else
                        class="text-gray-600 dark:text-gray-300"
                    >
                        {{ t('app.administration.ai_usage_run_matches_none_preview') }}
                    </p>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton
                        type="button"
                        class="justify-center"
                        :disabled="matchSubmitting"
                        @click="closeMatchModal"
                    >
                        {{ t('app.actions.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        class="justify-center"
                        :disabled="!canConfirmMatchRun"
                        @click="confirmMatchRun"
                    >
                        {{ matchSubmitting ? t('app.administration.ai_usage_run_matches_loading') : t('app.administration.ai_usage_run_matches_confirm') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
