const STORAGE_KEY = 'apleqz_cookie_consent';
export const CONSENT_VERSION = 1;

const defaultPreferences = () => ({
    version: CONSENT_VERSION,
    essential: true,
    analytics: false,
    decidedAt: null,
});

export function readCookieConsent() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (parsed?.version !== CONSENT_VERSION) {
            return null;
        }

        return {
            ...defaultPreferences(),
            ...parsed,
            essential: true,
        };
    } catch {
        return null;
    }
}

export function writeCookieConsent(preferences) {
    const payload = {
        ...defaultPreferences(),
        ...preferences,
        version: CONSENT_VERSION,
        essential: true,
        decidedAt: new Date().toISOString(),
    };

    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));

    window.dispatchEvent(
        new CustomEvent('cookie-consent-updated', { detail: payload }),
    );

    return payload;
}

export function acceptAllCookies() {
    return writeCookieConsent({ essential: true, analytics: true });
}

export function rejectNonEssentialCookies() {
    return writeCookieConsent({ essential: true, analytics: false });
}

export function saveCookiePreferences({ analytics }) {
    return writeCookieConsent({ essential: true, analytics: Boolean(analytics) });
}

export function clearCookieConsent() {
    localStorage.removeItem(STORAGE_KEY);
    window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: null }));
}

export function openCookieSettings() {
    window.dispatchEvent(new CustomEvent('cookie-consent-open'));
}
