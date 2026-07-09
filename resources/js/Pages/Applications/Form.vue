<script setup>
import ApplicationRemindersSection from '@/Components/ApplicationRemindersSection.vue';
import ApplicationFormPreview from '@/Components/ApplicationFormPreview.vue';
import ApplicationMomentModal from '@/Components/ApplicationMomentModal.vue';
import ChipSelect from '@/Components/ChipSelect.vue';
import FileManager from '@/Components/FileManager.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MomentBadge from '@/Components/MomentBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatusSelector from '@/Components/StatusSelector.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, default: null },
    areas: { type: Array, default: () => [] },
    waves: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    momentTypes: { type: Array, default: () => [] },
    reminderTypes: { type: Array, default: () => [] },
    reminderFrequencies: { type: Array, default: () => [] },
    canUploadApplicationFiles: { type: Boolean, default: false },
    canCreateApplication: { type: Boolean, default: true },
});

const { t } = useI18n();
const page = usePage();
const isEdit = computed(() => props.application !== null);
const hasAreas = computed(() => props.areas.length > 0);
const hasWaves = computed(() => props.waves.length > 0);
const showNoAreasState = computed(() => !isEdit.value && !hasAreas.value);
const showNoWavesState = computed(() => !isEdit.value && hasAreas.value && !hasWaves.value);
const canUseForm = computed(() => isEdit.value || (hasAreas.value && hasWaves.value));
const showOnboardingPreview = computed(
    () => Boolean(page.props.onboarding?.show) && !isEdit.value && !canUseForm.value,
);

const statusColors = {
    a_candidatar: 'sky',
    esperando: 'amber',
    rejeitado: 'red',
    oferta: 'emerald',
    recusado: 'orange',
    retirada: 'slate',
    cancelada: 'zinc',
};

const momentTypeColors = {
    feedback: 'indigo',
    interview: 'violet',
    offer: 'emerald',
    rejection: 'red',
    other: 'slate',
};

const editableMoments = computed(() =>
    (props.application?.moments ?? []).filter(
        (moment) => !moment.is_system && moment.type !== 'status_change',
    ),
);

const statusHistory = computed(() =>
    [...(props.application?.moments ?? [])]
        .filter((moment) => moment.is_system || moment.type === 'status_change')
        .sort((a, b) => {
            const dateCompare = (a.occurred_at ?? '').localeCompare(b.occurred_at ?? '');

            if (dateCompare !== 0) {
                return dateCompare;
            }

            return (a.id ?? 0) - (b.id ?? 0);
        }),
);

const timelineItems = computed(() => {
    const items = statusHistory.value.map((event) => ({
        key: `status-${event.id}`,
        kind: 'status',
        occurred_at: event.occurred_at,
        status: event.notes,
    }));

    editableMoments.value.forEach((moment) => {
        items.push({
            key: `moment-${moment.id}`,
            kind: 'moment',
            moment,
            occurred_at: moment.occurred_at,
            type: moment.type,
            notes: moment.notes,
        });
    });

    return items.sort((a, b) => {
        const dateCompare = (a.occurred_at ?? '').localeCompare(b.occurred_at ?? '');

        if (dateCompare !== 0) {
            return dateCompare;
        }

        return a.kind === 'status' ? -1 : 1;
    });
});

const timelineCardClass =
    'w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left dark:border-gray-600 dark:bg-gray-900/40';

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};

const momentModalOpen = ref(false);
const editingMomentId = ref(null);
const momentDraft = ref({ type: 'feedback', occurred_at: '', notes: '' });
const momentForm = useForm({
    type: '',
    occurred_at: '',
    notes: '',
});

const isNewMoment = computed(() => editingMomentId.value === null);

const momentModalErrors = computed(() => ({
    type: momentForm.errors.type,
    occurred_at: momentForm.errors.occurred_at,
    notes: momentForm.errors.notes,
}));

