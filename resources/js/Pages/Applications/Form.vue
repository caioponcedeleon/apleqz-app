<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    application: { type: Object, default: null },
    areas: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();
const isEdit = computed(() => props.application !== null);

const form = useForm({
    area_id: props.application?.area_id ?? '',
    position: props.application?.position ?? '',
    company: props.application?.company ?? '',
    location: props.application?.location ?? '',
    applied_at: props.application?.applied_at?.slice?.(0, 10) ?? '',
    status: props.application?.status ?? 'esperando',
    rejected_at: props.application?.rejected_at?.slice?.(0, 10) ?? '',
    interview_date: props.application?.interview_date?.slice?.(0, 10) ?? '',
    channel: props.application?.channel ?? '',
    notes: props.application?.notes ?? '',
    job_url: props.application?.job_url ?? '',
});

const requiresAppliedDate = computed(() => form.status !== 'a_candidatar');

const appliedAtLabel = computed(() =>
    requiresAppliedDate.value
        ? t('app.applications.applied_at')
        : t('app.applications.planned_apply_at'),
);

const submit = () => {
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
                    class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
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
                            <InputLabel :value="t('app.applications.rejected_at')" />
                            <TextInput v-model="form.rejected_at" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.rejected_at" />
                        </div>

                        <div>
                            <InputLabel :value="t('app.applications.interview_date')" />
                            <TextInput v-model="form.interview_date" type="date" class="mt-1 block w-full" />
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
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
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
