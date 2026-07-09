<script setup>
import ChipSelect from '@/Components/ChipSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    jobSource: { type: Object, required: true },
    previewUrl: { type: String, required: true },
    itemSelector: { type: String, default: '' },
    fieldMappings: { type: Object, default: () => ({}) },
    fieldOptions: { type: Object, required: true },
    requiredFields: { type: Array, default: () => [] },
});

const { t } = useI18n();

const previewUrl = ref(props.previewUrl);
const itemSelector = ref(props.itemSelector);
const fieldMappings = ref({ ...props.fieldMappings });
const cachedHtml = ref(null);
const testResults = ref([]);
const itemMatchCount = ref(null);
const previewFrame = ref(null);
const previewLoaded = ref(false);
const loadingPreview = ref(false);
const testingExtraction = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const pendingSelection = ref(null);
const selectedField = ref('');

const currentStep = computed(() => {
    if (!previewLoaded.value) {
        return 1;
    }

    if (!itemSelector.value) {
        return 2;
    }

    return 3;
});

const fieldSelectOptions = computed(() =>
    Object.entries(props.fieldOptions).map(([value, label]) => ({ value, label })),
);

const mappedFields = computed(() => Object.entries(fieldMappings.value));

const missingRequiredFields = computed(() =>
    props.requiredFields.filter((field) => !fieldMappings.value[field]),
);

const hasAbsoluteFieldSelectors = computed(() =>
    Object.values(fieldMappings.value).some((mapping) => {
        const selector = mapping?.selector || '';

        return selector.includes('div.card.card-vertical')
            || selector.includes('div.card-deck')
            || selector.startsWith('div.row');
    }),
);

const hasGenericItemSelector = computed(() => {
    const selector = itemSelector.value.trim();

    return selector === 'div.container'
        || selector === 'div.row'
        || selector === 'div.col';
});

const configurationWarning = computed(() => {
    if (hasGenericItemSelector.value) {
        return t('app.job_sources.configurator.item_selector_generic_warning');
    }

    if (hasAbsoluteFieldSelectors.value) {
        return t('app.job_sources.configurator.field_selector_absolute_warning');
    }

    if (
        itemMatchCount.value !== null
        && testResults.value.length > 0
        && testResults.value.length < itemMatchCount.value
    ) {
        return t('app.job_sources.configurator.extracted_less_than_matches', {
            extracted: testResults.value.length,
            count: itemMatchCount.value,
        });
    }

    return '';
});

const pickerMode = computed(() => (currentStep.value === 2 ? 'item' : 'field'));

const stepPrompt = computed(() => {
    if (currentStep.value === 1) {
        return t('app.job_sources.configurator.step_load_prompt');
    }

    if (currentStep.value === 2) {
        return t('app.job_sources.configurator.step_item_prompt');
    }

    if (pendingSelection.value) {
        return t('app.job_sources.configurator.step_field_assign_prompt');
    }

    return t('app.job_sources.configurator.step_field_prompt');
});

const sendPickerConfig = () => {
    previewFrame.value?.contentWindow?.postMessage({
        type: 'job-source-picker-config',
        mode: pickerMode.value,
        itemSelector: itemSelector.value,
        enabled: previewLoaded.value && !pendingSelection.value,
    }, '*');
};

const clearPendingSelection = () => {
    pendingSelection.value = null;
    selectedField.value = '';
    sendPickerConfig();
};

const handlePickerMessage = (event) => {
    if (!event.data || event.data.type !== 'job-source-picker') {
        return;
    }

    if (event.data.ready) {
        sendPickerConfig();
        return;
    }

    const selector = (event.data.selector || '').trim();
    if (!selector || !previewLoaded.value) {
        return;
    }

    if (currentStep.value === 2 && event.data.mode === 'item') {
        pendingSelection.value = {
            type: 'item',
            selector,
            tagName: event.data.tagName || '',
            matchCount: event.data.matchCount || 0,
        };
        sendPickerConfig();
        return;
    }

    if (currentStep.value === 3 && event.data.mode === 'field') {
        pendingSelection.value = {
            type: 'field',
            selector,
            tagName: event.data.tagName || '',
        };
        selectedField.value = '';
        sendPickerConfig();
    }
};

const confirmItemSelection = () => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'item') {
        return;
    }

    const matchCount = pendingSelection.value.matchCount || 0;
    itemSelector.value = pendingSelection.value.selector;
    pendingSelection.value = null;
    successMessage.value = t('app.job_sources.configurator.item_selector_set', { count: matchCount });
    errorMessage.value = '';
    sendPickerConfig();
};