const openMomentModal = (moment = null) => {
    editingMomentId.value = moment?.id ?? null;

    momentDraft.value = moment
        ? {
            id: moment.id,
            type: moment.type,
            occurred_at: moment.occurred_at?.slice?.(0, 10) ?? '',
            notes: moment.notes ?? '',
        }
        : {
            id: null,
            type: props.momentTypes[0] ?? 'feedback',
            occurred_at: '',
            notes: '',
        };

    momentModalOpen.value = true;
};

const closeMomentModal = () => {
    momentModalOpen.value = false;
    editingMomentId.value = null;
    momentForm.clearErrors();
};

const saveMomentDraft = (draft) => {
    momentForm.type = draft.type;
    momentForm.occurred_at = draft.occurred_at;
    momentForm.notes = draft.notes ?? '';

    const options = {
        preserveScroll: true,
        onSuccess: () => closeMomentModal(),
    };

    if (editingMomentId.value) {
        momentForm.patch(
            route('applications.moments.update', [props.application.id, editingMomentId.value]),
            options,
        );

        return;
    }

    momentForm.post(route('applications.moments.store', props.application.id), options);
};

const deleteMomentDraft = () => {
    if (!editingMomentId.value) {
        closeMomentModal();

        return;
    }

    router.delete(
        route('applications.moments.destroy', [props.application.id, editingMomentId.value]),
        {
            preserveScroll: true,
            onSuccess: () => closeMomentModal(),
        },
    );
};

const form = useForm({
    area_id: props.application?.area_id ?? props.areas[0]?.id ?? '',
    application_wave_id: props.application?.application_wave_id
        ?? page.props.selectedWave?.id
        ?? props.waves[0]?.id
        ?? '',
    position: props.application?.position ?? '',
    company: props.application?.company ?? '',
    location: props.application?.location ?? '',
    applied_at: props.application?.applied_at?.slice?.(0, 10) ?? '',
    status: props.application?.status ?? 'esperando',
    channel: props.application?.channel ?? '',
    notes: props.application?.notes ?? '',
    job_url: props.application?.job_url ?? '',
    create_another: false,
});

const requiresAppliedDate = computed(() => form.status !== 'a_candidatar');

const appliedAtLabel = computed(() =>
    requiresAppliedDate.value
        ? t('app.applications.applied_at')
        : t('app.applications.planned_apply_at'),
);

const waveOptions = computed(() =>
    props.waves.map((wave) => ({
        value: wave.id,
        label: wave.name,
    })),
);

const areaOptions = computed(() =>
    props.areas.map((area) => ({
        value: area.id,
        label: area.name,
    })),
);

const submitDetails = (createAnother = false) => {
    if (!canUseForm.value) {
        return;
    }

    form.create_another = createAnother;

    const options = { preserveScroll: true };

    if (isEdit.value) {
        form.put(route('applications.update', props.application.id), options);
    } else {
        form.post(route('applications.store'), options);
    }
};

const applicationFiles = computed(() => props.application?.files ?? []);
const applicationReminders = computed(() => props.application?.reminders ?? []);

const downloadApplicationFileUrl = (file) =>
    route('applications.files.download', [props.application.id, file.id]);

const previewApplicationFileUrl = (file) =>
    route('applications.files.preview', [props.application.id, file.id]);

const deleteApplicationFileUrl = (file) =>
    route('applications.files.destroy', [props.application.id, file.id]);

const renameApplicationFileUrl = (file) =>
    route('applications.files.update', [props.application.id, file.id]);
</script>

