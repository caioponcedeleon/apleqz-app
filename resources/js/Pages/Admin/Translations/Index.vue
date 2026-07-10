<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SyncedHorizontalScroll from '@/Components/SyncedHorizontalScroll.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    translationLines: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    localeOptions: { type: Array, default: () => [] },
    filterGroup: { type: String, default: null },
});

const { t } = useI18n();
const page = usePage();
const selectedGroup = ref(props.filterGroup || '');

watch(
    () => props.filterGroup,
    (value) => {
        selectedGroup.value = value || '';
    },
);

const applyFilter = () => {
    router.get(
        route('administration.translations.index'),
        selectedGroup.value ? { group: selectedGroup.value } : {},
        { preserveState: true, replace: true },
    );
};

const previewText = (value) => {
    if (!value) {
        return '—';
    }

    return value.length > 80 ? `${value.slice(0, 80)}…` : value;
};
</script>

<template>
    <Head :title="t('app.administration.translations_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ t('app.administration.translations_title') }}
                </h2>
                <div class="flex items-center gap-3">
                    <Link :href="route('administration.index')">
                        <SecondaryButton type="button">{{ t('app.administration.back_to_admin') }}</SecondaryButton>
                    </Link>
                    <Link :href="route('administration.translations.create')">
                        <PrimaryButton type="button">{{ t('app.administration.translations_create_title') }}</PrimaryButton>
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

                <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('app.administration.translations_filter_group') }}
                        </label>
                        <select
                            v-model="selectedGroup"
                            class="mt-1 block min-w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            @change="applyFilter"
                        >
                            <option value="">{{ t('app.administration.translations_filter_all') }}</option>
                            <option v-for="group in groups" :key="group" :value="group">
                                {{ group }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="translationLines.length === 0"
                    class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ t('app.administration.translations_empty') }}
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <SyncedHorizontalScroll :watch-key="`${selectedGroup}-${translationLines.length}`">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.translations_col_key') }}
                                    </th>
                                    <th
                                        v-for="locale in localeOptions"
                                        :key="locale.value"
                                        class="min-w-[12rem] px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        {{ locale.label }}
                                    </th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.administration.edit') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="translation in translationLines" :key="translation.id">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">
                                        {{ translation.full_key }}
                                    </td>
                                    <td
                                        v-for="locale in localeOptions"
                                        :key="`${translation.id}-${locale.value}`"
                                        class="min-w-[12rem] px-4 py-3 text-sm text-gray-600 dark:text-gray-300"
                                    >
                                        {{ previewText(translation.previews?.[locale.value]) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <Link
                                            :href="route('administration.translations.edit', translation.id)"
                                            class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            {{ t('app.administration.edit') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </SyncedHorizontalScroll>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
