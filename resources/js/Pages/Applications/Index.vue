<script setup>
import ApplicationImport from '@/Components/ApplicationImport.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    applications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    areas: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const statusColors = {
    a_candidatar: 'sky',
    esperando: 'amber',
    rejeitado: 'red',
    oferta: 'emerald',
    recusado: 'orange',
    retirada: 'slate',
    cancelada: 'zinc',
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const areaId = ref(props.filters.area_id ?? '');

let searchDebounce = null;
let suppressFilterWatch = false;

const applyFilters = () => {
    router.get(
        route('applications.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            area_id: areaId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const hasActiveFilters = computed(
    () => Boolean(search.value || status.value || areaId.value),
);

const clearFilters = () => {
    clearTimeout(searchDebounce);
    suppressFilterWatch = true;
    search.value = '';
    status.value = '';
    areaId.value = '';
    suppressFilterWatch = false;
    applyFilters();
};

watch([status, areaId], () => {
    if (!suppressFilterWatch) {
        applyFilters();
    }
});

watch(search, () => {
    if (suppressFilterWatch) {
        return;
    }

    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 300);
});

const deleteApplication = (id) => {
    if (!confirm(t('app.applications.delete_confirm'))) return;
    router.delete(route('applications.destroy', id));
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString();
};
</script>

<template>
    <Head :title="t('app.applications.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    {{ t('app.applications.title') }}
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <ApplicationImport />
                    <Link :href="route('applications.create')">
                        <PrimaryButton>{{ t('app.applications.new') }}</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
                >
                    {{ page.props.flash.success }}
                </p>
                <p
                    v-if="page.props.flash?.error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200"
                >
                    {{ page.props.flash.error }}
                </p>
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <TextInput
                        v-model="search"
                        class="min-w-[200px] flex-1"
                        :placeholder="t('app.applications.search')"
                    />
                    <select
                        v-model="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <option value="">{{ t('app.applications.filter_status') }}</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ t(`app.status.${s}`) }}</option>
                    </select>
                    <select
                        v-model="areaId"
                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <option value="">{{ t('app.applications.filter_area') }}</option>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <SecondaryButton
                        v-if="hasActiveFilters"
                        type="button"
                        @click="clearFilters"
                    >
                        {{ t('app.applications.clear_filters') }}
                    </SecondaryButton>
                </div>

                <div
                    v-if="!applications.data.length"
                    class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-gray-600"
                >
                    {{ t('app.applications.empty') }}
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ t('app.applications.position') }}</th>
                                <th class="px-4 py-3 text-left">{{ t('app.applications.company') }}</th>
                                <th class="px-4 py-3 text-left">{{ t('app.applications.area') }}</th>
                                <th class="px-4 py-3 text-left">{{ t('app.applications.applied_at') }}</th>
                                <th class="px-4 py-3 text-left">{{ t('app.applications.status') }}</th>
                                <th class="px-4 py-3 text-right">{{ t('app.actions.edit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="app in applications.data" :key="app.id">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    {{ app.position }}
                                </td>
                                <td class="px-4 py-3">{{ app.company }}</td>
                                <td class="px-4 py-3">{{ app.area?.name }}</td>
                                <td class="px-4 py-3">{{ formatDate(app.applied_at) }}</td>
                                <td class="px-4 py-3">
                                    <StatusBadge
                                        :status="app.status"
                                        :color="statusColors[app.status] ?? 'slate'"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <Link
                                        :href="route('applications.edit', app.id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        {{ t('app.actions.edit') }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:underline"
                                        @click="deleteApplication(app.id)"
                                    >
                                        {{ t('app.actions.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="applications.links?.length > 3"
                    class="flex flex-wrap gap-1"
                >
                    <Link
                        v-for="link in applications.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        class="rounded border px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-300 text-gray-600 dark:border-gray-600'
                        "
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
