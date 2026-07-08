<script setup>
import FilePreviewModal from '@/Components/FilePreviewModal.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    files: { type: Array, default: () => [] },
    uploadUrl: { type: String, required: true },
    downloadUrl: { type: Function, required: true },
    previewUrl: { type: Function, required: true },
    deleteUrl: { type: Function, required: true },
    disabled: { type: Boolean, default: false },
    multiple: { type: Boolean, default: false },
});

const { t } = useI18n();
const uploading = ref(false);
const fileInput = ref(null);
const previewFile = ref(null);
const showPreview = ref(false);

const formatSize = (bytes) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDate = (value) => {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
};

const canPreview = (file) => {
    const name = file.original_name?.toLowerCase() ?? '';

    return name.endsWith('.pdf') || name.endsWith('.docx');
};

const openPreview = (file) => {
    previewFile.value = file;
    showPreview.value = true;
};

const closePreview = () => {
    showPreview.value = false;
    previewFile.value = null;
};

const pickFile = () => {
    if (!props.disabled && !uploading.value) {
        fileInput.value?.click();
    }
};

const onFileSelected = (event) => {
    const files = Array.from(event.target.files ?? []);

    if (!files.length || props.disabled) {
        return;
    }

    uploading.value = true;

    const payload = props.multiple
        ? { files }
        : { file: files[0] };

    router.post(
        props.uploadUrl,
        payload,
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploading.value = false;

                if (fileInput.value) {
                    fileInput.value.value = '';
                }
            },
        },
    );
};

const deleteFile = (file) => {
    if (props.disabled || !confirm(t('app.files.delete_confirm'))) {
        return;
    }

    router.delete(props.deleteUrl(file), { preserveScroll: true });
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="!disabled"
            class="flex flex-wrap items-center gap-3"
        >
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :multiple="multiple"
                accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                @change="onFileSelected"
            />
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                :disabled="uploading"
                @click="pickFile"
            >
                {{ uploading ? t('app.files.uploading') : t('app.files.upload') }}
            </button>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ t('app.files.allowed_types') }}
            </p>
        </div>

        <p
            v-if="!files.length"
            class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
        >
            {{ t('app.files.empty') }}
        </p>

        <ul
            v-else
            class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700"
        >
            <li
                v-for="file in files"
                :key="file.id"
                class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-900 dark:text-white">
                        {{ file.original_name }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatSize(file.size) }}
                        ·
                        {{ formatDate(file.created_at) }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <button
                        v-if="canPreview(file)"
                        type="button"
                        class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-950/70"
                        @click="openPreview(file)"
                    >
                        {{ t('app.files.view') }}
                    </button>
                    <a
                        :href="downloadUrl(file)"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        {{ t('app.files.download') }}
                    </a>
                    <button
                        v-if="!disabled"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                        @click="deleteFile(file)"
                    >
                        {{ t('app.files.delete') }}
                    </button>
                </div>
            </li>
        </ul>

        <FilePreviewModal
            :show="showPreview"
            :file="previewFile"
            :preview-url="previewFile ? previewUrl(previewFile) : ''"
            @close="closePreview"
        />
    </div>
</template>
