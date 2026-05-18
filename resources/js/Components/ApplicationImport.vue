<script setup>
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const fileInput = ref(null);

const form = useForm({
    file: null,
});

const openFilePicker = () => {
    if (!form.processing) {
        fileInput.value?.click();
    }
};

const onFileSelected = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    if (!confirm(t('app.applications.import_confirm'))) {
        event.target.value = '';

        return;
    }

    form.file = file;
    form.post(route('applications.import'), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            class="hidden"
            @change="onFileSelected"
        />
        <SecondaryButton
            type="button"
            :disabled="form.processing"
            @click="openFilePicker"
        >
            {{ form.processing ? t('app.applications.importing') : t('app.applications.import') }}
        </SecondaryButton>
        <InputError class="mt-1" :message="form.errors.file" />
    </div>
</template>
