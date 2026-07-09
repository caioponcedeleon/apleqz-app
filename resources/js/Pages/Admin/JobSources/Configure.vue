<script setup>
import JobSourceConfigurator from '@/Components/JobSourceConfigurator.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    jobSource: { type: Object, required: true },
    previewUrl: { type: String, required: true },
    itemSelector: { type: String, default: '' },
    itemMode: { type: String, default: 'single' },
    itemGroup: { type: Array, default: () => [] },
    fieldMappings: { type: Object, default: () => ({}) },
    pagination: {
        type: Object,
        default: () => ({ type: 'none', param: 'page', max_pages: 10 }),
    },
    engine: { type: String, default: 'http' },
    interactions: { type: Array, default: () => [] },
    fieldOptions: { type: Object, required: true },
    requiredFields: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();
</script>

<template>
    <Head :title="t('app.job_sources.configure_title', { name: jobSource.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <Link :href="route('job-sources.index')" class="hover:underline">
                            {{ t('app.job_sources.title') }}
                        </Link>
                        <span class="mx-2">/</span>
                        <Link :href="route('job-sources.edit', jobSource.id)" class="hover:underline">
                            {{ jobSource.name }}
                        </Link>
                    </p>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ t('app.job_sources.configure_title', { name: jobSource.name }) }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <JobSourceConfigurator
                    :job-source="jobSource"
                    :preview-url="previewUrl"
                    :item-selector="itemSelector"
                    :item-mode="itemMode"
                    :item-group="itemGroup"
                    :field-mappings="fieldMappings"
                    :pagination="pagination"
                    :engine="engine"
                    :interactions="interactions"
                    :field-options="fieldOptions"
                    :required-fields="requiredFields"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
