<script setup>
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const switchLocale = (locale) => {
    router.post(
        route('locale.update'),
        { locale },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div
        class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white p-0.5 text-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <button
            v-for="loc in page.props.locales"
            :key="loc"
            type="button"
            class="rounded-md px-2.5 py-1 font-medium uppercase transition"
            :class="
                page.props.locale === loc
                    ? 'bg-indigo-600 text-white'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
            "
            @click="switchLocale(loc)"
        >
            {{ loc }}
        </button>
    </div>
</template>
