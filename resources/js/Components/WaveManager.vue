<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    waves: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const createForm = useForm({ name: '' });

const addWave = () => {
    createForm.post(route('waves.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
};

const deleteWave = (wave) => {
    if (!confirm(t('app.waves.delete') + '?')) return;
    router.delete(route('waves.destroy', wave.id), { preserveScroll: true });
};
</script>

<template>
    <div
        data-onboarding="wave-manager"
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ t('app.waves.title') }}
        </h3>

        <form class="mt-4 flex gap-2" @submit.prevent="addWave">
            <TextInput
                v-model="createForm.name"
                class="block w-full"
                :placeholder="t('app.waves.name')"
            />
            <PrimaryButton :disabled="createForm.processing" type="submit">
                {{ t('app.waves.add') }}
            </PrimaryButton>
        </form>
        <InputError class="mt-2" :message="createForm.errors.name" />

        <p
            v-if="!waves.length"
            class="mt-4 text-sm text-gray-500 dark:text-gray-400"
        >
            {{ t('app.waves.empty') }}
        </p>

        <ul v-if="waves.length" class="mt-4 space-y-2">
            <li
                v-for="wave in waves"
                :key="wave.id"
                class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900/50"
            >
                <div class="min-w-0">
                    <span class="text-gray-800 dark:text-gray-200">{{ wave.name }}</span>
                    <span
                        v-if="wave.is_default"
                        class="ms-2 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
                    >
                        {{ t('app.waves.default_badge') }}
                    </span>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-red-600 hover:underline"
                    @click="deleteWave(wave)"
                >
                    {{ t('app.waves.delete') }}
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
