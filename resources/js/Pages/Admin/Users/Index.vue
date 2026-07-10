<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    users: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const featureBadges = (user) => {
    const badges = [];

    if (user.is_admin) {
        badges.push(t('app.administration.users_feature_admin'));
    }

    if (user.application_files_enabled) {
        badges.push(t('app.administration.users_feature_app_files'));
    }

    if (user.personal_files_enabled) {
        badges.push(t('app.administration.users_feature_storage'));
    }

    if (user.excel_import_enabled) {
        badges.push(t('app.administration.users_feature_excel'));
    }

    return badges;
};
</script>

<template>
    <Head :title="t('app.administration.users_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ t('app.administration.users_title') }}
                </h2>
                <div class="flex items-center gap-3">
                    <Link :href="route('administration.index')">
                        <SecondaryButton type="button">{{ t('app.administration.back_to_admin') }}</SecondaryButton>
                    </Link>
                    <Link :href="route('administration.users.create')">
                        <PrimaryButton type="button">{{ t('app.administration.users_create_title') }}</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="page.props.flash?.error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >
                    {{ page.props.flash.error }}
                </div>

                <div
                    v-if="users.length === 0"
                    class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ t('app.administration.users_empty') }}
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_name') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_email') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_locale') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_features') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_job_alerts') }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.users_col_applications') }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.edit') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="user in users" :key="user.id">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ user.name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3 text-sm uppercase text-gray-600 dark:text-gray-300">
                                        {{ user.locale }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="badge in featureBadges(user)"
                                                :key="badge"
                                                class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                            >
                                                {{ badge }}
                                            </span>
                                            <span
                                                v-if="featureBadges(user).length === 0"
                                                class="text-sm text-gray-400"
                                            >
                                                —
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                            {{ user.job_alerts_tier_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">
                                        {{ user.applications_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="route('administration.users.edit', user.id)"
                                            class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            {{ t('app.administration.edit') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
