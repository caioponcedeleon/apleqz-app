<script setup>
import ApplicationReminderModal from '@/Components/ApplicationReminderModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    defaultReminderDraft,
    formatReminderDateTime,
    formatReminderSchedule,
    reminderPayloadFromDraft,
    splitReminderSchedule,
} from '@/utils/reminderTimeSlots';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, required: true },
    reminders: { type: Array, default: () => [] },
    reminderTypes: { type: Array, default: () => [] },
    reminderFrequencies: { type: Array, default: () => [] },
    moments: { type: Array, default: () => [] },
});

const { t } = useI18n();
const VISIBLE_LIMIT = 5;

const showAllModal = ref(false);
const editModalOpen = ref(false);
const addModalOpen = ref(false);
const editingReminderId = ref(null);
const reminderDraft = ref(null);

const sortedReminders = computed(() =>
    [...props.reminders].sort((a, b) => {
        const dateCompare = (b.remind_at ?? '').localeCompare(a.remind_at ?? '');

        if (dateCompare !== 0) {
            return dateCompare;
        }

        return (b.id ?? 0) - (a.id ?? 0);
    }),
);

const visibleReminders = computed(() => sortedReminders.value.slice(0, VISIBLE_LIMIT));
const hasMoreReminders = computed(() => sortedReminders.value.length > VISIBLE_LIMIT);

const momentOptions = computed(() =>
    props.moments
        .filter((moment) => moment.id && moment.type && moment.occurred_at)
        .map((moment) => ({
            value: String(moment.id),
            label: `${t(`app.moment_types.${moment.type}`)} — ${formatReminderDateTime(moment.occurred_at)}`,
        })),
);

const addForm = useForm({});
const editForm = useForm({});

const isNewReminder = computed(() => editingReminderId.value === null);

const modalErrors = computed(() => {
    const form = isNewReminder.value ? addForm : editForm;

    return {
        type: form.errors.type,
        frequency: form.errors.frequency,
        remind_at: form.errors.remind_at,
        remind_weekday: form.errors.remind_weekday,
        remind_day_of_month: form.errors.remind_day_of_month,
        remind_time: form.errors.remind_time,
        custom_message: form.errors.custom_message,
        application_moment_id: form.errors.application_moment_id,
        is_active: form.errors.is_active,
    };
});

const formatFrequency = (frequency) => t(`app.notifications.frequencies.${frequency}`);
const formatType = (type) => t(`app.notifications.types.${type}`);
const formatSchedule = (reminder) => formatReminderSchedule(reminder.remind_at, reminder.frequency, t);

const reminderToDraft = (reminder) => {
    const schedule = splitReminderSchedule(reminder.remind_at, reminder.frequency);

    return {
        type: reminder.type,
        frequency: reminder.frequency,
        remind_at: schedule.remind_at,
        remind_time: schedule.remind_time,
        remind_weekday: schedule.remind_weekday,
        remind_day_of_month: schedule.remind_day_of_month,
        custom_message: reminder.custom_message ?? '',
        application_moment_id: reminder.application_moment_id
            ? String(reminder.application_moment_id)
            : '',
        is_active: reminder.is_active ?? true,
    };
};

const openAddModal = () => {
    editingReminderId.value = null;
    reminderDraft.value = defaultReminderDraft(props.reminderTypes, props.reminderFrequencies);
    addModalOpen.value = true;
    showAllModal.value = false;
};

const openEditModal = (reminder) => {
    editingReminderId.value = reminder.id;
    reminderDraft.value = reminderToDraft(reminder);
    editModalOpen.value = true;
    addModalOpen.value = false;
    showAllModal.value = false;
};

const closeReminderModal = () => {
    editModalOpen.value = false;
    addModalOpen.value = false;
    editingReminderId.value = null;
    reminderDraft.value = null;
    addForm.clearErrors();
    editForm.clearErrors();
};

