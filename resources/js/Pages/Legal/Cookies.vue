<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import CookieSettingsLink from '@/Components/CookieSettingsLink.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const cookieRows = [
    { name: 'laravel_session', type: 'essential', duration: 'session' },
    { name: 'XSRF-TOKEN', type: 'essential', duration: 'session' },
];
</script>

<template>
    <Head :title="t('app.cookies.page_title')" />

    <div class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <Link href="/" class="shrink-0">
                    <ApplicationLogo class="h-9 w-auto" />
                </Link>
                <LocaleSwitcher />
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                {{ t('app.cookies.page_title') }}
            </h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ t('app.cookies.last_updated') }}
            </p>

            <div class="prose prose-slate mt-8 max-w-none dark:prose-invert">
                <p class="text-slate-600 dark:text-slate-300">
                    {{ t('app.cookies.page_intro') }}
                </p>

                <h2 class="mt-8 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ t('app.cookies.what_we_use_title') }}
                </h2>
                <p class="text-slate-600 dark:text-slate-300">
                    {{ t('app.cookies.what_we_use_text') }}
                </p>

                <h2 class="mt-8 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ t('app.cookies.table_title') }}
                </h2>
                <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-slate-600">
                                    {{ t('app.cookies.col_name') }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-slate-600">
                                    {{ t('app.cookies.col_type') }}
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-slate-600">
                                    {{ t('app.cookies.col_duration') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900">
                            <tr v-for="row in cookieRows" :key="row.name">
                                <td class="px-4 py-3 font-mono text-xs">{{ row.name }}</td>
                                <td class="px-4 py-3">{{ t(`app.cookies.type_${row.type}`) }}</td>
                                <td class="px-4 py-3">{{ t(`app.cookies.duration_${row.duration}`) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h2 class="mt-8 text-lg font-semibold text-slate-900 dark:text-white">
                    {{ t('app.cookies.your_rights_title') }}
                </h2>
                <p class="text-slate-600 dark:text-slate-300">
                    {{ t('app.cookies.your_rights_text') }}
                </p>
                <p class="mt-4">
                    <CookieSettingsLink />
                </p>
            </div>

            <p class="mt-10">
                <Link
                    href="/"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    ← {{ t('app.cookies.back_home') }}
                </Link>
            </p>
        </main>
    </div>
</template>
