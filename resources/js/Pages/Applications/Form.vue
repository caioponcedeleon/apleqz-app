<script setup>
import ApplicationMomentModal from '@/Components/ApplicationMomentModal.vue';
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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, default: null },
    areas: { type: Array, default: () => [] },
    waves: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    momentTypes: { type: Array, default: () => [] },
    canUploadApplicationFiles: { type: Boolean, default: false },
    canCreateApplication: { type: Boolean, default: true },
});

const { t } = useI18n();
const isEdit = computed(() => props.application !== null);
const hasAreas = computed(() => props.areas.length > 0);
const hasWaves = computed(() => props.waves.length > 0);
const showNoAreasState = computed(() => !isEdit.value && !hasAreas.value);
const showNoWavesState = computed(() => !isEdit.value && hasAreas.value && !hasWaves.value);
const canUseForm = computed(() => isEdit.value || (hasAreas.value && hasWaves.value));

const mapMoments = (moments) =>
    (moments ?? [])
        .filter((moment) => !moment.is_system && moment.type !== 'status_change')
        .map((moment) => ({
            id: moment.id ?? null,
            type: moment.type,
            occurred_at: moment.occurred_at?.slice?.(0, 10) ?? '',
            notes: moment.notes ?? '',
        }));

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

    form.moments.forEach((moment, index) => {
        if (!moment.type || !moment.occurred_at) {
            return;
        }

        items.push({
            key: moment.id ?? `moment-${index}`,
            kind: 'moment',
            index,
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

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};

const momentModalOpen = ref(false);
const editingMomentIndex = ref(null);
const momentDraft = ref({ type: 'feedback', occurred_at: '', notes: '' });

const isNewMoment = computed(() => editingMomentIndex.value === null);

const momentModalErrors = computed(() => {
    if (editingMomentIndex.value === null) {
        return {};
    }

    const index = editingMomentIndex.value;

    return {
        type: form.errors[`moments.${index}.type`],
        occurred_at: form.errors[`moments.${index}.occurred_at`],
        notes: form.errors[`moments.${index}.notes`],
    };
});

const openMomentModal = (index = null) => {
    editingMomentIndex.value = index;

    if (index !== null) {
        momentDraft.value = { ...form.moments[index] };
    } else {
        momentDraft.value = {
            id: null,
            type: props.momentTypes[0] ?? 'feedback',
            occurred_at: '',
            notes: '',
        };
    }

    momentModalOpen.value = true;
};

const closeMomentModal = () => {
    momentModalOpen.value = false;
    editingMomentIndex.value = null;
};

const saveMomentDraft = (draft) => {
    const payload = {
        id: editingMomentIndex.value !== null ? form.moments[editingMomentIndex.value]?.id ?? null : null,
        type: draft.type,
        occurred_at: draft.occurred_at,
        notes: draft.notes ?? '',
    };

    if (editingMomentIndex.value !== null) {
        form.moments[editingMomentIndex.value] = payload;
    } else {
        form.moments.push(payload);
    }

    closeMomentModal();
};

const deleteMomentDraft = () => {
    if (editingMomentIndex.value !== null) {
        form.moments.splice(editingMomentIndex.value, 1);
    }

    closeMomentModal();
};

const form = useForm({
    area_id: props.application?.area_id ?? props.areas[0]?.id ?? '',
    application_wave_id: props.application?.application_wave_id ?? props.waves[0]?.id ?? '',
    position: props.application?.position ?? '',
    company: props.application?.company ?? '',
    location: props.application?.location ?? '',
    applied_at: props.application?.applied_at?.slice?.(0, 10) ?? '',
    status: props.application?.status ?? 'esperando',
    channel: props.application?.channel ?? '',
    notes: props.application?.notes ?? '',
    job_url: props.application?.job_url ?? '',
    moments: mapMoments(props.application?.moments),
    create_another: false,
});

const requiresAppliedDate = computed(() => form.status !== 'a_candidatar');

const appliedAtLabel = computed(() =>
    requiresAppliedDate.value
        ? t('app.applications.applied_at')
        : t('app.applications.planned_apply_at'),
);

const submit = (createAnother = false) => {
    if (!canUseForm.value) {
        return;
    }

    form.create_another = createAnother;

    form
        .transform((data) => ({
            ...data,
            moments: data.moments.filter((moment) => moment.type && moment.occurred_at),
        }));

    const options = { preserveScroll: true };

    if (isEdit.value) {
        form.put(route('applications.update', props.application.id), options);
    } else {
        form.post(route('applications.store'), options);
    }
};

const applicationFiles = computed(() => props.application?.files ?? []);

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
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="showNoAreasState"
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

                <form
                    v-else
                    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    @submit.prevent="submit(false)"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel :value="t('app.applications.wave')" />
                            <select
                                v-model="form.application_wave_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                required
                            >
                                <option v-for="w in waves" :key="w.id" :value="w.id">
                                    {{ w.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.application_wave_id" />
                        </div>

                        <div>
                            <InputLabel :value="t('app.applications.area')" />
                            <select
                                v-model="form.area_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                required
                            >
                                <option v-for="a in areas" :key="a.id" :value="a.id">
                                    {{ a.name }}
                                </option>
                            </select>
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

                    <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
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
                                    class="absolute left-3 top-4 z-10 size-2.5 -translate-x-1/2 rounded-full ring-4 ring-white dark:ring-gray-800"
                                    :class="item.kind === 'status'
                                        ? 'bg-gray-300 dark:bg-gray-500'
                                        : 'bg-indigo-400 dark:bg-indigo-500'"
                                />

                                <div v-if="item.kind === 'status'" class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <StatusBadge
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
                                    class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20"
                                    @click="openMomentModal(item.index)"
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
                            @click="submit(true)"
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
