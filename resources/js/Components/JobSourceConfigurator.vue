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
    itemMode: { type: String, default: 'single' },
    itemGroup: { type: Array, default: () => [] },
    fieldMappings: { type: Object, default: () => ({}) },
    pagination: {
        type: Object,
        default: () => ({ type: 'none', param: 'page', max_pages: 10 }),
    },
    engine: { type: String, default: 'http' },
    interactions: { type: Array, default: () => [] },
    fieldOptions: { type: Object, required: true },
    requiredFields: { type: Array, default: () => [] },
    detail: {
        type: Object,
        default: () => ({
            enabled: false,
            sample_url: '',
            fetch_min_score: 60,
            engine: 'inherit',
            interactions: [],
            fields: {},
        }),
    },
    detailFieldOptions: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const activeTab = ref('listing');
const previewUrl = ref(props.previewUrl);
const itemSelector = ref(props.itemSelector);
const itemMode = ref(props.itemMode === 'group' ? 'group' : 'single');
const itemGroupParts = ref(
    Array.isArray(props.itemGroup)
        ? props.itemGroup.map((part) => ({
            selector: part.selector || '',
            matchCount: part.match_count ?? part.matchCount ?? null,
        }))
        : [],
);
const itemGroupBuilding = ref(false);
const fieldMappings = ref({ ...props.fieldMappings });
const paginationType = ref(props.pagination?.type === 'query_param' ? 'query_param' : 'none');
const paginationParam = ref(props.pagination?.param || 'page');
const paginationMaxPages = ref(props.pagination?.max_pages || 10);
const previewEngine = ref(props.engine === 'playwright' ? 'playwright' : 'http');
const interactionSteps = ref(
    Array.isArray(props.interactions)
        ? props.interactions.map((step) => normalizeInteractionStep(step))
        : [],
);
const interactionRecordingType = ref(null);
const pendingInteraction = ref(null);
const testingFullFlow = ref(false);
const suggestPlaywright = ref(false);
const cachedHtml = ref(null);
const listingPreviewHtml = ref(null);
const testResults = ref([]);
const itemMatchCount = ref(null);
const previewFrame = ref(null);
const previewLoaded = ref(false);
const loadingPreview = ref(false);
const testingExtraction = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const detailEnabled = ref(Boolean(props.detail?.enabled));
const detailSampleUrl = ref(props.detail?.sample_url || '');
const detailFetchMinScore = ref(props.detail?.fetch_min_score ?? 60);
const detailEngine = ref(props.detail?.engine === 'playwright' ? 'playwright' : props.detail?.engine === 'http' ? 'http' : 'inherit');
const detailFieldMappings = ref({ ...(props.detail?.fields || {}) });
const detailInteractionSteps = ref(
    Array.isArray(props.detail?.interactions)
        ? props.detail.interactions.map((step) => normalizeInteractionStep(step))
        : [],
);
const detailCachedHtml = ref(null);
const detailPreviewHtml = ref(null);
const detailPreviewLoaded = ref(false);
const detailTestResults = ref([]);
const detailSelectedField = ref('');
const detailCustomFieldLabel = ref('');

const pendingSelection = ref(null);

const activePendingCandidate = computed(() => {
    if (!pendingSelection.value) {
        return null;
    }

    if (pendingSelection.value.type !== 'item' && pendingSelection.value.type !== 'detail_field') {
        return null;
    }

    const candidates = pendingSelection.value.candidates || [];
    const index = pendingSelection.value.candidateIndex ?? 0;

    if (candidates.length > 0) {
        return candidates[Math.min(index, candidates.length - 1)];
    }

    if (pendingSelection.value.selector) {
        return {
            selector: pendingSelection.value.selector,
            matchCount: pendingSelection.value.matchCount || 0,
            tagName: pendingSelection.value.tagName || '',
        };
    }

    return null;
});

const canUseParentElement = computed(() => {
    if (!pendingSelection.value) {
        return false;
    }

    if (pendingSelection.value.type !== 'item' && pendingSelection.value.type !== 'detail_field') {
        return false;
    }

    const candidates = pendingSelection.value.candidates || [];
    const index = pendingSelection.value.candidateIndex ?? 0;

    return index < candidates.length - 1;
});

const canUseChildElement = computed(() => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'detail_field') {
        return false;
    }

    return (pendingSelection.value.candidateIndex ?? 0) > 0;
});

const activePendingMatchCount = computed(() => activePendingCandidate.value?.matchCount || 0);

const activePendingMatchIndex = computed(() => {
    if (!pendingSelection.value) {
        return 0;
    }

    if (pendingSelection.value.matchIndex !== undefined && pendingSelection.value.matchIndex !== null) {
        return pendingSelection.value.matchIndex;
    }

    return activePendingCandidate.value?.matchIndex ?? 0;
});

const canCycleMatch = computed(() => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'detail_field') {
        return false;
    }

    return activePendingMatchCount.value > 1;
});

const groupPartMatchCounts = computed(() =>
    itemGroupParts.value.map((part) => part.matchCount ?? 0),
);

const groupMatchCount = computed(() => {
    if (itemGroupParts.value.length === 0) {
        return 0;
    }

    return Math.min(...groupPartMatchCounts.value);
});

const groupPartCountMismatch = computed(() => {
    const counts = groupPartMatchCounts.value;

    if (counts.length <= 1) {
        return false;
    }

    return new Set(counts).size > 1;
});

const hasListItemConfigured = computed(() => {
    if (itemMode.value === 'group') {
        return itemGroupParts.value.length >= 2;
    }

    return itemSelector.value.trim() !== '';
});
const selectedField = ref('');
const customFieldLabel = ref('');

const CUSTOM_FIELD = '__custom__';

const INTERACTION_PRESETS = [
    {
        key: 'onetrust',
        selector: '#onetrust-accept-btn-handler',
        type: 'click',
        optional: true,
        wait_after_ms: 500,
    },
    {
        key: 'cookiebot',
        selector: '#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll',
        type: 'click',
        optional: true,
        wait_after_ms: 500,
    },
];

function normalizeInteractionStep(step) {
    if (!step || typeof step !== 'object') {
        return { type: 'click', selector: '', optional: false };
    }

    return {
        type: step.type || 'click',
        selector: typeof step.selector === 'string' ? step.selector : '',
        optional: step.optional === true,
        timeout_ms: typeof step.timeout_ms === 'number' ? step.timeout_ms : 10_000,
        wait_after_ms: typeof step.wait_after_ms === 'number' ? step.wait_after_ms : 500,
        ms: typeof step.ms === 'number' ? step.ms : 1_000,
        repeat_until_gone: step.repeat_until_gone === true,
        max_clicks: typeof step.max_clicks === 'number' ? step.max_clicks : 5,
    };
}

