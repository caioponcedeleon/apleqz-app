<script setup>
import JobAlertsNav from '@/Components/JobAlertsNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    matches: {
        type: Array,
        default: () => [],
    },
    canCreateApplication: {
        type: Boolean,
        default: false,
    },
});

const { t } = useI18n();
const page = usePage();

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
                <JobAlertsNav />

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="!matches.length"
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
                        v-for="match in matches"
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
                            <a
                                v-if="match.listing?.url"
                                :href="match.listing.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="sm:order-2"
                            >
                                <SecondaryButton type="button" class="w-full justify-center sm:w-auto">
                                    {{ t('app.job_alerts.view_job') }}
                                </SecondaryButton>
                            </a>
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
    </AuthenticatedLayout>
</template>
