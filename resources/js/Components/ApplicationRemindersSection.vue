<script setup>
import ChipSelect from '@/Components/ChipSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, required: true },
    reminders: { type: Array, default: () => [] },
    reminderTypes: { type: Array, default: () => [] },
    reminderFrequencies: { type: Array, default: () => [] },
    moments: { type: Array, default: () => [] },
});

const { t } = useI18n();

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

const momentOptions = computed(() =>
    props.moments
        .filter((moment) => moment.id && moment.type && moment.occurred_at)
        .map((moment) => ({
            value: String(moment.id),
            label: `${t(`app.moment_types.${moment.type}`)} — ${formatDate(moment.occurred_at)}`,
        })),
);

const form = useForm({
    type: props.reminderTypes[0] ?? 'check_in',
    frequency: props.reminderFrequencies[0] ?? 'once',
    remind_at: '',
    custom_message: '',
    application_moment_id: '',
});

const showCustomMessage = computed(() => form.type === 'custom');
const showMomentSelect = computed(() => form.type === 'moment');

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};

const formatFrequency = (frequency) => t(`app.notifications.frequencies.${frequency}`);
const formatType = (type) => t(`app.notifications.types.${type}`);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            application_moment_id: data.application_moment_id || null,
            custom_message: data.custom_message || null,
        }))
        .post(route('applications.reminders.store', props.application.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('remind_at', 'custom_message', 'application_moment_id'),
        });
};

const toggleActive = (reminder) => {
    router.patch(
        route('applications.reminders.update', [props.application.id, reminder.id]),
        {
            type: reminder.type,
            frequency: reminder.frequency,
            remind_at: reminder.remind_at?.slice?.(0, 10) ?? reminder.remind_at,
            custom_message: reminder.custom_message,
            application_moment_id: reminder.application_moment_id,
            is_active: !reminder.is_active,
        },
        { preserveScroll: true },
    );
};

const remove = (reminder) => {
    if (!window.confirm(t('app.notifications.delete_confirm'))) {
        return;
    }

    router.delete(
        route('applications.reminders.destroy', [props.application.id, reminder.id]),
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ t('app.notifications.title') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ t('app.notifications.hint') }}
        </p>

        <ul
            v-if="reminders.length"
            class="mt-4 space-y-3"
        >
            <li
                v-for="reminder in reminders"
                :key="reminder.id"
                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-600 dark:bg-gray-900/40"
            >
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ formatType(reminder.type) }}
                        <span class="font-normal text-gray-500 dark:text-gray-400">
                            · {{ formatFrequency(reminder.frequency) }}
                        </span>
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ t('app.notifications.remind_on') }}: {{ formatDate(reminder.remind_at) }}
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
                        {{ t('app.notifications.sent_at', { date: formatDate(reminder.sent_at) }) }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs font-medium transition"
                        :class="reminder.is_active
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        @click="toggleActive(reminder)"
                    >
                        {{ reminder.is_active
                            ? t('app.notifications.active')
                            : t('app.notifications.paused') }}
                    </button>
                    <SecondaryButton
                        type="button"
                        class="!px-2 !py-1 !text-xs"
                        @click="remove(reminder)"
                    >
                        {{ t('app.actions.delete') }}
                    </SecondaryButton>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="mt-4 rounded-lg border border-dashed border-gray-300 px-4 py-4 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
        >
            {{ t('app.notifications.empty') }}
        </p>

        <form
            class="mt-6 space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-600 dark:bg-gray-800/50"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium text-gray-900 dark:text-white">
                {{ t('app.notifications.add') }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel :value="t('app.notifications.reason')" />
                    <ChipSelect
                        v-model="form.type"
                        class="mt-1"
                        :options="typeOptions"
                    />
                    <InputError class="mt-1" :message="form.errors.type" />
                </div>

                <div>
                    <InputLabel :value="t('app.notifications.frequency_label')" />
                    <ChipSelect
                        v-model="form.frequency"
                        class="mt-1"
                        :options="frequencyOptions"
                    />
                    <InputError class="mt-1" :message="form.errors.frequency" />
                </div>

                <div>
                    <InputLabel :value="t('app.notifications.remind_on')" />
                    <TextInput
                        v-model="form.remind_at"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.remind_at" />
                </div>

                <div v-if="showMomentSelect">
                    <InputLabel :value="t('app.notifications.linked_moment')" />
                    <ChipSelect
                        v-model="form.application_moment_id"
                        class="mt-1"
                        :placeholder="t('app.notifications.linked_moment_placeholder')"
                        :options="momentOptions"
                    />
                    <InputError class="mt-1" :message="form.errors.application_moment_id" />
                </div>
            </div>

            <div v-if="showCustomMessage">
                <InputLabel :value="t('app.notifications.custom_message')" />
                <textarea
                    v-model="form.custom_message"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                />
                <InputError class="mt-1" :message="form.errors.custom_message" />
            </div>

            <PrimaryButton
                type="submit"
                :disabled="form.processing"
            >
                {{ t('app.notifications.add_button') }}
            </PrimaryButton>
        </form>
    </div>
</template>
