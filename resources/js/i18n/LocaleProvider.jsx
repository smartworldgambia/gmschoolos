import {
    LOCALES,
    LOCALE_COOKIE_KEY,
    LOCALE_STORAGE_KEY,
    translate,
} from '@/i18n/marketingMessages';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const LocaleContext = createContext({
    locale: 'en',
    setLocale: () => {},
    t: (key, vars) => translate('en', key, vars),
    locales: LOCALES,
    dir: 'ltr',
});

function isValidLocale(code) {
    return LOCALES.some((l) => l.code === code);
}

function applyDocumentLocale(code) {
    if (typeof document === 'undefined') return;
    document.documentElement.lang = code;
    document.documentElement.dir = code === 'ar' ? 'rtl' : 'ltr';
}

function readCookie(name) {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

function writeCookie(name, value) {
    if (typeof document === 'undefined') return;
    const maxAge = 60 * 60 * 24 * 365;
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
}

/** Resolve order: cookie → localStorage → en */
function readStoredLocale() {
    if (typeof window === 'undefined') return 'en';
    const fromCookie = readCookie(LOCALE_COOKIE_KEY);
    if (isValidLocale(fromCookie)) return fromCookie;
    const fromStorage = window.localStorage.getItem(LOCALE_STORAGE_KEY);
    if (isValidLocale(fromStorage)) return fromStorage;
    return 'en';
}

function persistLocale(code) {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, code);
    writeCookie(LOCALE_COOKIE_KEY, code);
}

export function LocaleProvider({ children }) {
    const [locale, setLocaleState] = useState(() => {
        if (typeof window === 'undefined') return 'en';
        return readStoredLocale();
    });

    useEffect(() => {
        const initial = readStoredLocale();
        setLocaleState(initial);
        applyDocumentLocale(initial);
        // Keep storage and cookie aligned after boot.
        persistLocale(initial);
    }, []);

    const setLocale = useCallback((code) => {
        if (!isValidLocale(code)) return;
        setLocaleState(code);
        persistLocale(code);
        applyDocumentLocale(code);
    }, []);

    const t = useCallback((key, vars) => translate(locale, key, vars), [locale]);

    const value = useMemo(
        () => ({
            locale,
            setLocale,
            t,
            locales: LOCALES,
            dir: locale === 'ar' ? 'rtl' : 'ltr',
        }),
        [locale, setLocale, t],
    );

    return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocale() {
    return useContext(LocaleContext);
}

export { LocaleContext };
