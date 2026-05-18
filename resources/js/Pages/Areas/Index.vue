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
        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <AreaManager :areas="areas" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
