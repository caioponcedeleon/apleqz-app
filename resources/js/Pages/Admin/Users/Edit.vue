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
    user: { type: Object, required: true },
    localeOptions: { type: Array, default: () => [] },
    jobAlertsTiers: { type: Array, default: () => [] },
});

const { t } = useI18n();
const showDeleteModal = ref(false);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    email_verified_at: props.user.email_verified_at || '',
    locale: props.user.locale,
    is_admin: props.user.is_admin,
    application_files_enabled: props.user.application_files_enabled,
    personal_files_enabled: props.user.personal_files_enabled,
    excel_import_enabled: props.user.excel_import_enabled,
    job_alerts_tier: props.user.job_alerts_tier,
});

const submit = () => {
    form.put(route('administration.users.update', props.user.id));
};

const confirmDelete = () => {
    router.delete(route('administration.users.destroy', props.user.id));
};
</script>

<template>
    <Head :title="t('app.administration.users_edit_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.administration.users_edit_title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_name') }}
                            </label>
                            <TextInput v-model="form.name" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_email') }}
                            </label>
                            <TextInput v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_password') }}
                            </label>
                            <TextInput v-model="form.password" type="password" class="mt-1 block w-full" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('app.administration.users_password_help') }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_email_verified_at') }}
                            </label>
                            <TextInput v-model="form.email_verified_at" type="datetime-local" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.email_verified_at" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_locale') }}
                            </label>
                            <select
                                v-model="form.locale"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option v-for="locale in localeOptions" :key="locale.value" :value="locale.value">
                                    {{ locale.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.locale" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.administration.users_job_alerts_tier') }}
                            </label>
                            <select
                                v-model="form.job_alerts_tier"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option v-for="tier in jobAlertsTiers" :key="tier.value" :value="tier.value">
                                    {{ tier.label }}
                                </option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('app.administration.users_job_alerts_tier_help') }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.job_alerts_tier" />
                        </div>

                        <div class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <ToggleSwitch v-model="form.is_admin" :label="t('app.administration.users_is_admin')" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('app.administration.users_is_admin_help') }}</p>
                            <ToggleSwitch v-model="form.application_files_enabled" :label="t('app.administration.users_application_files')" />
                            <ToggleSwitch v-model="form.personal_files_enabled" :label="t('app.administration.users_personal_files')" />
                            <ToggleSwitch v-model="form.excel_import_enabled" :label="t('app.administration.users_excel_import')" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.administration.save') }}
                            </PrimaryButton>
                            <Link :href="route('administration.users.index')">
                                <SecondaryButton type="button">{{ t('app.administration.cancel') }}</SecondaryButton>
                            </Link>
                            <DangerButton type="button" @click="showDeleteModal = true">
                                {{ t('app.administration.delete') }}
                            </DangerButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmDeleteModal
            :show="showDeleteModal"
            :title="t('app.administration.delete_confirm_title')"
            :message="t('app.administration.delete_confirm_message')"
            :item-name="user.name"
            @close="showDeleteModal = false"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
