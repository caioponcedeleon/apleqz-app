<script setup>
import AppCredits from '@/Components/AppCredits.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import CookieSettingsLink from '@/Components/CookieSettingsLink.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-center">
            <Link
                :href="route('cookies')"
                class="text-xs text-gray-500 underline-offset-2 hover:text-indigo-600 hover:underline"
            >
                {{ t('app.cookies.policy_link') }}
            </Link>
            <CookieSettingsLink class="!text-xs" />
        </div>

        <AppCredits class="mt-8 px-4 pb-8" />
    </div>
</template>
