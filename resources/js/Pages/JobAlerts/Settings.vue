<script setup>
import InputError from '@/Components/InputError.vue';
import JobAlertsNav from '@/Components/JobAlertsNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

                            <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                                <li
                                    v-for="source in sources"
                                    :key="source.id"
                                    class="flex items-start gap-3 px-4 py-3"
                                >
                                    <input
                                        :id="`source-${source.id}`"
                                        type="checkbox"
                                        class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                        :checked="isSourceSubscribed(source.id)"
                                        @change="toggleSource(source.id)"
                                    />
                                    <label
                                        :for="`source-${source.id}`"
                                        class="min-w-0 flex-1 cursor-pointer"
                                    >
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                            {{ source.name }}
                                        </span>
                                        <span
                                            v-if="source.company_name"
                                            class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ source.company_name }}
                                        </span>
                                    </label>
                                </li>
                            </ul>

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
