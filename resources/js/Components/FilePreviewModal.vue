<script setup>
import { renderAsync } from 'docx-preview';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    file: { type: Object, default: null },
    previewUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const docxContainer = ref(null);
const loading = ref(false);
const error = ref(false);

const extension = computed(() => {
    const name = props.file?.original_name ?? '';

    return name.includes('.') ? name.split('.').pop().toLowerCase() : '';
});

const isPdf = computed(() => extension.value === 'pdf');
const isDocx = computed(() => extension.value === 'docx');
const canPreview = computed(() => isPdf.value || isDocx.value);

const loadDocx = async () => {
    if (!docxContainer.value || !props.previewUrl) {
        return;
    }

    loading.value = true;
    error.value = false;
    docxContainer.value.innerHTML = '';

    try {
        const response = await fetch(props.previewUrl, {
            credentials: 'same-origin',
            headers: { Accept: props.file?.mime_type ?? '*/*' },
        });

        if (!response.ok) {
            throw new Error('Failed to load file');
        }

        const blob = await response.blob();

        await renderAsync(blob, docxContainer.value, null, {
            className: 'docx-preview',
            inWrapper: true,
            ignoreWidth: false,
            ignoreHeight: false,
        });
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.show, props.previewUrl, props.file?.id],
    async ([visible]) => {
        if (!visible) {
            loading.value = false;
            error.value = false;

            if (docxContainer.value) {
                docxContainer.value.innerHTML = '';
            }

            return;
        }

        if (isDocx.value) {
            await nextTick();
            await loadDocx();
        }
    },
);

const close = () => {
    emit('close');
};

const onBackdropClick = (event) => {
    if (event.target === event.currentTarget) {
        close();
    }
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && file"
            class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/80 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-label="file.original_name"
            @click="onBackdropClick"
            @keydown.escape="close"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                @click.stop
            >
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="min-w-0 truncate text-sm font-semibold text-slate-900 dark:text-white">
                        {{ file.original_name }}
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="close"
                    >
                        {{ t('app.files.close_preview') }}
                    </button>
                </div>

                <div class="relative min-h-[50vh] flex-1 overflow-auto bg-slate-100 p-4 dark:bg-slate-950">
                    <div
                        v-if="!canPreview"
                        class="flex h-full min-h-[40vh] items-center justify-center text-sm text-slate-500"
                    >
                        {{ t('app.files.preview_unsupported') }}
                    </div>

                    <iframe
                        v-else-if="isPdf"
                        :src="previewUrl"
                        :title="file.original_name"
                        class="h-[min(75vh,800px)] w-full rounded-lg border border-slate-200 bg-white dark:border-slate-700"
                    />

                    <template v-else-if="isDocx">
                        <div
                            v-if="loading"
                            class="flex min-h-[40vh] items-center justify-center text-sm text-slate-500"
                        >
                            {{ t('app.files.preview_loading') }}
                        </div>
                        <p
                            v-else-if="error"
                            class="flex min-h-[40vh] items-center justify-center text-center text-sm text-red-600 dark:text-red-400"
                        >
                            {{ t('app.files.preview_error') }}
                        </p>
                        <div
                            v-show="!loading && !error"
                            ref="docxContainer"
                            class="docx-wrapper mx-auto max-w-4xl overflow-auto rounded-lg bg-white p-4 shadow-sm"
                        />
                    </template>
                </div>
            </div>
        </div>
    </Teleport>
</template>
