<script setup>
import AreaManager from '@/Components/AreaManager.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    areas: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('app.areas.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ t('app.areas.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p
                    class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-sm leading-relaxed text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400"
                >
                    {{ t('app.applications.area_explanation') }}
                </p>

                <AreaManager :areas="areas" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
