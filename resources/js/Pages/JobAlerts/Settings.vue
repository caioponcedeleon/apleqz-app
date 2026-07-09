<script setup>
import InputError from '@/Components/InputError.vue';
import JobAlertsNav from '@/Components/JobAlertsNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    sources: {
        type: Array,
        default: () => [],
    },
    subscribedSourceIds: {
        type: Array,
        default: () => [],
    },
    emailVerified: {
        type: Boolean,
        default: true,
    },
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    profileTextMaxLength: {
        type: Number,
        default: 200,
    },
});

const { t } = useI18n();
const page = usePage();

const form = useForm({
    profile_text: props.profile.profile_text ?? '',
    min_fit_score: props.profile.min_fit_score ?? 70,
    job_alerts_enabled: props.profile.job_alerts_enabled ?? false,
    subscribed_source_ids: [...props.subscribedSourceIds],
});

const canEnableEmailAlerts = computed(() => props.emailVerified);

const emailAlertsEnabled = computed({
    get: () => form.job_alerts_enabled,
    set: (value) => {
        if (canEnableEmailAlerts.value) {
            form.job_alerts_enabled = value;
        }
    },
});

const toggleSource = (sourceId) => {
    const ids = new Set(form.subscribed_source_ids);

    if (ids.has(sourceId)) {
        ids.delete(sourceId);
    } else {
        ids.add(sourceId);
    }

    form.subscribed_source_ids = [...ids];
};

const isSourceSubscribed = (sourceId) => form.subscribed_source_ids.includes(sourceId);

const companyGroups = computed(() => {
    const groups = new Map();

    for (const source of props.sources) {
        const company = typeof source.company_name === 'string' ? source.company_name.trim() : '';
        const key = company || '__none__';

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                company,
                sources: [],
            });
        }

        groups.get(key).sources.push(source);
    }

    return [...groups.values()].sort((left, right) => {
        const leftLabel = left.company || t('app.job_alerts.no_company');
        const rightLabel = right.company || t('app.job_alerts.no_company');

        return leftLabel.localeCompare(rightLabel, undefined, { sensitivity: 'base' });
    });
});

const expandedCompanies = ref(new Set(
    companyGroups.value
        .filter((group) => group.sources.length > 1)
        .filter((group) => group.sources.some((source) => props.subscribedSourceIds.includes(source.id)))
        .map((group) => group.key),
));

const companyLabel = (company) => company || t('app.job_alerts.no_company');

const isCompanyExpanded = (group) => group.sources.length === 1 || expandedCompanies.value.has(group.key);

const toggleCompanyExpanded = (group) => {
    if (group.sources.length === 1) {
        return;
    }

    const next = new Set(expandedCompanies.value);

    if (next.has(group.key)) {
        next.delete(group.key);
    } else {
        next.add(group.key);
    }

    expandedCompanies.value = next;
};

const companySourceIds = (group) => group.sources.map((source) => source.id);

const companyAllSelected = (group) => companySourceIds(group).every((id) => isSourceSubscribed(id));

const companySomeSelected = (group) => companySourceIds(group).some((id) => isSourceSubscribed(id));

const companyIndeterminate = (group) => companySomeSelected(group) && !companyAllSelected(group);

const syncCompanyCheckbox = (element, group) => {
    if (element) {
        element.indeterminate = companyIndeterminate(group);
    }
};

const toggleCompany = (group) => {
    const ids = new Set(form.subscribed_source_ids);
    const sourceIds = companySourceIds(group);
    const selectAll = !companyAllSelected(group);

    for (const sourceId of sourceIds) {
        if (selectAll) {
            ids.add(sourceId);
        } else {
            ids.delete(sourceId);
        }
    }

    form.subscribed_source_ids = [...ids];

    if (selectAll && group.sources.length > 1) {
        expandedCompanies.value = new Set([...expandedCompanies.value, group.key]);
    }
};

const toggleSingleSourceGroup = (group) => {
    toggleSource(group.sources[0].id);
};

const profileTextLength = computed(() => form.profile_text.length);

const submit = () => {
    form.patch(route('job-alerts.settings.update'));
};
</script>

