<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    matchId: { type: [String, Number], default: null },
    title: { type: String, default: '' },
    externalUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const loading = ref(false);
const errorMessage = ref('');
const suggestPlaywright = ref(false);
const usePlaywright = ref(false);
const previewFrame = ref(null);

const loadPreview = async () => {
    if (!props.matchId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';
    suggestPlaywright.value = false;

    try {
        const { data } = await window.axios.post(route('job-alerts.matches.preview', props.matchId), {
            engine: usePlaywright.value ? 'playwright' : undefined,
        });

        suggestPlaywright.value = Boolean(data.suggest_playwright);

        await nextTick();

        if (previewFrame.value) {
            previewFrame.value.srcdoc = data.html;
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            || error.response?.data?.errors?.url?.[0]
            || t('app.job_alerts.preview_error');
    } finally {
        loading.value = false;
    }
};

const retryWithPlaywright = () => {
    usePlaywright.value = true;
    loadPreview();
};

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            loading.value = false;
            errorMessage.value = '';
            suggestPlaywright.value = false;
            usePlaywright.value = false;

            if (previewFrame.value) {
                previewFrame.value.srcdoc = '';
            }

            return;
        }

        loadPreview();
    },
);
</script>

<template>
    <Modal :show="show" max-width="7xl" @close="emit('close')">
        <div class="flex max-h-[90vh] flex-col">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ title || t('app.job_alerts.preview_title') }}
                    </h3>
                    <p v-if="externalUrl" class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ externalUrl }}
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <a
                        v-if="externalUrl"
                        :href="externalUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <SecondaryButton type="button" class="w-full justify-center sm:w-auto">
                            {{ t('app.job_alerts.preview_open_external') }}
                        </SecondaryButton>
                    </a>
                    <SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="emit('close')">
                        {{ t('app.job_alerts.preview_close') }}
                    </SecondaryButton>
                </div>
            </div>

            <div class="relative min-h-[60vh] flex-1 bg-gray-100 dark:bg-gray-900">
                <div
                    v-if="loading"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 text-sm text-gray-600 dark:bg-gray-900/80 dark:text-gray-300"
                >
                    {{ t('app.job_alerts.preview_loading') }}
                </div>

                <div
                    v-else-if="errorMessage"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 px-6 text-center"
                >
                    <p class="text-sm text-red-600 dark:text-red-400">
                        {{ errorMessage }}
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <PrimaryButton type="button" @click="loadPreview">
                            {{ t('app.job_alerts.preview_retry') }}
                        </PrimaryButton>
                        <a
                            v-if="externalUrl"
                            :href="externalUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <SecondaryButton type="button">
                                {{ t('app.job_alerts.preview_open_external') }}
                            </SecondaryButton>
                        </a>
                    </div>
                </div>

                <div
                    v-else-if="suggestPlaywright && !usePlaywright"
                    class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p>{{ t('app.job_alerts.preview_suggest_playwright') }}</p>
                        <PrimaryButton type="button" class="shrink-0 justify-center" @click="retryWithPlaywright">
                            {{ t('app.job_alerts.preview_use_browser_render') }}
                        </PrimaryButton>
                    </div>
                </div>

                <iframe
                    ref="previewFrame"
                    :title="title || t('app.job_alerts.preview_title')"
                    class="h-[70vh] w-full bg-white"
                    sandbox="allow-same-origin"
                />
            </div>
        </div>
    </Modal>
</template>
