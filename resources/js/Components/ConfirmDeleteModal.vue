<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    itemName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const { t } = useI18n();
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div class="px-6 py-6">
            <div class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/50"
                >
                    <svg
                        class="size-7 text-red-600 dark:text-red-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                        />
                    </svg>
                </div>

                <div class="mt-4 sm:mt-0 sm:ms-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ title }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ message }}
                    </p>
                    <p
                        v-if="itemName"
                        class="mt-3 truncate rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900 dark:bg-gray-900/60 dark:text-white"
                    >
                        {{ itemName }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" class="justify-center" @click="emit('close')">
                    {{ t('app.actions.cancel') }}
                </SecondaryButton>
                <DangerButton type="button" class="justify-center" @click="emit('confirm')">
                    {{ t('app.actions.delete') }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
