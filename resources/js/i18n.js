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
