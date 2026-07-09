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
import ToggleSwitch from '@/Components/ToggleSwitch.vue';
import {
    REMINDER_DAYS_OF_MONTH,
    REMINDER_TIME_SLOTS,
    REMINDER_WEEKDAYS,
    canSubmitReminderDraft,
} from '@/utils/reminderTimeSlots';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    reminder: { type: Object, default: null },
    reminderTypes: { type: Array, default: () => [] },
    reminderFrequencies: { type: Array, default: () => [] },
    momentOptions: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    isNew: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save', 'delete']);

const { t } = useI18n();
const draft = ref({
    type: 'check_in',
    frequency: 'once',
    remind_at: '',
    remind_time: '09:00',
    remind_weekday: '1',
    remind_day_of_month: '1',
    custom_message: '',
    application_moment_id: '',
    is_active: true,
});
const showDeleteConfirm = ref(false);

const typeOptions = computed(() =>
    props.reminderTypes.map((type) => ({
        value: type,
        label: t(`app.notifications.types.${type}`),
    })),
);

const frequencyOptions = computed(() =>
    props.reminderFrequencies.map((frequency) => ({
        value: frequency,
        label: t(`app.notifications.frequencies.${frequency}`),
    })),
);

const timeOptions = computed(() =>
    REMINDER_TIME_SLOTS.map((slot) => ({
        value: slot,
        label: slot,
    })),
);

const weekdayOptions = computed(() =>
    REMINDER_WEEKDAYS.map((weekday) => ({
        value: weekday,
        label: t(`app.notifications.weekdays.${weekday}`),
    })),
);

const dayOfMonthOptions = computed(() =>
    REMINDER_DAYS_OF_MONTH.map((day) => ({
        value: day,
        label: day,
    })),
);

const showCustomMessage = computed(() => draft.value.type === 'custom');
const showMomentSelect = computed(() => draft.value.type === 'moment');
const showDateField = computed(() => draft.value.frequency === 'once');
const showWeekdayField = computed(() => draft.value.frequency === 'weekly');
const showDayOfMonthField = computed(() => draft.value.frequency === 'monthly');
const canSave = computed(() => canSubmitReminderDraft(draft.value));

const scheduleLabel = computed(() => {
    if (draft.value.frequency === 'once') {
        return t('app.notifications.remind_on');
    }

    if (draft.value.frequency === 'weekly') {
        return t('app.notifications.weekday_label');
    }

    if (draft.value.frequency === 'monthly') {
        return t('app.notifications.day_of_month_label');
    }

    return '';
});

watch(
    () => [props.show, props.reminder],
    () => {
        if (props.show && props.reminder) {
            draft.value = {
                type: props.reminder.type ?? props.reminderTypes[0] ?? 'check_in',
                frequency: props.reminder.frequency ?? props.reminderFrequencies[0] ?? 'once',
                remind_at: props.reminder.remind_at ?? '',
                remind_time: props.reminder.remind_time ?? '09:00',
                remind_weekday: props.reminder.remind_weekday ?? '1',
                remind_day_of_month: props.reminder.remind_day_of_month ?? '1',
                custom_message: props.reminder.custom_message ?? '',
                application_moment_id: props.reminder.application_moment_id
                    ? String(props.reminder.application_moment_id)
                    : '',
                is_active: props.reminder.is_active ?? true,
            };
            showDeleteConfirm.value = false;
        }
    },
    { immediate: true },
);

const save = () => {
    if (!canSave.value) {
        return;
    }

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
                {{ isNew ? t('app.notifications.add') : t('app.notifications.edit_reminder') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ isNew ? t('app.notifications.add_hint') : t('app.notifications.modal_hint') }}
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel :value="t('app.notifications.reason')" />
                    <ChipSelect
                        v-model="draft.type"
                        class="mt-1"
                        :options="typeOptions"
                    />
                    <InputError class="mt-1" :message="errors.type" />
                </div>

                <div>
                    <InputLabel :value="t('app.notifications.frequency_label')" />
                    <ChipSelect
                        v-model="draft.frequency"
                        class="mt-1"
                        :options="frequencyOptions"
                    />
                    <InputError class="mt-1" :message="errors.frequency" />
                </div>

                <div v-if="showDateField">
                    <InputLabel :value="scheduleLabel" />
                    <TextInput
                        v-model="draft.remind_at"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="errors.remind_at" />
                </div>

                <div v-else-if="showWeekdayField">
                    <InputLabel :value="scheduleLabel" />
                    <ChipSelect
                        v-model="draft.remind_weekday"
                        class="mt-1"
                        :options="weekdayOptions"
                    />
                    <InputError class="mt-1" :message="errors.remind_weekday" />
                </div>

                <div v-else-if="showDayOfMonthField">
                    <InputLabel :value="scheduleLabel" />
                    <ChipSelect
                        v-model="draft.remind_day_of_month"
                        class="mt-1"
                        :options="dayOfMonthOptions"
                    />
                    <InputError class="mt-1" :message="errors.remind_day_of_month" />
                </div>

                <div :class="showDateField || showWeekdayField || showDayOfMonthField ? '' : 'sm:col-span-2'">
                    <InputLabel :value="t('app.notifications.remind_time')" />
                    <ChipSelect
                        v-model="draft.remind_time"
                        class="mt-1"
                        :options="timeOptions"
                    />
                    <p
                        v-if="draft.frequency === 'daily'"
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('app.notifications.daily_hint') }}
                    </p>
                    <InputError class="mt-1" :message="errors.remind_time" />
                </div>

                <div
                    v-if="showMomentSelect"
                    class="sm:col-span-2"
                >
                    <InputLabel :value="t('app.notifications.linked_moment')" />
                    <ChipSelect
                        v-model="draft.application_moment_id"
                        class="mt-1"
                        :placeholder="t('app.notifications.linked_moment_placeholder')"
                        :options="momentOptions"
                    />
                    <InputError class="mt-1" :message="errors.application_moment_id" />
                </div>

                <div
                    v-if="showCustomMessage"
                    class="sm:col-span-2"
                >
                    <InputLabel :value="t('app.notifications.custom_message')" />
                    <textarea
                        v-model="draft.custom_message"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                    />
                    <InputError class="mt-1" :message="errors.custom_message" />
                </div>

                <div
                    v-if="!isNew"
                    class="sm:col-span-2"
                >
                    <InputLabel :value="t('app.notifications.status_label')" />
                    <ToggleSwitch
                        v-model="draft.is_active"
                        class="mt-2"
                        :label="draft.is_active
                            ? t('app.notifications.active')
                            : t('app.notifications.paused')"
                    />
                    <InputError class="mt-1" :message="errors.is_active" />
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <DangerButton
                    v-if="!isNew"
                    type="button"
                    class="justify-center sm:me-auto"
                    @click="showDeleteConfirm = true"
                >
                    {{ t('app.actions.delete') }}
                </DangerButton>

                <div class="flex flex-col-reverse gap-3 sm:ms-auto sm:flex-row">
                    <SecondaryButton type="button" class="justify-center" @click="emit('close')">
                        {{ t('app.actions.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        class="justify-center"
                        :disabled="!canSave"
                        @click="save"
                    >
                        {{ isNew ? t('app.notifications.add_button') : t('app.actions.save') }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>

    <ConfirmDeleteModal
        :show="showDeleteConfirm"
        :title="t('app.notifications.delete_title')"
        :message="t('app.notifications.delete_confirm')"
        @close="showDeleteConfirm = false"
        @confirm="confirmDelete"
    />
</template>
