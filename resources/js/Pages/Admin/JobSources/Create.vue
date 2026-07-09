<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    url: '',
    company_name: '',
    is_active: false,
});

const submit = () => {
    form.post(route('job-sources.store'));
};
</script>

<template>
    <Head :title="t('app.job_sources.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.job_sources.create_title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.create_help') }}
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="submit">
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

                        <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                            <input v-model="form.is_active" type="checkbox" class="mt-1 rounded border-gray-300 text-indigo-600" />
                            <span>
                                <span class="font-medium">{{ t('app.job_sources.active') }}</span>
                                <span class="mt-1 block text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_sources.active_help') }}
                                </span>
                            </span>
                        </label>

                        <div class="flex items-center gap-3">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.job_sources.create_and_configure') }}
                            </PrimaryButton>
                            <Link :href="route('job-sources.index')">
                                <SecondaryButton type="button">{{ t('app.job_sources.cancel') }}</SecondaryButton>
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
