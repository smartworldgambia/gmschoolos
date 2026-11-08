import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

export function initials(name) {
    const skip = /^(mr|mrs|ms|miss|dr|prof)\.?$/i;
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter((p) => p && !skip.test(p) && /[\p{L}]/u.test(p));
    const letters = `${parts[0]?.[0] ?? ''}${parts[1]?.[0] ?? ''}`.toUpperCase();

    return letters || '?';
}

export function avatarTone(name) {
    const tones = ['bg-teal/15 text-teal', 'bg-brand-blue/15 text-brand-blue', 'bg-amber-100 text-amber-800', 'bg-violet-100 text-violet-700', 'bg-rose-100 text-rose-700'];
    const s = String(name || '');
    let h = 0;
    for (let i = 0; i < s.length; i += 1) h = (h + s.charCodeAt(i)) % tones.length;

    return tones[h];
}

export function ChatIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 10h8M8 14h5M21 12a8 8 0 11-16 0 8 8 0 0116 0zm-4 5l3 3v-5" />
        </svg>
    );
}

export function ComposeIcon() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z" />
        </svg>
    );
}

export function SearchIcon() {
    return (
        <svg className="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z" />
        </svg>
    );
}

export function ClipIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M21.44 11.05l-8.49 8.49a6 6 0 01-8.49-8.49l8.49-8.49a4 4 0 015.66 5.66l-8.49 8.49a2 2 0 01-2.83-2.83l8.49-8.49"
            />
        </svg>
    );
}

export function PlaneIcon() {
    return (
        <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3.4 20.6L21 12 3.4 3.4 3 10l11 2-11 2 .4 6.6z" />
        </svg>
    );
}

export function BackIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
        </svg>
    );
}

export function DownloadIcon() {
    return (
        <svg className="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14" />
        </svg>
    );
}

export function HeaderDeco() {
    return (
        <svg viewBox="0 0 220 120" className="h-24 w-auto" aria-hidden="true">
            <ellipse cx="118" cy="108" rx="58" ry="8" fill="#E3EAF2" opacity="0.85" />
            <path d="M42 42c0-14 11-25 25-25h22c14 0 25 11 25 25v20c0 5-2 9-6 12l-14 9 4-12c1-2 1-4 1-6V42c0-7-6-13-13-13H67c-7 0-13 6-13 13v22c0 7 6 13 13 13h10" fill="#3B8BEA" />
            <circle cx="68" cy="50" r="3.5" fill="#FFFFFF" opacity="0.95" />
            <circle cx="82" cy="50" r="3.5" fill="#FFFFFF" opacity="0.95" />
            <circle cx="96" cy="50" r="3.5" fill="#FFFFFF" opacity="0.95" />
            <path d="M102 48c0-13 11-24 24-24h20c13 0 24 11 24 24v18c0 5-2 9-6 11l-13 8 3-11c1-2 1-4 1-6V48c0-7-5-12-12-12h-20c-7 0-12 5-12 12v20c0 7 5 12 12 12h9" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2.2" />
            <rect x="126" y="58" width="32" height="5" rx="2.5" fill="#C9D6E5" />
            <rect x="126" y="68" width="22" height="5" rx="2.5" fill="#08B7A5" opacity="0.75" />
            <path d="M176 18l2.6 6.1 6.1 2.6-6.1 2.6-2.6 6.1-2.6-6.1-6.1-2.6 6.1-2.6 2.6-6.1z" fill="#08B7A5" />
            <path d="M28 24c8 0 13 7 10 15-8-1-13-7-10-15z" fill="#16A34A" />
            <circle cx="198" cy="42" r="4" fill="#08B7A5" opacity="0.45" />
        </svg>
    );
}

export function EmptyChatIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="mx-auto h-36 w-auto sm:h-44" aria-hidden="true">
            <circle cx="120" cy="86" r="68" fill="#EAF3FF" />
            <path d="M196 46l2.4 5.6 5.6 2.4-5.6 2.4-2.4 5.6-2.4-5.6-5.6-2.4 5.6-2.4 2.4-5.6z" fill="#2878D0" opacity="0.75" />
            <rect x="48" y="52" width="92" height="58" rx="16" fill="#3B8BEA" />
            <circle cx="72" cy="80" r="4" fill="#FFFFFF" opacity="0.9" />
            <circle cx="86" cy="80" r="4" fill="#FFFFFF" opacity="0.9" />
            <circle cx="100" cy="80" r="4" fill="#FFFFFF" opacity="0.9" />
            <rect x="108" y="70" width="78" height="52" rx="14" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2" />
            <rect x="122" y="86" width="42" height="5" rx="2.5" fill="#C9D6E5" />
            <rect x="122" y="96" width="30" height="5" rx="2.5" fill="#08B7A5" opacity="0.7" />
            <ellipse cx="120" cy="154" rx="52" ry="8" fill="#E3EAF2" />
        </svg>
    );
}

export function MessagesHeader({ title, subtitle, actions, className = '' }) {
    return (
        <div className={`relative mb-5 overflow-hidden ${className}`}>
            <div className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-teal/10 blur-2xl lg:block" aria-hidden="true" />
            <div className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-brand-blue/10 blur-2xl lg:block" aria-hidden="true" />
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal text-white shadow-sm">
                        <ChatIcon />
                    </span>
                    <div className="min-w-0">
                        <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{title}</h1>
                        {subtitle ? <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p> : null}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 lg:ps-4">
                    <div className="me-2 hidden lg:block">
                        <HeaderDeco />
                    </div>
                    {actions}
                </div>
            </div>
        </div>
    );
}

export function MessagesActions({ canStart = false, canModerate = false, openReports = 0 }) {
    const { t } = useLocale();

    return (
        <>
            {canModerate ? (
                <Link href={route('messages.moderation')} className="btn-outline">
                    {t('msg.moderation')}
                    {openReports > 0 ? ` (${openReports})` : ''}
                </Link>
            ) : null}
            {canStart ? (
                <Link href={route('messages.create')} className="btn-teal gap-2">
                    <ComposeIcon />
                    {t('msg.new')}
                </Link>
            ) : null}
        </>
    );
}
