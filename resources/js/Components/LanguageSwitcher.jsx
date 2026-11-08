import { useLocale } from '@/i18n/LocaleProvider';
import { useEffect, useRef, useState } from 'react';

function IconChevron({ className = 'h-3.5 w-3.5' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fillRule="evenodd"
                d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                clipRule="evenodd"
            />
        </svg>
    );
}

export default function LanguageSwitcher({ className = '', compact = false }) {
    const { locale, setLocale, locales, t } = useLocale();
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        function onPointerDown(e) {
            if (rootRef.current && !rootRef.current.contains(e.target)) {
                setOpen(false);
            }
        }
        function onKey(e) {
            if (e.key === 'Escape') setOpen(false);
        }
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    function choose(code) {
        setLocale(code);
        setOpen(false);
    }

    const current = locales.find((l) => l.code === locale);

    return (
        <div ref={rootRef} className={`relative ${className}`}>
            <button
                type="button"
                className={
                    compact
                        ? 'inline-flex h-9 items-center gap-0.5 rounded-full px-1.5 text-xs font-semibold text-navy whitespace-nowrap hover:bg-surface sm:h-auto sm:rounded-lg sm:px-2 sm:py-1.5'
                        : 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-navy'
                }
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-label={t('shell.chooseLanguage')}
                onClick={() => setOpen((v) => !v)}
            >
                <span aria-hidden="true">🌐</span>
                {current?.label ?? 'EN'}
                <IconChevron />
            </button>
            {open ? (
                <ul
                    role="listbox"
                    className="absolute end-0 z-50 mt-2 min-w-[9rem] rounded-xl border border-slate-100 bg-white p-1 shadow-card"
                >
                    {locales.map((l) => (
                        <li key={l.code}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={locale === l.code}
                                className="flex w-full rounded-lg px-3 py-2 text-start text-sm text-brand-ink hover:bg-surface"
                                onClick={() => choose(l.code)}
                            >
                                {l.label} · {l.name}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
