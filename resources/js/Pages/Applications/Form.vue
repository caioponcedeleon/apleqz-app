<script setup>
import FileManager from '@/Components/FileManager.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, default: null },
    areas: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    momentTypes: { type: Array, default: () => [] },
    canUploadApplicationFiles: { type: Boolean, default: false },
});

const { t } = useI18n();
const isEdit = computed(() => props.application !== null);

const mapMoments = (moments) =>
    (moments ?? []).map((moment) => ({
        id: moment.id ?? null,
        type: moment.type,
        occurred_at: moment.occurred_at?.slice?.(0, 10) ?? '',
        notes: moment.notes ?? '',
    }));

const form = useForm({
    area_id: props.application?.area_id ?? '',
    position: props.application?.position ?? '',
    company: props.application?.company ?? '',
    location: props.application?.location ?? '',
    applied_at: props.application?.applied_at?.slice?.(0, 10) ?? '',
    status: props.application?.status ?? 'esperando',
    channel: props.application?.channel ?? '',
    notes: props.application?.notes ?? '',
    job_url: props.application?.job_url ?? '',
    moments: mapMoments(props.application?.moments),
});

const requiresAppliedDate = computed(() => form.status !== 'a_candidatar');

const appliedAtLabel = computed(() =>
    requiresAppliedDate.value
        ? t('app.applications.applied_at')
        : t('app.applications.planned_apply_at'),
);

const addMoment = () => {
    form.moments.push({
        id: null,
        type: props.momentTypes[0] ?? 'feedback',
        occurred_at: '',
        notes: '',
    });
};

const removeMoment = (index) => {
    form.moments.splice(index, 1);
};

const applicationFiles = computed(() => props.application?.files ?? []);

const downloadApplicationFileUrl = (file) =>
    route('applications.files.download', [props.application.id, file.id]);

const previewApplicationFileUrl = (file) =>
    route('applications.files.preview', [props.application.id, file.id]);

const deleteApplicationFileUrl = (file) =>
    route('applications.files.destroy', [props.application.id, file.id]);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            moments: data.moments.filter((moment) => moment.type && moment.occurred_at),
        }));

    if (isEdit.value) {
        form.put(route('applications.update', props.application.id));
    } else {
        form.post(route('applications.store'));
    }
};
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
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel :value="t('app.applications.area')" />
                            <select
                                v-model="form.area_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                required
                            >
                                <option value="" disabled>{{ t('app.applications.area') }}</option>
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

                        <div>
                            <InputLabel :value="t('app.applications.status')" />
                            <select
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option v-for="s in statuses" :key="s" :value="s">
                                    {{ t(`app.status.${s}`) }}
                                </option>
                            </select>
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
                            <SecondaryButton type="button" @click="addMoment">
                                {{ t('app.applications.add_moment') }}
                            </SecondaryButton>
                        </div>

                        <p
                            v-if="!form.moments.length"
                            class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
                        >
                            {{ t('app.applications.moments_empty') }}
                        </p>

                        <div v-else class="space-y-4">
                            <div
                                v-for="(moment, index) in form.moments"
                                :key="moment.id ?? `new-${index}`"
                                class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900/40"
                            >
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ t('app.applications.moment_number', { n: index + 1 }) }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-sm text-red-600 hover:underline dark:text-red-400"
                                        @click="removeMoment(index)"
                                    >
                                        {{ t('app.applications.remove_moment') }}
                                    </button>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :value="t('app.applications.moment_type')" />
                                        <select
                                            v-model="moment.type"
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            required
                                        >
                                            <option
                                                v-for="type in momentTypes"
                                                :key="type"
                                                :value="type"
                                            >
                                                {{ t(`app.moment_types.${type}`) }}
                                            </option>
                                        </select>
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors[`moments.${index}.type`]"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel :value="t('app.applications.moment_date')" />
                                        <TextInput
                                            v-model="moment.occurred_at"
                                            type="date"
                                            class="mt-1 block w-full"
                                            required
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors[`moments.${index}.occurred_at`]"
                                        />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <InputLabel :value="t('app.applications.moment_notes')" />
                                        <textarea
                                            v-model="moment.notes"
                                            rows="2"
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            :placeholder="t('app.applications.moment_notes_placeholder')"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors[`moments.${index}.notes`]"
                                        />
                                    </div>
                                </div>
                            </div>
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
                            />
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600"
                            >
                                {{ t('app.applications.files_save_first') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <PrimaryButton :disabled="form.processing" type="submit">
                            {{ t('app.actions.save') }}
                        </PrimaryButton>
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