function serializeInteractionSteps(steps) {
    return steps.map((step) => {
        const serialized = { type: step.type };

        if (step.type === 'sleep') {
            serialized.ms = step.ms ?? 1_000;

            return serialized;
        }

        if (step.selector?.trim()) {
            serialized.selector = step.selector.trim();
        }

        if (step.optional) {
            serialized.optional = true;
        }

        if (step.type === 'wait_for') {
            serialized.timeout_ms = step.timeout_ms ?? 10_000;
        }

        if (step.type === 'click') {
            if (step.wait_after_ms) {
                serialized.wait_after_ms = step.wait_after_ms;
            }

            if (step.repeat_until_gone) {
                serialized.repeat_until_gone = true;
                serialized.max_clicks = step.max_clicks ?? 5;
            }
        }

        return serialized;
    });
}

function interactionStepLabel(step) {
    const typeKey = `app.job_sources.configurator.interaction_type_${step.type}`;

    return t(typeKey);
}

const isCustomFieldSelected = computed(() => selectedField.value === CUSTOM_FIELD);

const fieldDisplayLabel = (field, mapping = null) => {
    const mappingConfig = mapping ?? fieldMappings.value[field];

    return mappingConfig?.label || props.fieldOptions[field] || field;
};

const buildCustomFieldKey = (label) => {
    const base = label
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '')
        .slice(0, 50) || 'custom_field';

    let key = base;
    let suffix = 2;

    while (fieldMappings.value[key] || detailFieldMappings.value[key]) {
        key = `${base}_${suffix}`;
        suffix += 1;
    }

    return key;
};

const canAssignField = computed(() => {
    if (!selectedField.value) {
        return false;
    }

    if (isCustomFieldSelected.value) {
        return customFieldLabel.value.trim() !== '';
    }

    return true;
});

const isListingTab = computed(() => activeTab.value === 'listing');
const isDetailTab = computed(() => activeTab.value === 'detail');

const activePreviewUrl = computed(() => (isListingTab.value ? previewUrl.value : detailSampleUrl.value));
const activePreviewEngine = computed(() => {
    if (isListingTab.value) {
        return previewEngine.value;
    }

    if (detailEngine.value === 'playwright') {
        return 'playwright';
    }

    if (detailEngine.value === 'http') {
        return 'http';
    }

    return previewEngine.value;
});
const activeInteractionSteps = computed(() => (
    isListingTab.value ? interactionSteps.value : detailInteractionSteps.value
));
const activeCachedHtml = computed(() => (isListingTab.value ? cachedHtml.value : detailCachedHtml.value));
const activePreviewLoaded = computed(() => (
    isListingTab.value ? previewLoaded.value : detailPreviewLoaded.value
));

const detailCurrentStep = computed(() => {
    if (!detailPreviewLoaded.value) {
        return 1;
    }

    return 2;
});

const detailMappedFields = computed(() => Object.entries(detailFieldMappings.value));

const detailFieldSelectOptions = computed(() =>
    Object.entries(props.detailFieldOptions ?? {}).map(([value, label]) => ({ value, label })),
);

const isDetailCustomFieldSelected = computed(() => detailSelectedField.value === CUSTOM_FIELD);

const canAssignDetailField = computed(() => {
    if (!detailSelectedField.value) {
        return false;
    }

    if (isDetailCustomFieldSelected.value) {
        return detailCustomFieldLabel.value.trim() !== '';
    }

    return true;
});

const detailFieldDisplayLabel = (field, mapping = null) => {
    const mappingConfig = mapping ?? detailFieldMappings.value[field];

    return mappingConfig?.label || props.detailFieldOptions[field] || field;
};

const currentStep = computed(() => {
    if (isDetailTab.value) {
        return detailCurrentStep.value;
    }

    if (!previewLoaded.value) {
        return 1;
    }

    if (!hasListItemConfigured.value) {
        return 2;
    }

    return 3;
});

const fieldSelectOptions = computed(() =>
    Object.entries(props.fieldOptions).map(([value, label]) => ({ value, label })),
);

const mappedFields = computed(() => Object.entries(fieldMappings.value));

const testResultColumns = computed(() =>
    mappedFields.value.map(([key, mapping]) => ({
        key,
        label: fieldDisplayLabel(key, mapping),
    })),
);

const detailTestResultColumns = computed(() =>
    detailMappedFields.value.map(([key, mapping]) => ({
        key,
        label: detailFieldDisplayLabel(key, mapping),
    })),
);

const testResultValue = (row, fieldKey) => row.fields?.[fieldKey] || '—';

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
    if (groupPartCountMismatch.value && itemMode.value === 'group') {
        return t('app.job_sources.configurator.item_group_count_mismatch', {
            counts: groupPartMatchCounts.value.join(', '),
            count: groupMatchCount.value,
        });
    }

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

const pickerMode = computed(() => {
    if (interactionRecordingType.value) {
        return 'interaction';
    }

    if (isDetailTab.value) {
        if (detailCurrentStep.value === 2) {
            return 'detail_field';
        }

        return 'item';
    }

    if (itemGroupBuilding.value) {
        return 'item_group_part';
    }

    if (currentStep.value === 2) {
        return 'item';
    }

    return 'field';
});

const pickerInteractive = computed(() => {
    if (!activePreviewLoaded.value) {
        return false;
    }

    if (interactionRecordingType.value) {
        return true;
    }

    if (isDetailTab.value) {
        return !pendingSelection.value && !pendingInteraction.value;
    }

    if (itemGroupBuilding.value) {
        return true;
    }

    return !pendingSelection.value && !pendingInteraction.value;
});

const stepPrompt = computed(() => {
    if (isDetailTab.value) {
        if (detailCurrentStep.value === 1) {
            return t('app.job_sources.configurator.detail_step_load_prompt');
        }

        if (pendingSelection.value) {
            return t('app.job_sources.configurator.detail_step_field_assign_prompt');
        }

        return t('app.job_sources.configurator.detail_step_field_prompt');
    }

    if (currentStep.value === 1) {
        return t('app.job_sources.configurator.step_load_prompt');
    }

    if (currentStep.value === 2) {
        if (itemGroupBuilding.value) {
            return t('app.job_sources.configurator.step_group_build_prompt');
        }

        return t('app.job_sources.configurator.step_item_prompt');
    }

    if (pendingSelection.value) {
        return t('app.job_sources.configurator.step_field_assign_prompt');
    }

    return t('app.job_sources.configurator.step_field_prompt');
});

const pinnedSelector = computed(() => {
    if (!pendingSelection.value) {
        return '';
    }

    return activePendingCandidate.value?.selector || pendingSelection.value.selector || '';
});

const sendPickerConfig = () => {
    previewFrame.value?.contentWindow?.postMessage({
        type: 'job-source-picker-config',
        mode: pickerMode.value,
        itemSelector: itemMode.value === 'single' ? itemSelector.value : '',
        itemMode: itemMode.value,
        itemGroupParts: itemGroupParts.value.map((part) => part.selector),
        enabled: pickerInteractive.value,
        pinnedSelector: pinnedSelector.value,
        pinnedMatchIndex: pendingSelection.value?.type === 'detail_field'
            ? activePendingMatchIndex.value
            : 0,
    }, '*');
};

