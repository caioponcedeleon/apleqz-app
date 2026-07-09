<script setup>
import ChipSelect from '@/Components/ChipSelect.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const localeOptions = computed(() =>
    (page.props.locales ?? []).map((locale) => ({
        value: locale,
        label: page.props.localeLabels?.[locale] ?? locale.toUpperCase(),
    })),
);

const switchLocale = (locale) => {
    if (!locale || locale === page.props.locale) {
        return;
    }

    router.post(
        route('locale.update'),
        { locale },
        { preserveScroll: true },
    );
};
</script>

<template>
    <ChipSelect
        id="locale-switcher"
        compact
        class="max-w-[9rem] sm:max-w-[10rem]"
        :model-value="page.props.locale"
        :options="localeOptions"
        :aria-label="t('app.nav.language')"
        @change="switchLocale"
    />
</template>
