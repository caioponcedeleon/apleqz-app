<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('waves.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name');
            emit('close');
        },
    });
};

const close = () => {
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div class="px-6 py-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ t('app.waves.add') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('app.waves.modal_hint') }}
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel :value="t('app.waves.name')" />
                    <TextInput
                        v-model="form.name"
                        class="mt-1 block w-full"
                        :placeholder="t('app.waves.name')"
                        required
                        autofocus
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="close">
                        {{ t('app.actions.cancel') }}
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing" type="submit">
                        {{ t('app.waves.add') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