<template>
    <Head :title="t('app.job_alerts.settings_title')" />

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

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form class="space-y-8" @submit.prevent="submit">
                        <section class="space-y-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ t('app.job_alerts.profile_heading') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_alerts.profile_help') }}
                                </p>
                            </div>

                            <textarea
                                v-model="form.profile_text"
                                rows="4"
                                :maxlength="profileTextMaxLength"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                :placeholder="t('app.job_alerts.profile_placeholder')"
                            />
                            <p class="text-right text-xs text-gray-500 dark:text-gray-400">
                                {{ t('app.job_alerts.profile_char_count', {
                                    count: profileTextLength,
                                    max: profileTextMaxLength,
                                }) }}
                            </p>
                            <InputError :message="form.errors.profile_text" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('app.job_alerts.profile_ai_privacy') }}
                            </p>
                        </section>

                        <section class="space-y-3">
                            <div>
                                <label
                                    for="min_fit_score"
                                    class="text-base font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ t('app.job_alerts.min_fit_score') }}
                                    <span class="ms-2 font-mono text-sm text-indigo-600 dark:text-indigo-400">
                                        {{ form.min_fit_score }}
                                    </span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_alerts.min_fit_score_help') }}
                                </p>
                            </div>

                            <input
                                id="min_fit_score"
                                v-model.number="form.min_fit_score"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                class="w-full accent-indigo-600"
                            />
                            <InputError :message="form.errors.min_fit_score" />
                        </section>

                        <section class="space-y-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ t('app.job_alerts.email_enabled') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_alerts.email_enabled_help') }}
                                </p>
                            </div>

                            <ToggleSwitch
                                v-model="emailAlertsEnabled"
                                :label="t('app.job_alerts.email_enabled')"
                                :class="{ 'pointer-events-none opacity-50': !canEnableEmailAlerts }"
                            />

                            <p
                                v-if="mustVerifyEmail && !emailVerified"
                                class="text-sm text-amber-700 dark:text-amber-300"
                            >
                                {{ t('app.job_alerts.email_verification_required') }}
                                <Link
                                    :href="route('verification.notice')"
                                    class="font-medium underline"
                                >
                                    {{ t('app.job_alerts.verify_email_link') }}
                                </Link>
                            </p>

                            <InputError :message="form.errors.job_alerts_enabled" />
                        </section>

                        <section class="space-y-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ t('app.job_alerts.sources_heading') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_alerts.sources_help') }}
                                </p>
                            </div>

                            <p
                                v-if="!sources.length"
                                class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
                            >
                                {{ t('app.job_alerts.sources_empty') }}
                            </p>

                            <div
                                v-else
                                class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700"
                            >
                                <section
                                    v-for="group in companyGroups"
                                    :key="group.key"
                                >
                                    <div class="flex items-start gap-3 px-4 py-3">
                                        <input
                                            :id="`company-${group.key}`"
                                            type="checkbox"
                                            class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                            :checked="companyAllSelected(group)"
                                            :ref="(element) => syncCompanyCheckbox(element, group)"
                                            @change="group.sources.length === 1 ? toggleSingleSourceGroup(group) : toggleCompany(group)"
                                        />

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <label
                                                    :for="`company-${group.key}`"
                                                    class="cursor-pointer"
                                                >
                                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ companyLabel(group.company) }}
                                                    </span>
                                                    <span
                                                        v-if="group.sources.length > 1"
                                                        class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400"
                                                    >
                                                        {{ t('app.job_alerts.sources_company_count', { count: group.sources.length }) }}
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400"
                                                    >
                                                        {{ group.sources[0].name }}
                                                    </span>
                                                </label>

                                                <button
                                                    v-if="group.sources.length > 1"
                                                    type="button"
                                                    class="shrink-0 rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                                    :aria-expanded="isCompanyExpanded(group)"
                                                    :aria-label="isCompanyExpanded(group)
                                                        ? t('app.job_alerts.sources_collapse_company', { company: companyLabel(group.company) })
                                                        : t('app.job_alerts.sources_expand_company', { company: companyLabel(group.company) })"
                                                    @click="toggleCompanyExpanded(group)"
                                                >
                                                    <svg
                                                        class="size-5 transition-transform"
                                                        :class="{ 'rotate-180': isCompanyExpanded(group) }"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                        aria-hidden="true"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>

                                            <ul
                                                v-if="group.sources.length > 1 && isCompanyExpanded(group)"
                                                class="mt-3 space-y-2 border-l border-gray-200 ps-4 dark:border-gray-700"
                                            >
                                                <li
                                                    v-for="source in group.sources"
                                                    :key="source.id"
                                                    class="flex items-start gap-3"
                                                >
                                                    <input
                                                        :id="`source-${source.id}`"
                                                        type="checkbox"
                                                        class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                                        :checked="isSourceSubscribed(source.id)"
                                                        @change="toggleSource(source.id)"
                                                    />
                                                    <label
                                                        :for="`source-${source.id}`"
                                                        class="min-w-0 flex-1 cursor-pointer text-sm text-gray-700 dark:text-gray-300"
                                                    >
                                                        {{ source.name }}
                                                    </label>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <InputError :message="form.errors.subscribed_source_ids" />
                        </section>

                        <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.job_alerts.save') }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
