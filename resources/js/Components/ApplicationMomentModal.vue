<script setup>
import ChipSelect from '@/Components/ChipSelect.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    moment: { type: Object, default: null },
    momentTypes: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    isNew: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save', 'delete']);

const { t } = useI18n();
const draft = ref({ type: '', occurred_at: '', notes: '' });
const showDeleteConfirm = ref(false);

const momentTypeOptions = computed(() =>
    props.momentTypes.map((type) => ({
        value: type,
        label: t(`app.moment_types.${type}`),
    })),
);

watch(
    () => [props.show, props.moment],
    () => {
        if (props.show && props.moment) {
            draft.value = {
                type: props.moment.type ?? props.momentTypes[0] ?? 'feedback',
                occurred_at: props.moment.occurred_at ?? '',
                notes: props.moment.notes ?? '',
            };
            showDeleteConfirm.value = false;
        }
    },
    { immediate: true },
);

const save = () => {
    emit('save', { ...draft.value });
};

const confirmDelete = () => {
    emit('delete');
    showDeleteConfirm.value = false;
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="px-6 py-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ isNew ? t('app.applications.add_moment') : t('app.applications.edit_moment') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.applications.moment_modal_hint') }}
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel :value="t('app.applications.moment_type')" />
                    <ChipSelect
                        v-model="draft.type"
                        class="mt-1"
                        :options="momentTypeOptions"
                    />
                    <InputError class="mt-1" :message="errors.type" />
                </div>

                <div>
                    <InputLabel :value="t('app.applications.moment_date')" />
                    <TextInput
                        v-model="draft.occurred_at"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="errors.occurred_at" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel :value="t('app.applications.moment_notes')" />
                    <textarea
                        v-model="draft.notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        :placeholder="t('app.applications.moment_notes_placeholder')"
                    />
                    <InputError class="mt-1" :message="errors.notes" />
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <DangerButton
                    v-if="!isNew"
                    type="button"
                    class="justify-center sm:me-auto"
                    @click="showDeleteConfirm = true"
                >
                    {{ t('app.applications.remove_moment') }}
                </DangerButton>

                <div class="flex flex-col-reverse gap-3 sm:ms-auto sm:flex-row">
                    <SecondaryButton type="button" class="justify-center" @click="emit('close')">
                        {{ t('app.actions.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton type="button" class="justify-center" @click="save">
                        {{ t('app.actions.save') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>

    <ConfirmDeleteModal
        :show="showDeleteConfirm"
        :title="t('app.applications.delete_moment_title')"
        :message="t('app.applications.delete_moment_message')"
        @close="showDeleteConfirm = false"
        @confirm="confirmDelete"
    />
</template>
