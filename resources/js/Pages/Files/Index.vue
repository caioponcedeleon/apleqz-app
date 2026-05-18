<script setup>
import FileManager from '@/Components/FileManager.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    files: { type: Array, default: () => [] },
});

const { t } = useI18n();

const downloadUrl = (file) => route('files.download', file.id);
const previewUrl = (file) => route('files.preview', file.id);
const deleteUrl = (file) => route('files.destroy', file.id);
</script>

<template>
    <Head :title="t('app.files.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ t('app.files.title') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="mb-6 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.files.subtitle') }}
                    </p>

                    <FileManager
                        :files="files"
                        :upload-url="route('files.store')"
                        :download-url="downloadUrl"
                        :preview-url="previewUrl"
                        :delete-url="deleteUrl"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