const assignFieldSelection = () => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'field' || !selectedField.value) {
        errorMessage.value = t('app.job_sources.configurator.select_field_first');
        return;
    }

    const fieldConfig = {
        selector: pendingSelection.value.selector,
        scope: 'item',
        extract: 'text',
    };

    if (selectedField.value === 'url' && (pendingSelection.value.tagName || '').toLowerCase() === 'a') {
        fieldConfig.extract = 'attribute';
        fieldConfig.attribute = 'href';
        fieldConfig.absolute = true;
    }

    if (!props.requiredFields.includes(selectedField.value)) {
        fieldConfig.optional = true;
    }

    fieldMappings.value = {
        ...fieldMappings.value,
        [selectedField.value]: fieldConfig,
    };

    successMessage.value = t('app.job_sources.configurator.field_mapped', {
        field: props.fieldOptions[selectedField.value] || selectedField.value,
    });
    errorMessage.value = '';
    clearPendingSelection();
};

const loadPreview = async () => {
    loadingPreview.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    clearPendingSelection();
    testResults.value = [];
    itemMatchCount.value = null;

    try {
        const { data } = await window.axios.post(route('job-sources.preview'), {
            url: previewUrl.value,
        });

        cachedHtml.value = data.cached_html;
        previewLoaded.value = true;

        if (previewFrame.value) {
            previewFrame.value.srcdoc = data.html;
        }

        successMessage.value = t('app.job_sources.configurator.preview_loaded');
    } catch (error) {
        previewLoaded.value = false;
        errorMessage.value = error.response?.data?.message
            || error.response?.data?.errors?.url?.[0]
            || t('app.job_sources.configurator.preview_failed');
    } finally {
        loadingPreview.value = false;
    }
};

const testExtraction = async () => {
    if (!itemSelector.value.trim()) {
        errorMessage.value = t('app.job_sources.configurator.item_selector_required');
        return;
    }

    if (!cachedHtml.value) {
        errorMessage.value = t('app.job_sources.configurator.preview_required');
        return;
    }

    testingExtraction.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const { data } = await window.axios.post(route('job-sources.test-extraction'), {
            html: cachedHtml.value,
            base_url: previewUrl.value,
            company_name: props.jobSource.company_name,
            item_selector: itemSelector.value,
            fields: fieldMappings.value,
        });

        testResults.value = data.listings || [];
        itemMatchCount.value = data.item_match_count ?? null;
        successMessage.value = t('app.job_sources.configurator.extracted_count', { count: data.count || 0 });
    } catch (error) {
        testResults.value = [];
        itemMatchCount.value = null;
        errorMessage.value = error.response?.data?.errors?.extraction?.[0]
            || error.response?.data?.message
            || t('app.job_sources.configurator.extraction_failed');
    } finally {
        testingExtraction.value = false;
    }
};

const removeFieldMapping = (field) => {
    const next = { ...fieldMappings.value };
    delete next[field];
    fieldMappings.value = next;
};

const resetItemSelector = () => {
    itemSelector.value = '';
    clearPendingSelection();
};