const clearPendingInteraction = () => {
    pendingInteraction.value = null;
    interactionRecordingType.value = null;
    sendPickerConfig();
};

const clearPendingSelection = () => {
    pendingSelection.value = null;
    selectedField.value = '';
    customFieldLabel.value = '';
    sendPickerConfig();
};

const clearAllPending = () => {
    clearPendingSelection();
    clearPendingInteraction();
};

const cancelGroupBuilding = () => {
    itemGroupBuilding.value = false;
    itemGroupParts.value = [];
    itemMode.value = 'single';
    clearPendingSelection();
};

const removeGroupPart = (index) => {
    itemGroupParts.value = itemGroupParts.value.filter((_, partIndex) => partIndex !== index);

    if (itemGroupParts.value.length === 0) {
        itemGroupBuilding.value = false;
        itemMode.value = 'single';
    }

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
    if (!selector || !activePreviewLoaded.value) {
        return;
    }

    if (event.data.mode === 'detail_field' && isDetailTab.value) {
        const candidates = Array.isArray(event.data.candidates) ? event.data.candidates : [];

        pendingSelection.value = {
            type: 'detail_field',
            selector: event.data.selector || '',
            tagName: event.data.tagName || '',
            candidates,
            candidateIndex: 0,
            matchIndex: candidates[0]?.matchIndex ?? event.data.matchIndex ?? 0,
        };
        detailSelectedField.value = '';
        detailCustomFieldLabel.value = '';
        sendPickerConfig();

        return;
    }

    if (event.data.mode === 'interaction' && interactionRecordingType.value) {
        const type = interactionRecordingType.value;

        pendingInteraction.value = {
            type,
            selector,
            optional: type === 'click',
            timeout_ms: 10_000,
            wait_after_ms: 500,
            ms: 1_000,
            repeat_until_gone: false,
            max_clicks: 5,
            matchCount: event.data.matchCount || 0,
        };
        interactionRecordingType.value = null;
        errorMessage.value = '';
        sendPickerConfig();

        return;
    }

    if (itemGroupBuilding.value && event.data.mode === 'group_part') {
        const selector = (event.data.selector || '').trim();

        if (!selector) {
            return;
        }

        if (itemGroupParts.value.some((part) => part.selector === selector)) {
            errorMessage.value = t('app.job_sources.configurator.item_group_duplicate_part');
            return;
        }

        itemGroupParts.value = [
            ...itemGroupParts.value,
            {
                selector,
                matchCount: event.data.matchCount || 0,
            },
        ];
        errorMessage.value = '';
        successMessage.value = t('app.job_sources.configurator.item_group_part_added', {
            count: itemGroupParts.value.length,
        });
        sendPickerConfig();

        return;
    }

    if (currentStep.value === 2 && event.data.mode === 'item' && isListingTab.value) {
        const candidates = Array.isArray(event.data.candidates) ? event.data.candidates : [];

        pendingSelection.value = {
            type: 'item',
            selector: event.data.selector || '',
            tagName: event.data.tagName || '',
            matchCount: event.data.matchCount || 0,
            candidates,
            candidateIndex: 0,
        };
        sendPickerConfig();
        return;
    }

    if (currentStep.value === 3 && event.data.mode === 'field' && isListingTab.value) {
        pendingSelection.value = {
            type: 'field',
            selector,
            tagName: event.data.tagName || '',
        };
        selectedField.value = '';
        customFieldLabel.value = '';
        sendPickerConfig();
    }
};

const confirmItemSelection = () => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'item') {
        return;
    }

    const candidate = activePendingCandidate.value;

    if (!candidate?.selector) {
        return;
    }

    itemMode.value = 'single';
    itemGroupParts.value = [];
    itemGroupBuilding.value = false;
    itemSelector.value = candidate.selector;
    pendingSelection.value = null;
    successMessage.value = t('app.job_sources.configurator.item_selector_set', { count: candidate.matchCount || 0 });
    errorMessage.value = '';
    sendPickerConfig();
};

const startGroupFromPending = () => {
    const candidate = activePendingCandidate.value;

    if (!candidate?.selector) {
        return;
    }

    itemMode.value = 'group';
    itemGroupBuilding.value = true;
    itemSelector.value = '';
    itemGroupParts.value = [{
        selector: candidate.selector,
        matchCount: candidate.matchCount || 0,
    }];
    pendingSelection.value = null;
    errorMessage.value = '';
    successMessage.value = t('app.job_sources.configurator.item_group_started');
    sendPickerConfig();
};

const finishGroupSelection = () => {
    if (itemGroupParts.value.length < 2) {
        errorMessage.value = t('app.job_sources.configurator.item_group_min_parts');

        return;
    }

    itemGroupBuilding.value = false;
    itemMode.value = 'group';
    itemSelector.value = '';
    pendingSelection.value = null;
    successMessage.value = t('app.job_sources.configurator.item_group_set', {
        count: groupMatchCount.value,
        parts: itemGroupParts.value.length,
    });
    errorMessage.value = '';
    sendPickerConfig();
};

const useParentElement = () => {
    if (!canUseParentElement.value || !pendingSelection.value) {
        return;
    }

    const newIndex = (pendingSelection.value.candidateIndex ?? 0) + 1;
    const candidate = (pendingSelection.value.candidates || [])[newIndex];

    pendingSelection.value = {
        ...pendingSelection.value,
        candidateIndex: newIndex,
        matchIndex: candidate?.matchIndex ?? 0,
    };
};

const useChildElement = () => {
    if (!canUseChildElement.value || !pendingSelection.value) {
        return;
    }

    const newIndex = (pendingSelection.value.candidateIndex ?? 0) - 1;
    const candidate = (pendingSelection.value.candidates || [])[newIndex];

    pendingSelection.value = {
        ...pendingSelection.value,
        candidateIndex: newIndex,
        matchIndex: candidate?.matchIndex ?? 0,
    };
};

const useNextMatch = () => {
    if (!canCycleMatch.value || !pendingSelection.value) {
        return;
    }

    const count = activePendingMatchCount.value;
    const current = activePendingMatchIndex.value;

    pendingSelection.value = {
        ...pendingSelection.value,
        matchIndex: (current + 1) % count,
    };
};

const usePreviousMatch = () => {
    if (!canCycleMatch.value || !pendingSelection.value) {
        return;
    }

    const count = activePendingMatchCount.value;
    const current = activePendingMatchIndex.value;

    pendingSelection.value = {
        ...pendingSelection.value,
        matchIndex: (current - 1 + count) % count,
    };
};

