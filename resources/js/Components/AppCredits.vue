<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const githubUrl = 'https://github.com/caioponcedeleon';

const versionLabel = computed(() => t('app.footer.version', {
    version: page.props.appVersion ?? '3.0',
}));
</script>

<template>
    <div class="text-center text-sm text-slate-500 dark:text-slate-400">
        <p>{{ versionLabel }}</p>
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