const saveConfiguration = () => {
    if (missingRequiredFields.value.length > 0) {
        errorMessage.value = t('app.job_sources.configurator.required_fields_missing');
        return;
    }

    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    router.patch(route('job-sources.extraction-config.update', props.jobSource.id), {
        preview_url: previewUrl.value,
        item_selector: itemSelector.value,
        fields: fieldMappings.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = t('app.job_sources.flash.config_saved');
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors).flat().join(' ');
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

watch(itemSelector, sendPickerConfig);
watch(currentStep, sendPickerConfig);
watch(previewLoaded, sendPickerConfig);

onMounted(() => {
    window.addEventListener('message', handlePickerMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', handlePickerMessage);
});
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="successMessage"
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"
        >
            {{ successMessage }}
        </div>

        <div
            v-if="configurationWarning"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            {{ configurationWarning }}
        </div>

        <div
            v-if="errorMessage"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ errorMessage }}
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <span :class="currentStep >= 1 ? 'text-indigo-600 dark:text-indigo-400' : ''">
                    1. {{ t('app.job_sources.configurator.step_load') }}
                </span>
                <span>→</span>
                <span :class="currentStep >= 2 ? 'text-indigo-600 dark:text-indigo-400' : ''">
                    2. {{ t('app.job_sources.configurator.step_item') }}
                </span>
                <span>→</span>
                <span :class="currentStep >= 3 ? 'text-indigo-600 dark:text-indigo-400' : ''">
                    3. {{ t('app.job_sources.configurator.step_fields') }}
                </span>
                <span>→</span>
                <span>4. {{ t('app.job_sources.configurator.step_save') }}</span>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                {{ stepPrompt }}
            </p>

            <p
                v-if="currentStep >= 3"
                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('app.job_sources.configurator.repeating_help') }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ t('app.job_sources.configurator.preview_url') }}
            </h3>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <TextInput
                        v-model="previewUrl"
                        type="url"
                        class="block w-full"
                        :placeholder="t('app.job_sources.configurator.preview_placeholder')"
                    />
                </div>
                <PrimaryButton :disabled="loadingPreview" type="button" @click="loadPreview">
                    {{ loadingPreview ? t('app.job_sources.configurator.loading_preview') : t('app.job_sources.configurator.load_preview') }}
                </PrimaryButton>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6">
                <div
                    v-if="pendingSelection?.type === 'item'"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.confirm_item_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.confirm_item_help', { count: pendingSelection.matchCount || 0 }) }}
                    </p>
                    <p class="mt-3 break-all rounded-lg bg-white/80 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ pendingSelection.selector }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" @click="confirmItemSelection">
                            {{ t('app.job_sources.configurator.use_as_list_item') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="clearPendingSelection">
                            {{ t('app.job_sources.configurator.pick_again') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-else-if="pendingSelection?.type === 'field'"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.confirm_field_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.confirm_field_help') }}
                    </p>
                    <p class="mt-3 break-all rounded-lg bg-white/80 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ pendingSelection.selector }}
                    </p>
                    <div class="mt-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('app.job_sources.configurator.field_to_map') }}
                        </label>
                        <ChipSelect
                            v-model="selectedField"
                            class="mt-2"
                            :options="fieldSelectOptions"
                            :placeholder="t('app.job_sources.configurator.choose_field')"
                            :aria-label="t('app.job_sources.configurator.field_to_map')"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" :disabled="!selectedField" @click="assignFieldSelection">
                            {{ t('app.job_sources.configurator.add_field_mapping') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="clearPendingSelection">
                            {{ t('app.job_sources.configurator.pick_again') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ t('app.job_sources.configurator.item_selector') }}
                        </h3>
                        <button
                            v-if="itemSelector"
                            type="button"
                            class="text-xs text-gray-500 hover:underline dark:text-gray-400"
                            @click="resetItemSelector"
                        >
                            {{ t('app.job_sources.configurator.change_item') }}
                        </button>
                    </div>
                    <p class="mt-2 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ itemSelector || '—' }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.mapped_fields') }}
                    </h3>

                    <p v-if="!mappedFields.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.no_fields_mapped') }}
                    </p>

                    <ul v-else class="mt-3 space-y-3">
                        <li
                            v-for="[field, mapping] in mappedFields"
                            :key="field"
                            class="rounded-lg border border-gray-100 p-3 dark:border-gray-700"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ fieldOptions[field] || field }}
                                    </p>
                                    <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300">
                                        {{ mapping.selector }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="removeFieldMapping(field)"
                                >
                                    {{ t('app.job_sources.configurator.remove') }}
                                </button>
                            </div>
                        </li>
                    </ul>

                    <p
                        v-if="missingRequiredFields.length"
                        class="mt-4 text-sm text-amber-700 dark:text-amber-300"
                    >
                        {{ t('app.job_sources.configurator.still_need') }}
                        {{ missingRequiredFields.map((field) => fieldOptions[field] || field).join(', ') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <SecondaryButton
                        :disabled="testingExtraction || currentStep < 3"
                        type="button"
                        @click="testExtraction"
                    >
                        {{ testingExtraction ? t('app.job_sources.configurator.testing') : t('app.job_sources.configurator.test_extraction') }}
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="saving || currentStep < 3"
                        type="button"
                        @click="saveConfiguration"
                    >
                        {{ saving ? t('app.job_sources.configurator.saving') : t('app.job_sources.configurator.save') }}
                    </PrimaryButton>
                </div>
            </div>

            <div class="xl:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.page_preview') }}
                    </h3>

                    <div class="relative mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <div
                            v-if="!previewLoaded"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-gray-50 px-6 text-center dark:bg-gray-900/80"
                        >
                            <div>
                                <p class="text-base font-medium text-gray-800 dark:text-gray-100">
                                    {{ t('app.job_sources.configurator.preview_empty_title') }}
                                </p>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.job_sources.configurator.preview_empty_help') }}
                                </p>
                            </div>
                        </div>

                        <iframe
                            ref="previewFrame"
                            :title="t('app.job_sources.configurator.page_preview')"
                            class="h-[70vh] w-full bg-white"
                            sandbox="allow-scripts allow-same-origin"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="testResults.length"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ t('app.job_sources.configurator.test_results') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.job_sources.configurator.test_results_help') }}
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">{{ t('app.job_sources.configurator.col_title') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('app.job_sources.configurator.col_url') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('app.job_sources.configurator.col_company') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('app.job_sources.configurator.col_location') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="(row, index) in testResults" :key="index">
                            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ row.title }}</td>
                            <td class="max-w-xs truncate px-3 py-2 text-indigo-600 dark:text-indigo-400">{{ row.url }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ row.company || '—' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ row.location || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