const assignDetailFieldSelection = () => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'detail_field' || !detailSelectedField.value) {
        errorMessage.value = t('app.job_sources.configurator.select_field_first');
        return;
    }

    let fieldKey = detailSelectedField.value;
    let fieldLabel = props.detailFieldOptions[fieldKey] || fieldKey;

    if (isDetailCustomFieldSelected.value) {
        const label = detailCustomFieldLabel.value.trim();

        if (!label) {
            errorMessage.value = t('app.job_sources.configurator.custom_field_required');
            return;
        }

        fieldKey = buildCustomFieldKey(label);
        fieldLabel = label;
    }

    const candidate = activePendingCandidate.value;
    const fieldConfig = {
        selector: candidate?.selector || pendingSelection.value.selector,
        scope: 'document',
        extract: 'text',
        optional: fieldKey !== 'description',
    };

    if ((candidate?.matchCount || 0) > 1) {
        fieldConfig.match_index = activePendingMatchIndex.value;
    }

    if (isDetailCustomFieldSelected.value) {
        fieldConfig.label = fieldLabel;
    }

    detailFieldMappings.value = {
        ...detailFieldMappings.value,
        [fieldKey]: fieldConfig,
    };

    successMessage.value = t('app.job_sources.configurator.field_mapped', {
        field: fieldLabel,
    });
    errorMessage.value = '';
    clearPendingSelection();
};

const removeDetailFieldMapping = (field) => {
    const next = { ...detailFieldMappings.value };
    delete next[field];
    detailFieldMappings.value = next;
};

const assignFieldSelection = () => {
    if (!pendingSelection.value || pendingSelection.value.type !== 'field' || !selectedField.value) {
        errorMessage.value = t('app.job_sources.configurator.select_field_first');
        return;
    }

    let fieldKey = selectedField.value;
    let fieldLabel = props.fieldOptions[fieldKey] || fieldKey;

    if (isCustomFieldSelected.value) {
        const label = customFieldLabel.value.trim();

        if (!label) {
            errorMessage.value = t('app.job_sources.configurator.custom_field_required');
            return;
        }

        fieldKey = buildCustomFieldKey(label);
        fieldLabel = label;
    }

    const fieldConfig = {
        selector: pendingSelection.value.selector,
        scope: 'item',
        extract: 'text',
    };

    if (fieldKey === 'url' && (pendingSelection.value.tagName || '').toLowerCase() === 'a') {
        fieldConfig.extract = 'attribute';
        fieldConfig.attribute = 'href';
        fieldConfig.absolute = true;
    }

    if (!props.requiredFields.includes(fieldKey)) {
        fieldConfig.optional = true;
    }

    if (isCustomFieldSelected.value) {
        fieldConfig.label = fieldLabel;
    }

    fieldMappings.value = {
        ...fieldMappings.value,
        [fieldKey]: fieldConfig,
    };

    successMessage.value = t('app.job_sources.configurator.field_mapped', {
        field: fieldLabel,
    });
    errorMessage.value = '';
    clearPendingSelection();
};

const startInteractionRecording = (type) => {
    if (!activePreviewLoaded.value) {
        errorMessage.value = t('app.job_sources.configurator.preview_required');
        return;
    }

    clearAllPending();
    interactionRecordingType.value = type;

    if (isDetailTab.value && detailEngine.value === 'inherit') {
        detailEngine.value = 'playwright';
    } else if (isListingTab.value) {
        previewEngine.value = 'playwright';
    }

    successMessage.value = t(`app.job_sources.configurator.interaction_record_${type}`);
    errorMessage.value = '';
    sendPickerConfig();
};

const addInteractionPreset = (presetKey) => {
    const preset = INTERACTION_PRESETS.find((entry) => entry.key === presetKey);

    if (!preset) {
        return;
    }

    interactionSteps.value = [
        ...interactionSteps.value,
        normalizeInteractionStep(preset),
    ];
    previewEngine.value = 'playwright';
    successMessage.value = t('app.job_sources.configurator.interaction_added');
    errorMessage.value = '';
};

const addSleepStep = () => {
    interactionSteps.value = [
        ...interactionSteps.value,
        normalizeInteractionStep({ type: 'sleep', ms: 1_000 }),
    ];
    previewEngine.value = 'playwright';
    successMessage.value = t('app.job_sources.configurator.interaction_added');
};

const addWaitForListStep = () => {
    const selector = itemMode.value === 'single'
        ? itemSelector.value.trim()
        : (itemGroupParts.value[0]?.selector || '').trim();

    if (!selector) {
        errorMessage.value = t('app.job_sources.configurator.interaction_wait_list_needs_selector');
        return;
    }

    interactionSteps.value = [
        ...interactionSteps.value,
        normalizeInteractionStep({
            type: 'wait_for',
            selector,
            timeout_ms: 20_000,
        }),
    ];
    previewEngine.value = 'playwright';
    successMessage.value = t('app.job_sources.configurator.interaction_added');
    errorMessage.value = '';
};

const confirmInteractionStep = () => {
    if (!pendingInteraction.value) {
        return;
    }

    const step = normalizeInteractionStep(pendingInteraction.value);

    if (isDetailTab.value) {
        detailInteractionSteps.value = [...detailInteractionSteps.value, step];
        if (detailEngine.value === 'inherit') {
            detailEngine.value = 'playwright';
        }
    } else {
        interactionSteps.value = [...interactionSteps.value, step];
        previewEngine.value = 'playwright';
    }

    successMessage.value = t('app.job_sources.configurator.interaction_added');
    errorMessage.value = '';
    clearPendingInteraction();
};

const removeInteractionStep = (index) => {
    interactionSteps.value = interactionSteps.value.filter((_, stepIndex) => stepIndex !== index);
};

const moveInteractionStep = (index, direction) => {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= interactionSteps.value.length) {
        return;
    }

    const steps = [...interactionSteps.value];
    const [step] = steps.splice(index, 1);
    steps.splice(nextIndex, 0, step);
    interactionSteps.value = steps;
};

const loadPreview = async () => {
    loadingPreview.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    suggestPlaywright.value = false;
    clearAllPending();
    itemGroupBuilding.value = false;

    if (isListingTab.value) {
        testResults.value = [];
        itemMatchCount.value = null;
    } else {
        detailTestResults.value = [];
    }

    const interactions = serializeInteractionSteps(activeInteractionSteps.value);
    const engine = activePreviewEngine.value === 'playwright' ? 'playwright' : 'http';

    try {
        const { data } = await window.axios.post(route('job-sources.preview'), {
            url: activePreviewUrl.value,
            engine,
            interactions,
        });

        if (isListingTab.value) {
            cachedHtml.value = data.cached_html;
            listingPreviewHtml.value = data.html;
            previewLoaded.value = true;
        } else {
            detailCachedHtml.value = data.cached_html;
            detailPreviewHtml.value = data.html;
            detailPreviewLoaded.value = true;
        }

        suggestPlaywright.value = Boolean(data.suggest_playwright);

        if (previewFrame.value) {
            previewFrame.value.srcdoc = data.html;
        }

        if (data.rendered_with === 'playwright') {
            successMessage.value = t('app.job_sources.configurator.preview_loaded_playwright');
        } else {
            successMessage.value = t('app.job_sources.configurator.preview_loaded');
        }
    } catch (error) {
        if (isListingTab.value) {
            previewLoaded.value = false;
        } else {
            detailPreviewLoaded.value = false;
        }

        errorMessage.value = error.response?.data?.message
            || error.response?.data?.errors?.url?.[0]
            || t('app.job_sources.configurator.preview_failed');
    } finally {
        loadingPreview.value = false;
    }
};