const submitDraft = (draft) => {
    const payload = reminderPayloadFromDraft(draft);
    const options = {
        preserveScroll: true,
        onSuccess: () => closeReminderModal(),
    };

    if (isNewReminder.value) {
        addForm
            .transform(() => payload)
            .post(route('applications.reminders.store', props.application.uuid), options);

        return;
    }

    editForm
        .transform(() => payload)
        .patch(
            route('applications.reminders.update', [props.application.uuid, editingReminderId.value]),
            options,
        );
};

const deleteEditDraft = () => {
    if (!editingReminderId.value) {
        closeReminderModal();

        return;
    }

    editForm.delete(
        route('applications.reminders.destroy', [props.application.uuid, editingReminderId.value]),
        {
            preserveScroll: true,
            onSuccess: () => closeReminderModal(),
        },
    );
};

const reminderModalOpen = computed(() => editModalOpen.value || addModalOpen.value);
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ t('app.notifications.title') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ t('app.notifications.hint') }}
        </p>

        <ul
            v-if="sortedReminders.length"
            class="mt-4 space-y-3"
        >
            <li
                v-for="reminder in visibleReminders"
                :key="reminder.id"
            >
                <button
                    type="button"
                    class="flex w-full flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20"
                    @click="openEditModal(reminder)"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ formatType(reminder.type) }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                · {{ formatFrequency(reminder.frequency) }}
                            </span>
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ formatSchedule(reminder) }}
                        </p>
                        <p
                            v-if="reminder.custom_message"
                            class="text-sm text-gray-600 dark:text-gray-300"
                        >
                            {{ reminder.custom_message }}
                        </p>
                        <p
                            v-if="reminder.sent_at"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ t('app.notifications.sent_at', { date: formatReminderDateTime(reminder.sent_at) }) }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-md px-2 py-1 text-xs font-medium"
                        :class="reminder.is_active
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ reminder.is_active
                            ? t('app.notifications.active')
                            : t('app.notifications.paused') }}
                    </span>
                </button>
            </li>
        </ul>

        <p
            v-else
            class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-4 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
        >
            {{ t('app.notifications.empty') }}
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <PrimaryButton
                type="button"
                @click="openAddModal"
            >
                {{ t('app.notifications.add_button') }}
            </PrimaryButton>

            <SecondaryButton
                v-if="hasMoreReminders"
                type="button"
                @click="showAllModal = true"
            >
                {{ t('app.notifications.show_all', { count: sortedReminders.length }) }}
            </SecondaryButton>
        </div>

        <Modal
            :show="showAllModal"
            max-width="lg"
            @close="showAllModal = false"
        >
            <div class="flex max-h-[calc(100vh-3rem)] flex-col px-6 py-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('app.notifications.all_reminders_title') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('app.notifications.all_reminders_hint') }}
                </p>

                <ul class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto pe-1">
                    <li
                        v-for="reminder in sortedReminders"
                        :key="`all-${reminder.id}`"
                    >
                        <button
                            type="button"
                            class="flex w-full flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20"
                            @click="openEditModal(reminder)"
                        >
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatType(reminder.type) }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        · {{ formatFrequency(reminder.frequency) }}
                                    </span>
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    {{ formatSchedule(reminder) }}
                                </p>
                                <p
                                    v-if="reminder.custom_message"
                                    class="text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{ reminder.custom_message }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-md px-2 py-1 text-xs font-medium"
                                :class="reminder.is_active
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                                    : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            >
                                {{ reminder.is_active
                                    ? t('app.notifications.active')
                                    : t('app.notifications.paused') }}
                            </span>
                        </button>
                    </li>
                </ul>

                <div class="mt-4 shrink-0 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <SecondaryButton
                        type="button"
                        @click="showAllModal = false"
                    >
                        {{ t('app.actions.close') }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <ApplicationReminderModal
            :show="reminderModalOpen"
            :reminder="reminderDraft"
            :reminder-types="reminderTypes"
            :reminder-frequencies="reminderFrequencies"
            :moment-options="momentOptions"
            :errors="modalErrors"
            :is-new="isNewReminder"
            @close="closeReminderModal"
            @save="submitDraft"
            @delete="deleteEditDraft"
        />
    </div>
</template>
