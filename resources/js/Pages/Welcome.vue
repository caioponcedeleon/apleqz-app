<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('app.name')" />

    <div class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
        <!-- Header -->
        <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <Link href="/" class="shrink-0">
                    <ApplicationLogo class="h-10 w-auto sm:h-11" />
                </Link>

                <div class="flex items-center gap-3 sm:gap-4">
                    <LocaleSwitcher />

                    <nav v-if="canLogin" class="flex items-center gap-2 sm:gap-3">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="hidden text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 sm:inline"
                        >
                            {{ t('app.home.go_to_dashboard') }}
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                            >
                                {{ t('app.home.log_in') }}
                            </Link>

                            <Link v-if="canRegister" :href="route('register')">
                                <PrimaryButton class="!bg-indigo-600 !text-xs hover:!bg-indigo-700 focus:!ring-indigo-500">
                                    {{ t('app.home.get_started') }}
                                </PrimaryButton>
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-amber-50 dark:from-indigo-950/40 dark:via-slate-950 dark:to-amber-950/20"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-24 top-0 h-96 w-96 rounded-full bg-indigo-200/40 blur-3xl dark:bg-indigo-600/20"
                aria-hidden="true"
            />

            <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24 lg:py-28">
                <div class="mx-auto max-w-3xl text-center">
                    <ApplicationLogo class="mx-auto h-20 w-auto sm:h-24" />

                    <h1 class="mt-8 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl dark:text-white">
                        {{ t('app.home.title') }}
                    </h1>

                    <p class="mt-6 text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                        {{ t('app.home.subtitle') }}
                    </p>

                    <div v-if="canLogin" class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                        >
                            <PrimaryButton class="!bg-indigo-600 px-8 py-3 !text-sm hover:!bg-indigo-700">
                                {{ t('app.home.go_to_dashboard') }}
                            </PrimaryButton>
                        </Link>

                        <template v-else>
                            <Link v-if="canRegister" :href="route('register')">
                                <PrimaryButton class="!bg-indigo-600 px-8 py-3 !text-sm hover:!bg-indigo-700">
                                    {{ t('app.home.get_started') }}
                                </PrimaryButton>
                            </Link>
                            <Link
                                :href="route('login')"
                                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-8 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                {{ t('app.home.log_in') }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-200 py-8 dark:border-slate-800">
            <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                © {{ new Date().getFullYear() }} {{ t('app.name') }}
            </p>
        </footer>
    </div>
</template>