const enablePlaywrightAndReload = () => {
    previewEngine.value = 'playwright';
    loadPreview();
};

const testExtraction = async () => {
    if (!itemSelector.value.trim() && itemMode.value === 'single') {
        errorMessage.value = t('app.job_sources.configurator.item_selector_required');
        return;
    }

    if (itemMode.value === 'group' && itemGroupParts.value.length < 2) {
        errorMessage.value = t('app.job_sources.configurator.item_group_min_parts');
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
            item_mode: itemMode.value,
            item_selector: itemMode.value === 'single' ? itemSelector.value : '',
            item_group: itemMode.value === 'group' ? { parts: itemGroupParts.value } : null,
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

const testFullFlow = async () => {
    if (!itemSelector.value.trim() && itemMode.value === 'single') {
        errorMessage.value = t('app.job_sources.configurator.item_selector_required');
        return;
    }

    if (itemMode.value === 'group' && itemGroupParts.value.length < 2) {
        errorMessage.value = t('app.job_sources.configurator.item_group_min_parts');
        return;
    }

    if (missingRequiredFields.value.length > 0) {
        errorMessage.value = t('app.job_sources.configurator.required_fields_missing');
        return;
    }

    testingFullFlow.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const { data } = await window.axios.post(route('job-sources.test-extraction'), {
            url: previewUrl.value,
            base_url: previewUrl.value,
            company_name: props.jobSource.company_name,
            engine: 'playwright',
            interactions: serializeInteractionSteps(interactionSteps.value),
            item_mode: itemMode.value,
            item_selector: itemMode.value === 'single' ? itemSelector.value : '',
            item_group: itemMode.value === 'group' ? { parts: itemGroupParts.value } : null,
            fields: fieldMappings.value,
        });

        cachedHtml.value = data.html ?? cachedHtml.value;
        testResults.value = data.listings || [];
        itemMatchCount.value = data.item_match_count ?? null;
        successMessage.value = t('app.job_sources.configurator.full_flow_extracted', { count: data.count || 0 });
    } catch (error) {
        testResults.value = [];
        itemMatchCount.value = null;
        errorMessage.value = error.response?.data?.errors?.extraction?.[0]
            || error.response?.data?.message
            || t('app.job_sources.configurator.full_flow_failed');
    } finally {
        testingFullFlow.value = false;
    }
};

const removeFieldMapping = (field) => {
    const next = { ...fieldMappings.value };
    delete next[field];
    fieldMappings.value = next;
};

const resetItemSelector = () => {
    itemSelector.value = '';
    itemMode.value = 'single';
    itemGroupParts.value = [];
    itemGroupBuilding.value = false;
    clearPendingSelection();
};

const testDetailExtraction = async () => {
    if (!detailCachedHtml.value) {
        errorMessage.value = t('app.job_sources.configurator.preview_required');
        return;
    }

    testingExtraction.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    const engine = detailEngine.value === 'inherit'
        ? (previewEngine.value === 'playwright' ? 'playwright' : 'http')
        : detailEngine.value;

    try {
        const { data } = await window.axios.post(route('job-sources.test-extraction'), {
            html: detailCachedHtml.value,
            base_url: detailSampleUrl.value,
            extraction_type: 'detail',
            engine,
            interactions: serializeInteractionSteps(detailInteractionSteps.value),
            fields: detailFieldMappings.value,
        });

        detailTestResults.value = data.listings || [];
        successMessage.value = t('app.job_sources.configurator.detail_extracted_count', {
            count: data.count || detailTestResults.value.length,
        });
    } catch (error) {
        detailTestResults.value = [];
        errorMessage.value = error.response?.data?.errors?.extraction?.[0]
            || Object.values(error.response?.data?.errors ?? {}).flat().join(' ')
            || error.response?.data?.message
            || t('app.job_sources.configurator.extraction_failed');
    } finally {
        testingExtraction.value = false;
    }
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
        item_mode: itemMode.value,
        item_selector: itemMode.value === 'single' ? itemSelector.value : '',
        item_group: itemMode.value === 'group' ? { parts: itemGroupParts.value } : null,
        fields: fieldMappings.value,
        engine: previewEngine.value,
        interactions: serializeInteractionSteps(interactionSteps.value),
        pagination: {
            type: paginationType.value,
            param: paginationParam.value,
            max_pages: paginationMaxPages.value,
        },
        detail: {
            enabled: detailEnabled.value,
            sample_url: detailSampleUrl.value || null,
            fetch_min_score: detailFetchMinScore.value,
            engine: detailEngine.value,
            interactions: serializeInteractionSteps(detailInteractionSteps.value),
            fields: detailFieldMappings.value,
        },
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

watch(activeTab, () => {
    clearAllPending();
    sendPickerConfig();

    if (previewFrame.value) {
        const html = isListingTab.value ? listingPreviewHtml.value : detailPreviewHtml.value;

        if (html && activePreviewLoaded.value) {
            previewFrame.value.srcdoc = html;
        }
    }
});

watch(itemSelector, sendPickerConfig);
watch(itemMode, sendPickerConfig);
watch(itemGroupParts, sendPickerConfig, { deep: true });
watch(currentStep, sendPickerConfig);
watch(previewLoaded, sendPickerConfig);
watch(itemGroupBuilding, sendPickerConfig);
watch(interactionRecordingType, sendPickerConfig);
watch(pendingInteraction, sendPickerConfig);
watch(pendingSelection, sendPickerConfig, { deep: true });

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

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-medium transition"
                :class="isListingTab
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                @click="activeTab = 'listing'"
            >
                {{ t('app.job_sources.configurator.tab_listing') }}
            </button>
            <button
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-medium transition"
                :class="isDetailTab
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                @click="activeTab = 'detail'"
            >
                {{ t('app.job_sources.configurator.tab_detail') }}
            </button>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <template v-if="isListingTab">
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
                </template>
                <template v-else>
                    <span :class="currentStep >= 1 ? 'text-indigo-600 dark:text-indigo-400' : ''">
                        1. {{ t('app.job_sources.configurator.step_load') }}
                    </span>
                    <span>→</span>
                    <span :class="currentStep >= 2 ? 'text-indigo-600 dark:text-indigo-400' : ''">
                        2. {{ t('app.job_sources.configurator.step_fields') }}
                    </span>
                    <span>→</span>
                    <span>3. {{ t('app.job_sources.configurator.step_save') }}</span>
                </template>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                {{ stepPrompt }}
            </p>

            <p
                v-if="isListingTab && currentStep >= 3"
                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('app.job_sources.configurator.repeating_help') }}
            </p>

            <p
                v-if="isDetailTab"
                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('app.job_sources.configurator.detail_help') }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ isListingTab
                    ? t('app.job_sources.configurator.preview_url')
                    : t('app.job_sources.configurator.detail_sample_url') }}
            </h3>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <TextInput
                        v-if="isListingTab"
                        v-model="previewUrl"
                        type="url"
                        class="block w-full"
                        :placeholder="t('app.job_sources.configurator.preview_placeholder')"
                    />
                    <TextInput
                        v-else
                        v-model="detailSampleUrl"
                        type="url"
                        class="block w-full"
                        :placeholder="t('app.job_sources.configurator.detail_sample_url_placeholder')"
                    />
                </div>
                <PrimaryButton :disabled="loadingPreview" type="button" @click="loadPreview">
                    {{ loadingPreview ? t('app.job_sources.configurator.loading_preview') : t('app.job_sources.configurator.load_preview') }}
                </PrimaryButton>
            </div>

            <div v-if="isListingTab" class="mt-4 space-y-3 border-t border-gray-100 pt-4 dark:border-gray-700">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        v-model="previewEngine"
                        type="checkbox"
                        class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                        true-value="playwright"
                        false-value="http"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ t('app.job_sources.configurator.use_playwright') }}
                        </span>
                        <span class="mt-1 block text-gray-500 dark:text-gray-400">
                            {{ t('app.job_sources.configurator.use_playwright_help') }}
                        </span>
                    </span>
                </label>

                <div class="rounded-lg border border-gray-100 p-4 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.interactions_heading') }}
                    </h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.interactions_recorder_help') }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <SecondaryButton
                            type="button"
                            :disabled="!previewLoaded || Boolean(interactionRecordingType)"
                            @click="startInteractionRecording('click')"
                        >
                            {{ t('app.job_sources.configurator.interaction_add_click') }}
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!previewLoaded || Boolean(interactionRecordingType)"
                            @click="startInteractionRecording('wait_for')"
                        >
                            {{ t('app.job_sources.configurator.interaction_add_wait') }}
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!previewLoaded || Boolean(interactionRecordingType)"
                            @click="startInteractionRecording('scroll')"
                        >
                            {{ t('app.job_sources.configurator.interaction_add_scroll') }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="addSleepStep">
                            {{ t('app.job_sources.configurator.interaction_add_sleep') }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="addWaitForListStep">
                            {{ t('app.job_sources.configurator.interaction_wait_for_list') }}
                        </SecondaryButton>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <SecondaryButton type="button" @click="addInteractionPreset('onetrust')">
                            {{ t('app.job_sources.configurator.interaction_preset_onetrust') }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="addInteractionPreset('cookiebot')">
                            {{ t('app.job_sources.configurator.interaction_preset_cookiebot') }}
                        </SecondaryButton>
                    </div>

                    <p
                        v-if="interactionRecordingType"
                        class="mt-3 text-sm font-medium text-indigo-700 dark:text-indigo-300"
                    >
                        {{ t(`app.job_sources.configurator.interaction_record_${interactionRecordingType}`) }}
                    </p>

                    <p v-if="!interactionSteps.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.interactions_empty') }}
                    </p>

                    <ol v-else class="mt-3 space-y-2">
                        <li
                            v-for="(step, index) in interactionSteps"
                            :key="`interaction-${index}-${step.type}-${step.selector || step.ms}`"
                            class="rounded-lg border border-gray-100 p-3 dark:border-gray-700"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ index + 1 }}. {{ interactionStepLabel(step) }}
                                    </p>
                                    <p
                                        v-if="step.selector"
                                        class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300"
                                    >
                                        {{ step.selector }}
                                    </p>
                                    <p v-if="step.type === 'sleep'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ step.ms }} ms
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-600 dark:text-gray-300">
                                        <label
                                            v-if="step.type === 'click'"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <input v-model="step.optional" type="checkbox" class="rounded border-gray-300">
                                            {{ t('app.job_sources.configurator.interaction_optional') }}
                                        </label>
                                        <label
                                            v-if="step.type === 'click'"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <input v-model="step.repeat_until_gone" type="checkbox" class="rounded border-gray-300">
                                            {{ t('app.job_sources.configurator.interaction_repeat') }}
                                        </label>
                                        <label v-if="step.type === 'wait_for'" class="inline-flex items-center gap-2">
                                            <span>{{ t('app.job_sources.configurator.interaction_timeout') }}</span>
                                            <input
                                                v-model.number="step.timeout_ms"
                                                type="number"
                                                min="1000"
                                                max="120000"
                                                step="1000"
                                                class="w-20 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900"
                                            >
                                        </label>
                                        <label v-if="step.type === 'sleep'" class="inline-flex items-center gap-2">
                                            <span>{{ t('app.job_sources.configurator.interaction_sleep_ms') }}</span>
                                            <input
                                                v-model.number="step.ms"
                                                type="number"
                                                min="100"
                                                max="60000"
                                                step="100"
                                                class="w-20 rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900"
                                            >
                                        </label>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col gap-1">
                                    <button
                                        type="button"
                                        class="text-xs text-gray-500 hover:underline disabled:opacity-40"
                                        :disabled="index === 0"
                                        @click="moveInteractionStep(index, -1)"
                                    >
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-gray-500 hover:underline disabled:opacity-40"
                                        :disabled="index === interactionSteps.length - 1"
                                        @click="moveInteractionStep(index, 1)"
                                    >
                                        ↓
                                    </button>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 hover:underline"
                                        @click="removeInteractionStep(index)"
                                    >
                                        {{ t('app.job_sources.configurator.remove') }}
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <div v-else class="mt-4 space-y-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        v-model="detailEnabled"
                        type="checkbox"
                        class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ t('app.job_sources.configurator.detail_enabled') }}
                        </span>
                        <span class="mt-1 block text-gray-500 dark:text-gray-400">
                            {{ t('app.job_sources.configurator.detail_enabled_help') }}
                        </span>
                    </span>
                </label>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ t('app.job_sources.configurator.detail_fetch_min_score') }}
                    </label>
                    <TextInput
                        v-model.number="detailFetchMinScore"
                        type="number"
                        min="0"
                        max="100"
                        class="mt-2 block w-full max-w-xs"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.detail_fetch_min_score_help') }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ t('app.job_sources.configurator.detail_engine') }}
                    </label>
                    <ChipSelect
                        v-model="detailEngine"
                        class="mt-2"
                        :options="[
                            { value: 'inherit', label: t('app.job_sources.configurator.detail_engine_inherit') },
                            { value: 'http', label: t('app.job_sources.configurator.detail_engine_http') },
                            { value: 'playwright', label: t('app.job_sources.configurator.detail_engine_playwright') },
                        ]"
                        :aria-label="t('app.job_sources.configurator.detail_engine')"
                    />
                </div>
            </div>

            <div
                v-if="suggestPlaywright && activePreviewEngine === 'http'"
                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <p>{{ t('app.job_sources.configurator.suggest_playwright') }}</p>
                <SecondaryButton class="mt-3" type="button" @click="enablePlaywrightAndReload">
                    {{ t('app.job_sources.configurator.load_with_playwright') }}
                </SecondaryButton>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6">
                <div
                    v-if="pendingInteraction"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.interaction_confirm_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.interaction_confirm_help', {
                            type: interactionStepLabel(pendingInteraction),
                        }) }}
                    </p>
                    <p class="mt-3 break-all rounded-lg bg-white/80 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ pendingInteraction.selector || '—' }}
                    </p>
                    <div class="mt-4 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                        <label v-if="pendingInteraction.type === 'click'" class="flex items-center gap-2">
                            <input v-model="pendingInteraction.optional" type="checkbox" class="rounded border-gray-300">
                            {{ t('app.job_sources.configurator.interaction_optional') }}
                        </label>
                        <label v-if="pendingInteraction.type === 'click'" class="flex items-center gap-2">
                            <input v-model="pendingInteraction.repeat_until_gone" type="checkbox" class="rounded border-gray-300">
                            {{ t('app.job_sources.configurator.interaction_repeat') }}
                        </label>
                        <label v-if="pendingInteraction.type === 'wait_for'" class="flex items-center gap-2">
                            <span>{{ t('app.job_sources.configurator.interaction_timeout') }}</span>
                            <input
                                v-model.number="pendingInteraction.timeout_ms"
                                type="number"
                                min="1000"
                                max="120000"
                                step="1000"
                                class="w-24 rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900"
                            >
                        </label>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" @click="confirmInteractionStep">
                            {{ t('app.job_sources.configurator.interaction_add_step') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="clearPendingInteraction">
                            {{ t('app.job_sources.configurator.pick_again') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-if="pendingSelection?.type === 'item'"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.confirm_item_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.confirm_item_help', { count: activePendingCandidate?.matchCount || 0 }) }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.confirm_item_parent_help') }}
                    </p>
                    <p class="mt-3 break-all rounded-lg bg-white/80 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ activePendingCandidate?.selector || '—' }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" @click="confirmItemSelection">
                            {{ t('app.job_sources.configurator.use_as_list_item') }}
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!canUseParentElement"
                            @click="useParentElement"
                        >
                            {{ t('app.job_sources.configurator.use_parent_element') }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="startGroupFromPending">
                            {{ t('app.job_sources.configurator.add_to_group') }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="clearPendingSelection">
                            {{ t('app.job_sources.configurator.pick_again') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-else-if="itemGroupBuilding"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.item_group_building_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.item_group_building_help') }}
                    </p>
                    <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ t('app.job_sources.configurator.item_group_match_count', { count: groupMatchCount }) }}
                    </p>
                    <p
                        v-if="groupPartCountMismatch"
                        class="mt-2 text-sm text-amber-800 dark:text-amber-200"
                    >
                        {{ t('app.job_sources.configurator.item_group_count_mismatch', {
                            counts: groupPartMatchCounts.join(', '),
                            count: groupMatchCount,
                        }) }}
                    </p>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="(part, index) in itemGroupParts"
                            :key="`${part.selector}-${index}`"
                            class="rounded-lg border border-amber-100 bg-white/80 p-3 dark:border-amber-900/40 dark:bg-gray-900/40"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ t('app.job_sources.configurator.item_group_part_label', { index: index + 1 }) }}
                                        · {{ part.matchCount ?? 0 }}
                                    </p>
                                    <p class="mt-1 break-all font-mono text-xs text-gray-700 dark:text-gray-300">
                                        {{ part.selector }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="removeGroupPart(index)"
                                >
                                    {{ t('app.job_sources.configurator.remove') }}
                                </button>
                            </div>
                        </li>
                    </ul>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton
                            type="button"
                            :disabled="itemGroupParts.length < 2"
                            @click="finishGroupSelection"
                        >
                            {{ t('app.job_sources.configurator.finish_group') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="cancelGroupBuilding">
                            {{ t('app.job_sources.configurator.cancel_group') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div
                    v-else-if="pendingSelection?.type === 'detail_field'"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/20"
                >
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.confirm_detail_field_title') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.job_sources.configurator.confirm_detail_field_help') }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.confirm_detail_field_parent_help') }}
                    </p>
                    <p
                        v-if="activePendingCandidate?.tagName"
                        class="mt-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        {{ activePendingCandidate.tagName }}
                        <span v-if="activePendingMatchCount > 1">
                            · {{ t('app.job_sources.configurator.detail_field_match_position', {
                                current: activePendingMatchIndex + 1,
                                total: activePendingMatchCount,
                            }) }}
                        </span>
                    </p>
                    <p
                        v-if="canCycleMatch"
                        class="mt-2 text-xs text-amber-800 dark:text-amber-200"
                    >
                        {{ t('app.job_sources.configurator.confirm_detail_field_match_help') }}
                    </p>
                    <p class="mt-3 break-all rounded-lg bg-white/80 p-2 font-mono text-xs text-gray-700 dark:bg-gray-900/50 dark:text-gray-300">
                        {{ activePendingCandidate?.selector || '—' }}
                    </p>
                    <div class="mt-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('app.job_sources.configurator.field_to_map') }}
                        </label>
                        <ChipSelect
                            v-model="detailSelectedField"
                            class="mt-2"
                            searchable
                            :options="detailFieldSelectOptions"
                            :placeholder="t('app.job_sources.configurator.choose_field')"
                            :no-results-text="t('app.job_sources.configurator.no_field_results')"
                            :aria-label="t('app.job_sources.configurator.field_to_map')"
                        />
                    </div>
                    <div v-if="isDetailCustomFieldSelected" class="mt-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('app.job_sources.configurator.custom_field_label') }}
                        </label>
                        <TextInput
                            v-model="detailCustomFieldLabel"
                            type="text"
                            class="mt-2 block w-full"
                            :placeholder="t('app.job_sources.configurator.custom_field_placeholder')"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" :disabled="!canAssignDetailField" @click="assignDetailFieldSelection">
                            {{ t('app.job_sources.configurator.add_field_mapping') }}
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!canUseParentElement"
                            @click="useParentElement"
                        >
                            {{ t('app.job_sources.configurator.use_parent_element') }}
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!canUseChildElement"
                            @click="useChildElement"
                        >
                            {{ t('app.job_sources.configurator.use_child_element') }}
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!canCycleMatch"
                            @click="usePreviousMatch"
                        >
                            {{ t('app.job_sources.configurator.use_previous_match') }}
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="!canCycleMatch"
                            @click="useNextMatch"
                        >
                            {{ t('app.job_sources.configurator.use_next_match') }}
                        </SecondaryButton>
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
                            searchable
                            :options="fieldSelectOptions"
                            :placeholder="t('app.job_sources.configurator.choose_field')"
                            :no-results-text="t('app.job_sources.configurator.no_field_results')"
                            :aria-label="t('app.job_sources.configurator.field_to_map')"
                        />
                    </div>
                    <div v-if="isCustomFieldSelected" class="mt-4">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('app.job_sources.configurator.custom_field_label') }}
                        </label>
                        <TextInput
                            v-model="customFieldLabel"
                            type="text"
                            class="mt-2 block w-full"
                            :placeholder="t('app.job_sources.configurator.custom_field_placeholder')"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <PrimaryButton type="button" :disabled="!canAssignField" @click="assignFieldSelection">
                            {{ t('app.job_sources.configurator.add_field_mapping') }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="clearPendingSelection">
                            {{ t('app.job_sources.configurator.pick_again') }}
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="isListingTab" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ itemMode === 'group'
                                ? t('app.job_sources.configurator.item_group_heading')
                                : t('app.job_sources.configurator.item_selector') }}
                        </h3>
                        <button
                            v-if="hasListItemConfigured"
                            type="button"
                            class="text-xs text-gray-500 hover:underline dark:text-gray-400"
                            @click="resetItemSelector"
                        >
                            {{ t('app.job_sources.configurator.change_item') }}
                        </button>
                    </div>

                    <template v-if="itemMode === 'group'">
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ t('app.job_sources.configurator.item_group_summary', {
                                parts: itemGroupParts.length,
                                count: groupMatchCount,
                            }) }}
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(part, index) in itemGroupParts"
                                :key="`${part.selector}-${index}`"
                                class="break-all rounded-lg bg-gray-50 p-2 font-mono text-xs text-gray-600 dark:bg-gray-900/50 dark:text-gray-300"
                            >
                                {{ part.selector }}
                            </li>
                        </ul>
                    </template>
                    <template v-else>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ t('app.job_sources.configurator.item_selector_edit_help') }}
                        </p>
                        <TextInput
                            v-model="itemSelector"
                            type="text"
                            class="mt-3 block w-full font-mono text-xs"
                            :placeholder="t('app.job_sources.configurator.item_selector_placeholder')"
                        />
                    </template>
                </div>

                <div v-if="isListingTab" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.pagination_heading') }}
                    </h3>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.pagination_help') }}
                    </p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ t('app.job_sources.configurator.pagination_type') }}
                            </label>
                            <ChipSelect
                                v-model="paginationType"
                                class="mt-2"
                                :options="[
                                    { value: 'none', label: t('app.job_sources.configurator.pagination_none') },
                                    { value: 'query_param', label: t('app.job_sources.configurator.pagination_query_param') },
                                ]"
                                :aria-label="t('app.job_sources.configurator.pagination_type')"
                            />
                        </div>
                        <div v-if="paginationType === 'query_param'" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('app.job_sources.configurator.pagination_param') }}
                                </label>
                                <TextInput
                                    v-model="paginationParam"
                                    type="text"
                                    class="mt-2 block w-full font-mono text-xs"
                                    placeholder="page"
                                />
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ t('app.job_sources.configurator.pagination_max_pages') }}
                                </label>
                                <TextInput
                                    v-model.number="paginationMaxPages"
                                    type="number"
                                    min="1"
                                    max="50"
                                    class="mt-2 block w-full"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isListingTab" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                                        {{ fieldDisplayLabel(field, mapping) }}
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
                        {{ missingRequiredFields.map((field) => fieldDisplayLabel(field)).join(', ') }}
                    </p>
                </div>

                <div v-if="isDetailTab" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ t('app.job_sources.configurator.detail_mapped_fields') }}
                    </h3>

                    <p v-if="!detailMappedFields.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('app.job_sources.configurator.detail_no_fields_mapped') }}
                    </p>

                    <ul v-else class="mt-3 space-y-3">
                        <li
                            v-for="[field, mapping] in detailMappedFields"
                            :key="field"
                            class="rounded-lg border border-gray-100 p-3 dark:border-gray-700"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ detailFieldDisplayLabel(field, mapping) }}
                                    </p>
                                    <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300">
                                        {{ mapping.selector }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="removeDetailFieldMapping(field)"
                                >
                                    {{ t('app.job_sources.configurator.remove') }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-3">
                    <template v-if="isListingTab">
                        <SecondaryButton
                            :disabled="testingExtraction || currentStep < 3"
                            type="button"
                            @click="testExtraction"
                        >
                            {{ testingExtraction ? t('app.job_sources.configurator.testing') : t('app.job_sources.configurator.test_extraction') }}
                        </SecondaryButton>
                        <SecondaryButton
                            :disabled="testingFullFlow || currentStep < 3"
                            type="button"
                            @click="testFullFlow"
                        >
                            {{ testingFullFlow ? t('app.job_sources.configurator.testing_full_flow') : t('app.job_sources.configurator.test_full_flow') }}
                        </SecondaryButton>
                    </template>
                    <SecondaryButton
                        v-else
                        :disabled="testingExtraction || currentStep < 2"
                        type="button"
                        @click="testDetailExtraction"
                    >
                        {{ testingExtraction ? t('app.job_sources.configurator.testing') : t('app.job_sources.configurator.test_detail_extraction') }}
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="saving || (isListingTab && currentStep < 3)"
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
                            v-if="!activePreviewLoaded"
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
            v-if="isListingTab && testResults.length"
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
                            <th
                                v-for="column in testResultColumns"
                                :key="column.key"
                                class="px-3 py-2 font-medium"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="(row, index) in testResults" :key="index">
                            <td
                                v-for="column in testResultColumns"
                                :key="column.key"
                                class="max-w-xs px-3 py-2 text-gray-700 dark:text-gray-300"
                                :class="column.key === 'url' ? 'truncate text-indigo-600 dark:text-indigo-400' : ''"
                            >
                                <a
                                    v-if="column.key === 'url' && row.fields?.url"
                                    :href="row.fields.url"
                                    class="hover:underline"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ row.fields.url }}
                                </a>
                                <span
                                    v-else
                                    :class="column.key === 'job_title' ? 'text-gray-900 dark:text-white' : ''"
                                >
                                    {{ testResultValue(row, column.key) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="!isListingTab && detailTestResults.length"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ t('app.job_sources.configurator.detail_test_results') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.job_sources.configurator.detail_test_results_help') }}
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th
                                v-for="column in detailTestResultColumns"
                                :key="column.key"
                                class="px-3 py-2 font-medium"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="(row, index) in detailTestResults" :key="index">
                            <td
                                v-for="column in detailTestResultColumns"
                                :key="column.key"
                                class="px-3 py-2 align-top text-gray-700 dark:text-gray-300"
                                :class="column.key === 'description'
                                    ? 'max-w-2xl whitespace-pre-wrap'
                                    : 'max-w-xs'"
                            >
                                <span
                                    :class="column.key === 'job_title' || column.key === 'employment_type'
                                        ? 'font-medium text-gray-900 dark:text-white'
                                        : ''"
                                >
                                    {{ testResultValue(row, column.key) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
