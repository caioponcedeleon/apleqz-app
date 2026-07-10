<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    localeOptions: { type: Array, default: () => [] },
});

const { t } = useI18n();

const initialValues = Object.fromEntries(props.localeOptions.map((locale) => [locale.value, '']));

const form = useForm({
    group: '',
    key: '',
    values: { ...initialValues },
});

const submit = () => {
    form.post(route('administration.translations.store'));
};
</script>

<template>
    <Head :title="t('app.administration.translations_create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.administration.translations_create_title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.translations_group') }}
                            </label>
                            <TextInput v-model="form.group" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.group" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.translations_key') }}
                            </label>
                            <TextInput v-model="form.key" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.key" />
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ t('app.administration.translations_values_heading') }}
                            </h3>

                            <div v-for="locale in localeOptions" :key="locale.value">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ locale.label }}
                                </label>
                                <textarea
                                    v-model="form.values[locale.value]"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                                <InputError class="mt-2" :message="form.errors[`values.${locale.value}`]" />
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.administration.create') }}
                            </PrimaryButton>
                            <Link :href="route('administration.translations.index')">
                                <SecondaryButton type="button">{{ t('app.administration.cancel') }}</SecondaryButton>
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
