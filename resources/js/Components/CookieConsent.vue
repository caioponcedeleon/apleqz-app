<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    acceptAllCookies,
    readCookieConsent,
    rejectNonEssentialCookies,
    saveCookiePreferences,
} from '@/composables/useCookieConsent';

const { t, te } = useI18n({ useScope: 'global' });

const showModal = ref(false);
const settingsMode = ref(false);
const showPreferences = ref(false);
const analyticsEnabled = ref(false);
const contentReady = ref(false);

const isMandatory = computed(() => showModal.value && !settingsMode.value);

const syncFromStorage = () => {
    const consent = readCookieConsent();

    if (consent === null) {
        settingsMode.value = false;
        analyticsEnabled.value = false;

        if (contentReady.value && te('app.cookies.banner_title')) {
            showModal.value = true;
        }

        return;
    }

    analyticsEnabled.value = consent.analytics ?? false;

    if (!settingsMode.value) {
        showModal.value = false;
    }
};

const setPageLocked = (locked) => {
    document.body.style.overflow = locked ? 'hidden' : '';

    const app = document.getElementById('app');

    if (!app) {
        return;
    }

    if (locked) {
        app.setAttribute('inert', '');
        app.setAttribute('aria-hidden', 'true');
    } else {
        app.removeAttribute('inert');
        app.removeAttribute('aria-hidden');
    }
};

watch(isMandatory, (locked) => setPageLocked(locked), { immediate: true });

onMounted(async () => {
    await nextTick();
    contentReady.value = te('app.cookies.banner_title');
    syncFromStorage();
    window.addEventListener('cookie-consent-open', openSettings);
    window.addEventListener('cookie-consent-updated', onConsentUpdated);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('cookie-consent-open', openSettings);
    window.removeEventListener('cookie-consent-updated', onConsentUpdated);
    document.removeEventListener('keydown', onKeydown);
    setPageLocked(false);
});

const onConsentUpdated = () => {
    settingsMode.value = false;
    showPreferences.value = false;
    syncFromStorage();
};

const openSettings = () => {
    const consent = readCookieConsent();
    analyticsEnabled.value = consent?.analytics ?? false;
    showPreferences.value = false;
    settingsMode.value = true;
    showModal.value = true;
};

const closeModal = () => {
    if (isMandatory.value) {
        return;
    }

    showModal.value = false;
    settingsMode.value = false;
    showPreferences.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && showModal.value && !isMandatory.value) {
        closeModal();
    }
};

const acceptAll = () => {
    acceptAllCookies();
};

const rejectNonEssential = () => {
    rejectNonEssentialCookies();
};

const savePreferences = () => {
    saveCookiePreferences({ analytics: analyticsEnabled.value });
};

const togglePreferences = () => {
    showPreferences.value = !showPreferences.value;
};

const panelTitle = computed(() =>
    showPreferences.value ? t('app.cookies.preferences_title') : t('app.cookies.banner_title'),
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
            :class="isMandatory ? 'bg-slate-900/75' : 'bg-slate-900/50'"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cookie-consent-title"
        >
            <div
                v-if="!isMandatory"
                class="absolute inset-0"
                aria-hidden="true"
                @click="closeModal"
            />

            <div
                class="relative isolate z-10 w-full max-w-3xl rounded-xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:p-6"
            >
                <h2
                    id="cookie-consent-title"
                    class="text-base font-semibold text-slate-900 dark:text-white"
                >
                    {{ panelTitle }}
                </h2>

                <p
                    v-if="isMandatory"
                    class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300"
                >
                    {{ t('app.cookies.required_notice') }}
                </p>

                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    {{ t('app.cookies.banner_text') }}
                    <Link
                        :href="route('cookies')"
                        class="font-medium text-indigo-600 underline underline-offset-2 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        {{ t('app.cookies.policy_link') }}
                    </Link>
                </p>

                <div
                    v-if="showPreferences"
                    class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                {{ t('app.cookies.essential_title') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                                {{ t('app.cookies.essential_desc') }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                        >
                            {{ t('app.cookies.always_on') }}
                        </span>
                    </div>

                    <div class="flex items-start justify-between gap-4 border-t border-slate-200 pt-3 dark:border-slate-700">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                {{ t('app.cookies.analytics_title') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                                {{ t('app.cookies.analytics_desc') }}
                            </p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input
                                v-model="analyticsEnabled"
                                type="checkbox"
                                class="peer sr-only"
                            />
                            <span
                                class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500 peer-focus:ring-offset-2 dark:bg-slate-600 dark:peer-focus:ring-offset-slate-900"
                            />
                            <span
                                class="absolute start-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-5"
                            />
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <template v-if="showPreferences">
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                            @click="savePreferences"
                        >
                            {{ t('app.cookies.save_preferences') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
                            @click="togglePreferences"
                        >
                            {{ t('app.cookies.back') }}
                        </button>
                    </template>
                    <template v-else>
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                            @click="acceptAll"
                        >
                            {{ t('app.cookies.accept_all') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
                            @click="rejectNonEssential"
                        >
                            {{ t('app.cookies.reject_non_essential') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 underline-offset-2 transition hover:text-indigo-600 hover:underline dark:text-slate-300 dark:hover:text-indigo-400"
                            @click="togglePreferences"
                        >
                            {{ t('app.cookies.customize') }}
                        </button>
                    </template>

                    <button
                        v-if="!isMandatory"
                        type="button"
                        class="inline-flex justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                        @click="closeModal"
                    >
                        {{ t('app.cookies.close') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
