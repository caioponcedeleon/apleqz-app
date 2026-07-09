<script setup>
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    jobSource: { type: Object, required: true },
});

const { t } = useI18n();

const showDeleteModal = ref(false);

const form = useForm({
    name: props.jobSource.name,
    url: props.jobSource.url,
    company_name: props.jobSource.company_name || '',
    is_active: props.jobSource.is_active,
});

const submit = () => {
    form.put(route('job-sources.update', props.jobSource.id));
};

const confirmDelete = () => {
    router.delete(route('job-sources.destroy', props.jobSource.id));
};
</script>

<template>
    <Head :title="t('app.job_sources.edit_title', { name: jobSource.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ t('app.job_sources.edit_title', { name: jobSource.name }) }}
                </h2>
                <Link :href="route('job-sources.configure', jobSource.id)">
                    <SecondaryButton type="button">
                        {{ t('app.job_sources.configure') }}
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.job_sources.name') }}
                            </label>
                            <TextInput v-model="form.name" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.job_sources.url') }}
                            </label>
                            <TextInput v-model="form.url" type="url" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.url" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.job_sources.company_name') }}
                            </label>
                            <TextInput v-model="form.company_name" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.company_name" />
                        </div>

                        <div>
                            <ToggleSwitch
                                v-model="form.is_active"
                                :label="t('app.job_sources.active')"
                            />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('app.job_sources.active_help') }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.is_active" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.job_sources.save') }}
                            </PrimaryButton>
                            <Link :href="route('job-sources.index')">
                                <SecondaryButton type="button">{{ t('app.job_sources.cancel') }}</SecondaryButton>
                            </Link>
                            <DangerButton type="button" @click="showDeleteModal = true">
                                {{ t('app.job_sources.delete') }}
                            </DangerButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmDeleteModal
            :show="showDeleteModal"
            :title="t('app.job_sources.delete_title')"
            :message="t('app.job_sources.delete_message')"
            :item-name="jobSource.name"
            @close="showDeleteModal = false"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
