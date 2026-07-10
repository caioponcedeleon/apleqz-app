<script setup>
import JobAlertsNav from '@/Components/JobAlertsNav.vue';
import JobListingPreviewModal from '@/Components/JobListingPreviewModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    matches: {
        type: Array,
        default: () => [],
    },
    canCreateApplication: {
        type: Boolean,
        default: false,
    },
    canRunMatches: {
        type: Boolean,
        default: false,
    },
});

const { t } = useI18n();
const page = usePage();
const runningMatches = ref(false);
const previewMatch = ref(null);
const localMatches = ref([...props.matches]);

watch(
    () => props.matches,
    (matches) => {
        if (!runningMatches.value) {
            localMatches.value = [...matches];
        }
    },
);

const previewableMatches = computed(() =>
    localMatches.value.filter((match) => Boolean(match.listing?.url)),
);

const removeMatch = (matchId) => {
    localMatches.value = localMatches.value.filter((match) => match.id !== matchId);
};

const openPreview = (match) => {
    if (!match.listing?.url) {
        return;
    }

    previewMatch.value = match;
};

const closePreview = () => {
    previewMatch.value = null;
};

const openNextPreview = (currentMatchId) => {
    const remaining = previewableMatches.value.filter((match) => match.id !== currentMatchId);
    previewMatch.value = remaining[0] ?? null;
};

const handlePreviewDismiss = ({ matchId, advance }) => {
    removeMatch(matchId);

    if (advance) {
        openNextPreview(matchId);
        return;
    }

    closePreview();
};

const handleSavedForLater = ({ matchId }) => {
    removeMatch(matchId);
};

const handleSeeNext = () => {
    previewMatch.value = previewableMatches.value[0] ?? null;
};

const runMatches = () => {
    runningMatches.value = true;
    localMatches.value = [];

    router.post(route('job-alerts.matches.run'), {}, {
        preserveScroll: true,
        onFinish: () => {
            runningMatches.value = false;
            localMatches.value = [...props.matches];
        },
    });
};

const dismissMatch = (matchId) => {
    router.patch(route('job-alerts.matches.dismiss', matchId), {}, {
        preserveScroll: true,
    });
};

const scoreClass = (score) => {
    if (score >= 85) {
        return 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200';
    }

    if (score >= 70) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
};
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
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <JobAlertsNav />

                    <div v-if="canRunMatches" class="sm:pt-1">
                        <SecondaryButton
                            type="button"
                            class="w-full justify-center sm:w-auto"
                            :disabled="runningMatches"
                            @click="runMatches"
                        >
                            {{ runningMatches ? t('app.job_alerts.run_matches_running') : t('app.job_alerts.run_matches') }}
                        </SecondaryButton>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ t('app.job_alerts.run_matches_help') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="page.props.flash?.warning"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {{ page.props.flash.warning }}
                </div>

                <div
                    v-if="runningMatches"
                    class="rounded-xl border border-indigo-200 bg-white p-12 text-center shadow-sm dark:border-indigo-900/50 dark:bg-gray-800"
                    role="status"
                    aria-live="polite"
                >
                    <svg
                        class="mx-auto size-8 animate-spin text-indigo-600 dark:text-indigo-400"
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
                    <p class="mt-4 text-sm font-medium text-gray-900 dark:text-white">
                        {{ t('app.job_alerts.run_matches_loading') }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_alerts.run_matches_loading_help') }}
                    </p>
                </div>

                <div
                    v-else-if="!localMatches.length"
                    class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_alerts.matches_empty_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_alerts.matches_empty_help') }}
                    </p>
                </div>

                <ul v-else class="space-y-4">
                    <li
                        v-for="match in localMatches"
                        :key="match.id"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="scoreClass(match.fit_score)"
                                >
                                    {{ t('app.job_alerts.match_score', { score: match.fit_score }) }}
                                </span>
                                <span
                                    v-if="match.listing?.company"
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ match.listing.company }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-white">
                                <a
                                    v-if="match.listing?.url"
                                    :href="match.listing.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-400"
                                >
                                    {{ match.listing.title }}
                                </a>
                                <span v-else>{{ match.listing?.title }}</span>
                            </h3>

                            <p
                                v-if="match.listing?.location"
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ match.listing.location }}
                            </p>

                            <p class="mt-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                {{ match.reason }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:flex-wrap dark:border-gray-700 dark:bg-gray-900/40">
                            <Link
                                v-if="canCreateApplication"
                                :href="route('job-alerts.matches.apply', match.id)"
                                class="sm:order-1"
                            >
                                <PrimaryButton type="button" class="w-full justify-center sm:w-auto">
                                    {{ t('app.job_alerts.track_application') }}
                                </PrimaryButton>
                            </Link>
                            <SecondaryButton
                                v-if="match.listing?.url"
                                type="button"
                                class="w-full justify-center sm:order-2 sm:w-auto"
                                @click="openPreview(match)"
                            >
                                {{ t('app.job_alerts.view_job') }}
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                class="w-full justify-center sm:order-3 sm:w-auto"
                                @click="dismissMatch(match.id)"
                            >
                                {{ t('app.job_alerts.dismiss') }}
                            </SecondaryButton>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <JobListingPreviewModal
            :show="previewMatch !== null"
            :match-id="previewMatch?.id ?? null"
            :title="previewMatch?.listing?.title ?? ''"
            :external-url="previewMatch?.listing?.url ?? ''"
            :can-create-application="canCreateApplication"
            @close="closePreview"
            @dismiss="handlePreviewDismiss"
            @saved-for-later="handleSavedForLater"
            @see-next="handleSeeNext"
        />
    </AuthenticatedLayout>
</template>
