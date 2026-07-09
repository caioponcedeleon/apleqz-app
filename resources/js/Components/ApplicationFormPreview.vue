<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusSelector from '@/Components/StatusSelector.vue';
import TextInput from '@/Components/TextInput.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();

const previewStatus = 'esperando';
</script>

<template>
    <div
        class="pointer-events-none select-none space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        aria-hidden="true"
    >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-dashed border-gray-200 pb-4 dark:border-gray-700">
            <span
                class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
            >
                {{ t('app.onboarding.preview.badge') }}
            </span>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.onboarding.preview.disclaimer') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel :value="t('app.applications.wave')" />
                <div
                    class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-200"
                >
                    {{ t('app.onboarding.preview.wave') }}
                </div>
            </div>

            <div>
                <InputLabel :value="t('app.applications.area')" />
                <div
                    class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-200"
                >
                    {{ t('app.onboarding.preview.area') }}
                </div>
            </div>

            <div>
                <InputLabel :value="t('app.applications.position')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.position')"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div>
                <InputLabel :value="t('app.applications.company')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.company')"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div>
                <InputLabel :value="t('app.applications.location')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.location')"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div>
                <InputLabel :value="t('app.applications.applied_at')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.applied_at')"
                    type="date"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div class="sm:col-span-2">
                <InputLabel :value="t('app.applications.status')" />
                <StatusSelector
                    :model-value="previewStatus"
                    :statuses="statuses"
                    class="mt-2"
                />
            </div>

            <div>
                <InputLabel :value="t('app.applications.channel')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.channel')"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div class="sm:col-span-2">
                <InputLabel :value="t('app.applications.job_url')" />
                <TextInput
                    :model-value="t('app.onboarding.preview.job_url')"
                    type="url"
                    class="mt-1 block w-full"
                    readonly
                    tabindex="-1"
                />
            </div>

            <div class="sm:col-span-2">
                <InputLabel :value="t('app.applications.notes')" />
                <textarea
                    :value="t('app.onboarding.preview.notes')"
                    rows="3"
                    readonly
                    tabindex="-1"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-900/60 dark:text-gray-200"
                />
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
            <div class="mb-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ t('app.applications.moments_title') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('app.applications.moments_hint') }}
                </p>
            </div>

            <p
                class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
            >
                {{ t('app.applications.moments_empty') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 opacity-60 dark:border-gray-700">
            <PrimaryButton type="button" disabled>
                {{ t('app.actions.save') }}
            </PrimaryButton>
            <SecondaryButton type="button" disabled>
                {{ t('app.actions.save_and_create_another') }}
            </SecondaryButton>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.actions.cancel') }}
            </span>
        </div>
    </div>
</template>
