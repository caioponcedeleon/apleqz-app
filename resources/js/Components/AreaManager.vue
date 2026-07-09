<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    areas: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const createForm = useForm({ name: '' });

const addArea = () => {
    createForm.post(route('areas.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
};

const deleteArea = (area) => {
    if (!confirm(t('app.areas.delete') + '?')) return;
    router.delete(route('areas.destroy', area.id), { preserveScroll: true });
};
</script>

<template>
    <div
        data-onboarding="area-manager"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ t('app.areas.title') }}
        </h3>

        <form class="mt-4 flex gap-2" @submit.prevent="addArea">
            <TextInput
                v-model="createForm.name"
                class="block w-full"
                :placeholder="t('app.areas.name')"
            />
            <PrimaryButton :disabled="createForm.processing" type="submit">
                {{ t('app.areas.add') }}
            </PrimaryButton>
        </form>
        <InputError class="mt-2" :message="createForm.errors.name" />

        <p
            v-if="!areas.length"
            class="mt-4 text-sm text-gray-500 dark:text-gray-400"
        >
            {{ t('app.areas.empty') }}
        </p>

        <ul v-if="areas.length" class="mt-4 space-y-2">
            <li
                v-for="area in areas"
                :key="area.id"
                class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900/50"
            >
                <span class="text-gray-800 dark:text-gray-200">{{ area.name }}</span>
                <button
                    type="button"
                    class="text-red-600 hover:underline"
                    @click="deleteArea(area)"
                >
                    {{ t('app.areas.delete') }}
                </button>
            </li>
        </ul>

        <p
            v-if="page.props.flash?.error"
            class="mt-2 text-sm text-red-600"
        >
            {{ page.props.flash.error }}
        </p>
    </div>
</template>
