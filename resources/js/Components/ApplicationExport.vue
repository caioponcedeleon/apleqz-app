<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useI18n();

const showModal = ref(false);
const format = ref('xlsx');
const agenturFurArbeit = ref(false);
const selectedFields = ref(['position', 'company', 'applied_at', 'status', 'events']);
const errorMessage = ref('');

const formats = ['txt', 'docx', 'xlsx', 'pdf'];

const fieldOptions = [
    { key: 'position', label: 'app.applications.position', lockedForAgentur: true },
    { key: 'company', label: 'app.applications.company', lockedForAgentur: true },
    { key: 'applied_at', label: 'app.applications.applied_at', lockedForAgentur: true },
    { key: 'status', label: 'app.applications.status', lockedForAgentur: true },
    { key: 'events', label: 'app.export.field_events', lockedForAgentur: false },
];

const agenturFields = ['position', 'company', 'applied_at', 'status'];

watch(agenturFurArbeit, (enabled) => {
    if (enabled) {
        selectedFields.value = [
            ...agenturFields,
            ...(selectedFields.value.includes('events') ? ['events'] : []),
        ];
    }
});

const canSubmit = computed(() => {
    if (agenturFurArbeit.value) {
        return true;
    }

    return selectedFields.value.length > 0;
});

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const appendField = (form, name, value) => {
    if (value === undefined || value === null || value === '') {
        return;
    }

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
};

const submitExport = () => {
    errorMessage.value = '';

    if (!canSubmit.value) {
        errorMessage.value = t('app.export.fields_required');

        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('applications.export');
    form.style.display = 'none';

    appendField(form, '_token', csrfToken());
    appendField(form, 'format', format.value);
    appendField(form, 'agentur_fur_arbeit', agenturFurArbeit.value ? '1' : '0');
    appendField(form, 'search', props.filters.search);
    appendField(form, 'status', props.filters.status);
    appendField(form, 'area_id', props.filters.area_id);
    appendField(form, 'wave_id', props.filters.wave_id);
    appendField(form, 'sort', props.filters.sort);
    appendField(form, 'direction', props.filters.direction);

    const fields = agenturFurArbeit.value
        ? [
              ...agenturFields,
              ...(selectedFields.value.includes('events') ? ['events'] : []),
          ]
        : selectedFields.value;

    fields.forEach((field) => appendField(form, 'fields[]', field));

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    showModal.value = false;
};
</script>

<template>
    <div>
        <SecondaryButton type="button" @click="showModal = true">
            {{ t('app.export.button') }}
        </SecondaryButton>

        <Modal :show="showModal" max-width="lg" @close="showModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('app.export.title') }}
                </h3>

                <div class="mt-6 space-y-6">
                    <div>
                        <InputLabel :value="t('app.export.format')" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="item in formats"
                                :key="item"
                                class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="format"
                                    type="radio"
                                    name="export-format"
                                    :value="item"
                                    class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>{{ t(`app.export.formats.${item}`) }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel :value="t('app.export.fields')" />
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="option in fieldOptions"
                                :key="option.key"
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                :class="
                                    agenturFurArbeit && option.lockedForAgentur
                                        ? 'opacity-60'
                                        : 'cursor-pointer'
                                "
                            >
                                <Checkbox
                                    v-model:checked="selectedFields"
                                    :value="option.key"
                                    :disabled="agenturFurArbeit && option.lockedForAgentur"
                                />
                                <span>{{ t(option.label) }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                        <label class="flex cursor-pointer items-start gap-2">
                            <Checkbox v-model:checked="agenturFurArbeit" />
                            <span class="text-sm text-gray-800 dark:text-gray-200">
                                <span class="font-medium">{{ t('app.export.agentur') }}</span>
                                <span class="mt-1 block text-gray-600 dark:text-gray-400">
                                    {{ t('app.export.agentur_hint') }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <InputError :message="errorMessage" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showModal = false">
                        {{ t('app.actions.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton type="button" :disabled="!canSubmit" @click="submitExport">
                        {{ t('app.export.submit') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
