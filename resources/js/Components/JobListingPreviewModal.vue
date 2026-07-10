<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    matchId: { type: [String, Number], default: null },
    title: { type: String, default: '' },
    externalUrl: { type: String, default: '' },
    canCreateApplication: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'dismiss', 'saved-for-later', 'see-next']);

const { t } = useI18n();

const loading = ref(false);
const actionLoading = ref(false);
const savedForLater = ref(false);
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

const saveForLater = async () => {
    if (!props.matchId || actionLoading.value) {
        return;
    }

    actionLoading.value = true;
    errorMessage.value = '';

    try {
        await window.axios.post(route('job-alerts.matches.save-for-later', props.matchId));

        savedForLater.value = true;
        emit('saved-for-later', { matchId: props.matchId });
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            || error.response?.data?.errors?.application?.[0]
            || error.response?.data?.errors?.job_match?.[0]
            || t('app.job_alerts.save_for_later_error');
    } finally {
        actionLoading.value = false;
    }
};

const dismissMatch = async (advance) => {
    if (!props.matchId || actionLoading.value) {
        return;
    }

    actionLoading.value = true;
    errorMessage.value = '';

    try {
        await window.axios.patch(route('job-alerts.matches.dismiss', props.matchId));

        emit('dismiss', { matchId: props.matchId, advance });
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('app.job_alerts.preview_error');
    } finally {
        actionLoading.value = false;
    }
};

const seeNext = () => {
    emit('see-next');
};

watch(
    () => props.matchId,
    (matchId, oldMatchId) => {
        savedForLater.value = false;

        if (!props.show || !matchId || oldMatchId === undefined || oldMatchId === matchId) {
            return;
        }

        loadPreview();
    },
);

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            loading.value = false;
            actionLoading.value = false;
            savedForLater.value = false;
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
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ title || t('app.job_alerts.preview_title') }}
                        </h3>
                        <p v-if="externalUrl" class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ externalUrl }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <template v-if="savedForLater">
                            <PrimaryButton
                                type="button"
                                class="justify-center"
                                @click="seeNext"
                            >
                                {{ t('app.job_alerts.preview_see_next') }}
                            </PrimaryButton>
                        </template>
                        <template v-else>
                            <PrimaryButton
                                v-if="canCreateApplication"
                                type="button"
                                class="justify-center"
                                :disabled="actionLoading"
                                @click="saveForLater"
                            >
                                {{ t('app.job_alerts.preview_save_for_later') }}
                            </PrimaryButton>
                            <SecondaryButton
                                type="button"
                                class="justify-center"
                                :disabled="actionLoading"
                                @click="dismissMatch(true)"
                            >
                                {{ t('app.job_alerts.preview_dismiss_next') }}
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                class="justify-center"
                                :disabled="actionLoading"
                                @click="dismissMatch(false)"
                            >
                                {{ t('app.job_alerts.preview_dismiss_close') }}
                            </SecondaryButton>
                        </template>
                        <SecondaryButton
                            type="button"
                            class="justify-center"
                            :disabled="actionLoading && !savedForLater"
                            @click="emit('close')"
                        >
                            {{ t('app.job_alerts.preview_close') }}
                        </SecondaryButton>
                        <a
                            v-if="externalUrl"
                            :href="externalUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <SecondaryButton type="button" class="justify-center">
                                {{ t('app.job_alerts.preview_open_external') }}
                            </SecondaryButton>
                        </a>
                    </div>
                </div>

                <p
                    v-if="savedForLater"
                    class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
                    role="status"
                >
                    {{ t('app.job_alerts.save_for_later_toast') }}
                    <Link
                        :href="route('applications.index')"
                        class="font-medium underline hover:text-green-900 dark:hover:text-green-100"
                    >
                        {{ t('app.nav.applications') }}
                    </Link>.
                </p>
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
