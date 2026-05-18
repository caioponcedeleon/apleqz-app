<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const githubUrl = 'https://github.com/caioponcedeleon';
</script>

<template>
    <div class="text-center text-sm text-slate-500 dark:text-slate-400">
        <p>{{ t('app.footer.version') }}</p>
        <p class="mt-1">
            <i18n-t keypath="app.footer.created_by" tag="span">
                <template #author>
                    <a
                        :href="githubUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-indigo-600 underline-offset-2 transition hover:underline dark:text-indigo-400"
                    >
                        {{ t('app.footer.author_name') }}
                    </a>
                </template>
            </i18n-t>
        </p>
    </div>
</template>
