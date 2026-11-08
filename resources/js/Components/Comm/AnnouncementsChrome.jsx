import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

const KIND_TESTS = [
    ['sports', /sport|football|soccer|athlet|tournament|match|basketball|volleyball|رياض|مباراة|كرة/i],
    ['meeting', /meeting|briefing|pta|assembly|staff\b|reunion|réunion|اجتماع|إحاطة/i],
    ['academic', /exam|timetable|homework|lesson|assignment|curriculum|academic|term\b|grade\b|devoir|examen|واجب|امتحان|جدول/i],
];

export function inferKind(title, body = '') {
    const hay = `${title ?? ''} ${body ?? ''}`;
    for (const [kind, re] of KIND_TESTS) {
        if (re.test(hay)) {
            return kind;
        }
    }

    return null;
}

export function kindTone(kind) {
    if (kind === 'sports') {
        return 'bg-teal/10 text-teal';
    }
    if (kind === 'meeting') {
        return 'bg-amber-50 text-amber-800';
    }
    if (kind === 'academic') {
        return 'bg-brand-blue/10 text-brand-blue';
    }

    return 'bg-slate-100 text-slate-600';
}

export function MegaphoneIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M11 5L6 9H3v6h3l5 4V5zm7 1.5a6.5 6.5 0 010 11M16 9a3.5 3.5 0 010 6" />
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

export function PlusIcon() {
    return (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 5v14M5 12h14" />
        </svg>
    );
}

export function HeaderDeco() {
    return (
        <svg viewBox="0 0 200 118" className="h-24 w-auto" aria-hidden="true">
            <ellipse cx="108" cy="106" rx="58" ry="8" fill="#E3EAF2" opacity="0.85" />
            <rect x="86" y="38" width="82" height="58" rx="12" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2" />
            <rect x="98" y="50" width="58" height="6" rx="3" fill="#C9D6E5" />
            <rect x="98" y="62" width="46" height="6" rx="3" fill="#C9D6E5" />
            <rect x="98" y="74" width="34" height="6" rx="3" fill="#08B7A5" opacity="0.7" />
            <path d="M38 44c0-8 6-14 14-14h18c4 0 8 2 10 6l28 44c2 4 0 8-4 10l-8 4c-4 2-8 0-10-4L48 52c-6-8-10-8-10-8z" fill="#F59E0B" />
            <path d="M52 36h16c3 0 6 1.5 8 4.5L98 78c1.5 3 0 6-3 7.5l-6 3c-3 1.5-6 0-7.5-3L54 46c-2-4-2-10-2-10z" fill="#FBBF24" />
            <circle cx="62" cy="48" r="5" fill="#FDE68A" opacity="0.7" />
            <path d="M168 16l2.6 6.1 6.1 2.6-6.1 2.6-2.6 6.1-2.6-6.1-6.1-2.6 6.1-2.6 2.6-6.1z" fill="#08B7A5" />
            <circle cx="30" cy="24" r="3.5" fill="#2878D0" />
            <path d="M178 52c6 0 10 5 8 12-7-1-11-6-8-12z" fill="#16A34A" />
        </svg>
    );
}

export function EmptyIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="mx-auto h-36 w-auto sm:h-44" aria-hidden="true">
            <circle cx="120" cy="86" r="68" fill="#EAF3FF" />
            <path d="M196 46l2.4 5.6 5.6 2.4-5.6 2.4-2.4 5.6-2.4-5.6-5.6-2.4 5.6-2.4 2.4-5.6z" fill="#2878D0" opacity="0.75" />
            <rect x="78" y="46" width="92" height="72" rx="14" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2.2" />
            <rect x="92" y="62" width="64" height="6" rx="3" fill="#C9D6E5" />
            <rect x="92" y="76" width="50" height="6" rx="3" fill="#C9D6E5" />
            <rect x="92" y="90" width="36" height="6" rx="3" fill="#08B7A5" opacity="0.65" />
            <path d="M48 58c0-7 5-12 12-12h14c4 0 7 2 9 5l20 32c2 3 1 7-2 9l-6 3c-3 2-7 1-9-2L54 66c-4-6-6-8-6-8z" fill="#F59E0B" />
            <ellipse cx="120" cy="154" rx="52" ry="8" fill="#E3EAF2" />
        </svg>
    );
}

export function AnnouncementsHeader({ title, subtitle, actions, className = '' }) {
    return (
        <div className={`relative mb-5 overflow-hidden ${className}`}>
            <div className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-teal/10 blur-2xl lg:block" aria-hidden="true" />
            <div className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-brand-blue/10 blur-2xl lg:block" aria-hidden="true" />
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal text-white shadow-sm">
                        <MegaphoneIcon />
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

export function AnnouncementsActions({ canAuthor = false }) {
    const { t } = useLocale();

    if (!canAuthor) {
        return null;
    }

    return (
        <Link href={route('announcements.create')} className="btn-teal gap-2">
            <PlusIcon />
            {t('ann.new')}
        </Link>
    );
}
