import { createI18n } from 'vue-i18n';

export function setupI18n(locale, messages) {
    return createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages: {
            [locale]: messages,
        },
    });
}

export function syncI18nFromPage(i18n, { locale, translations }) {
    if (!locale || !translations) {
        return;
    }

    i18n.global.setLocaleMessage(locale, translations);
    i18n.global.locale.value = locale;
}
