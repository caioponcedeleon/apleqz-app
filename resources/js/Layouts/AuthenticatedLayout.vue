<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { openCookieSettings } from '@/composables/useCookieConsent';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const showingNavigationDropdown = ref(false);
const page = usePage();
const { t } = useI18n();

const isAdmin = computed(() => page.props.auth.user?.is_admin);
const canUsePersonalFiles = computed(() => page.props.auth.user?.personal_files_enabled);
</script>

<template>
    <div>
        <div class="min-h-screen overflow-x-hidden bg-gray-100 dark:bg-gray-950">
            <nav class="border-b border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    {{ t('app.nav.dashboard') }}
                                </NavLink>
                                <NavLink
                                    :href="route('applications.index')"
                                    :active="route().current('applications.*')"
                                >
                                    {{ t('app.nav.applications') }}
                                </NavLink>
                                <NavLink
                                    v-if="canUsePersonalFiles"
                                    :href="route('files.index')"
                                    :active="route().current('files.*')"
                                >
                                    {{ t('app.nav.files') }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden items-center gap-4 sm:flex">
                            <LocaleSwitcher />
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            {{ $page.props.auth.user.name }}
                                            <svg
                                                class="-me-0.5 ms-2 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('areas.index')">
                                        {{ t('app.nav.areas') }}
                                    </DropdownLink>
                                    <DropdownLink :href="route('waves.index')">
                                        {{ t('app.nav.waves') }}
                                    </DropdownLink>
                                    <DropdownLink :href="route('profile.edit')">
                                        {{ t('app.nav.profile') }}
                                    </DropdownLink>
                                    <DropdownLink
                                        v-if="isAdmin"
                                        href="/admin"
                                        external
                                    >
                                        {{ t('app.nav.admin') }}
                                    </DropdownLink>
                                    <button
                                        type="button"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="openCookieSettings"
                                    >
                                        {{ t('app.cookies.settings_link') }}
                                    </button>
                                    <DropdownLink
                                        :href="route('cookies')"
                                        external
                                    >
                                        {{ t('app.cookies.policy_link') }}
                                    </DropdownLink>
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                    >
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            {{ t('app.nav.dashboard') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('applications.index')"
                            :active="route().current('applications.*')"
                        >
                            {{ t('app.nav.applications') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('areas.index')"
                            :active="route().current('areas.*')"
                        >
                            {{ t('app.nav.areas') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('waves.index')"
                            :active="route().current('waves.*')"
                        >
                            {{ t('app.nav.waves') }}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="canUsePersonalFiles"
                            :href="route('files.index')"
                            :active="route().current('files.*')"
                        >
                            {{ t('app.nav.files') }}
                        </ResponsiveNavLink>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                        <LocaleSwitcher />
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="bg-white shadow dark:bg-gray-900">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
