<script setup>
import { useI18n } from 'vue-i18n';
import { openCookieSettings } from '@/composables/useCookieConsent';

defineOptions({ inheritAttrs: false });

const { t } = useI18n();
</script>

<template>
    <button
        type="button"
        class="text-sm text-slate-500 underline-offset-2 transition hover:text-indigo-600 hover:underline dark:text-slate-400 dark:hover:text-indigo-400"
        v-bind="$attrs"
        @click="openCookieSettings"
    >
        {{ t('app.cookies.settings_link') }}
    </button>
</template>