<template>
    <Head :title="isEdit ? t('app.applications.edit') : t('app.applications.new')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                {{ isEdit ? t('app.applications.edit') : t('app.applications.new') }}
            </h2>
        </template>

        <div class="py-8">
            <div
                data-onboarding="application-form"
                class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8"
            >
                <ApplicationFormPreview
                    v-if="showOnboardingPreview"
                    :statuses="statuses"
                />

                <div
                    v-else-if="showNoAreasState"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30"
                >
                    <h3 class="text-lg font-semibold text-amber-950 dark:text-amber-100">
                        {{ t('app.applications.no_areas_title') }}
                    </h3>
                    <p class="mt-3 text-sm leading-relaxed text-amber-900/90 dark:text-amber-100/90">
                        {{ t('app.applications.area_explanation') }}
                    </p>
                    <p class="mt-3 text-sm text-amber-900/80 dark:text-amber-200/80">
                        {{ t('app.applications.no_areas_body') }}
                    </p>
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <Link :href="route('areas.index')">
                            <PrimaryButton type="button">
                                {{ t('app.applications.no_areas_go_to_areas') }}
                            </PrimaryButton>
                        </Link>
                        <Link
                            :href="route('applications.index')"
                            class="text-sm text-amber-900/80 hover:underline dark:text-amber-200/80"
                        >
                            {{ t('app.actions.cancel') }}
                        </Link>
                    </div>
                </div>

                <div
                    v-else-if="showNoWavesState"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30"
                >
                    <h3 class="text-lg font-semibold text-amber-950 dark:text-amber-100">
                        {{ t('app.applications.no_waves_title') }}
                    </h3>
                    <p class="mt-3 text-sm leading-relaxed text-amber-900/90 dark:text-amber-100/90">
                        {{ t('app.applications.wave_explanation') }}
                    </p>
                    <p class="mt-3 text-sm text-amber-900/80 dark:text-amber-200/80">
                        {{ t('app.applications.no_waves_body') }}
                    </p>
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <Link :href="route('waves.index')">
                            <PrimaryButton type="button">
                                {{ t('app.applications.no_waves_go_to_waves') }}
                            </PrimaryButton>
                        </Link>
                        <Link
                            :href="route('applications.index')"
                            class="text-sm text-amber-900/80 hover:underline dark:text-amber-200/80"
                        >
                            {{ t('app.actions.cancel') }}
                        </Link>
                    </div>
                </div>

                <template v-else>
                    <form
                        :data-onboarding="isEdit ? 'application-manage' : undefined"
                        class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                        @submit.prevent="submitDetails(false)"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel :value="t('app.applications.wave')" />
                                <ChipSelect
                                    v-model="form.application_wave_id"
                                    class="mt-1"
                                    :options="waveOptions"
                                />
                                <InputError class="mt-1" :message="form.errors.application_wave_id" />
                            </div>

                            <div>
                                <InputLabel :value="t('app.applications.area')" />
                                <ChipSelect
                                    v-model="form.area_id"
                                    class="mt-1"
                                    :options="areaOptions"
                                />
                                <InputError class="mt-1" :message="form.errors.area_id" />
                            </div>

                            <div>
                                <InputLabel :value="t('app.applications.position')" />
                                <TextInput v-model="form.position" class="mt-1 block w-full" required />
                                <InputError class="mt-1" :message="form.errors.position" />
                            </div>

                            <div>
                                <InputLabel :value="t('app.applications.company')" />
                                <TextInput v-model="form.company" class="mt-1 block w-full" required />
                                <InputError class="mt-1" :message="form.errors.company" />
                            </div>

                            <div>
                                <InputLabel :value="t('app.applications.location')" />
                                <TextInput v-model="form.location" class="mt-1 block w-full" />
                            </div>

                            <div>
                                <InputLabel :value="appliedAtLabel" />
                                <TextInput
                                    v-model="form.applied_at"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :required="requiresAppliedDate"
                                />
                                <InputError class="mt-1" :message="form.errors.applied_at" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputLabel :value="t('app.applications.status')" />
                                <StatusSelector
                                    v-model="form.status"
                                    :statuses="statuses"
                                    class="mt-2"
                                />
                                <InputError class="mt-1" :message="form.errors.status" />
                            </div>

                            <div>
                                <InputLabel :value="t('app.applications.channel')" />
                                <TextInput v-model="form.channel" class="mt-1 block w-full" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputLabel :value="t('app.applications.job_url')" />
                                <TextInput v-model="form.job_url" type="url" class="mt-1 block w-full" />
                                <InputError class="mt-1" :message="form.errors.job_url" />
                            </div>

                            <div class="sm:col-span-2">
                                <InputLabel :value="t('app.applications.notes')" />
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                        </div>

                        <div
                            v-if="canUploadApplicationFiles"
                            class="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ t('app.applications.files_title') }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('app.applications.files_hint') }}
                            </p>

                            <div class="mt-4">
                                <FileManager
                                    v-if="isEdit"
                                    :files="applicationFiles"
                                    :upload-url="route('applications.files.store', application.id)"
                                    :download-url="downloadApplicationFileUrl"
                                    :preview-url="previewApplicationFileUrl"
                                    :delete-url="deleteApplicationFileUrl"
                                    :rename-url="renameApplicationFileUrl"
                                    multiple
                                />
                                <p
                                    v-else
                                    class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
                                >
                                    {{ t('app.applications.files_save_first') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <PrimaryButton :disabled="form.processing" type="submit">
                                {{ t('app.actions.save') }}
                            </PrimaryButton>
                            <SecondaryButton
                                v-if="!isEdit"
                                :disabled="form.processing"
                                type="button"
                                @click="submitDetails(true)"
                            >
                                {{ t('app.actions.save_and_create_another') }}
                            </SecondaryButton>
                            <Link
                                :href="route('applications.index')"
                                class="text-sm text-gray-600 hover:underline dark:text-gray-400"
                            >
                                {{ t('app.actions.cancel') }}
                            </Link>
                        </div>
                    </form>

                    <div
                        v-if="isEdit"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ t('app.applications.moments_title') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('app.applications.moments_hint') }}
                                </p>
                            </div>
                            <SecondaryButton type="button" @click="openMomentModal()">
                                {{ t('app.applications.add_moment') }}
                            </SecondaryButton>
                        </div>

                        <p
                            v-if="!timelineItems.length"
                            class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
                        >
                            {{ t('app.applications.moments_empty') }}
                        </p>

                        <div v-else class="relative">
                            <div
                                aria-hidden="true"
                                class="pointer-events-none absolute bottom-2 left-3 top-2 w-px -translate-x-1/2 bg-gray-200 dark:bg-gray-600"
                            />

                            <ol class="relative space-y-4">
                                <li
                                    v-for="item in timelineItems"
                                    :key="item.key"
                                    class="relative ps-8"
                                >
                                    <span
                                        class="absolute left-3 top-4 z-10 size-2.5 -translate-x-1/2 rounded-full bg-indigo-400 ring-4 ring-white dark:bg-indigo-500 dark:ring-gray-800"
                                    />

                                    <div
                                        v-if="item.kind === 'status'"
                                        :class="timelineCardClass"
                                    >
                                        <div class="flex flex-wrap items-center gap-3">
                                            <StatusBadge
                                                inline
                                                :status="item.status"
                                                :color="statusColors[item.status] ?? 'slate'"
                                            />
                                            <time class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatDate(item.occurred_at) }}
                                            </time>
                                        </div>
                                    </div>

                                    <button
                                        v-else
                                        type="button"
                                        :class="[
                                            timelineCardClass,
                                            'transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20',
                                        ]"
                                        @click="openMomentModal(item.moment)"
                                    >
                                        <div class="flex flex-wrap items-center gap-3">
                                            <MomentBadge
                                                :type="item.type"
                                                :color="momentTypeColors[item.type] ?? 'slate'"
                                            />
                                            <time class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatDate(item.occurred_at) }}
                                            </time>
                                        </div>
                                        <p
                                            v-if="item.notes"
                                            class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300"
                                        >
                                            {{ item.notes }}
                                        </p>
                                    </button>
                                </li>
                            </ol>
                        </div>

                        <ApplicationMomentModal
                            :show="momentModalOpen"
                            :moment="momentDraft"
                            :moment-types="momentTypes"
                            :errors="momentModalErrors"
                            :is-new="isNewMoment"
                            @close="closeMomentModal"
                            @save="saveMomentDraft"
                            @delete="deleteMomentDraft"
                        />
                    </div>

                    <ApplicationRemindersSection
                        v-if="isEdit"
                        :application="application"
                        :reminders="applicationReminders"
                        :reminder-types="reminderTypes"
                        :reminder-frequencies="reminderFrequencies"
                        :moments="editableMoments"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
