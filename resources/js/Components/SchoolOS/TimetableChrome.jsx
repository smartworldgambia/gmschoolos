export function CalendarIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z" />
        </svg>
    );
}

export function ClockIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export function UsersIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" strokeWidth={1.8} />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
        </svg>
    );
}

export function PrinterIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M6 9V4h12v5M6 14H4a2 2 0 01-2-2v-1a2 2 0 012-2h16a2 2 0 012 2v1a2 2 0 01-2 2h-2M6 14v6h12v-6" />
        </svg>
    );
}

export function HeaderDeco() {
    return (
        <svg viewBox="0 0 260 130" className="h-[8.5rem] w-auto max-w-[16rem]" aria-hidden="true">
            <ellipse cx="168" cy="118" rx="72" ry="8" fill="#D7EFE8" opacity="0.9" />
            <path d="M214 38c12 18 8 38-6 48" fill="none" stroke="#4ADE80" strokeWidth="8" strokeLinecap="round" />
            <path d="M228 42c-10 6-16 18-14 30" fill="none" stroke="#16A34A" strokeWidth="7" strokeLinecap="round" />
            <ellipse cx="232" cy="36" rx="16" ry="10" fill="#4ADE80" transform="rotate(-20 232 36)" />
            <ellipse cx="208" cy="48" rx="14" ry="9" fill="#22C55E" transform="rotate(18 208 48)" />
            <rect x="118" y="18" width="86" height="78" rx="10" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2" />
            <rect x="118" y="18" width="86" height="22" rx="10" fill="#3B82F6" />
            <rect x="118" y="32" width="86" height="8" fill="#3B82F6" />
            <circle cx="132" cy="29" r="3.2" fill="#FFFFFF" />
            <circle cx="190" cy="29" r="3.2" fill="#FFFFFF" />
            <rect x="132" y="50" width="16" height="14" rx="3" fill="#BBF7D0" />
            <path d="M136 57l3 3 6-6" fill="none" stroke="#16A34A" strokeWidth="1.6" strokeLinecap="round" />
            <rect x="154" y="50" width="16" height="14" rx="3" fill="#E2E8F0" />
            <rect x="176" y="50" width="16" height="14" rx="3" fill="#BBF7D0" />
            <path d="M180 57l3 3 6-6" fill="none" stroke="#16A34A" strokeWidth="1.6" strokeLinecap="round" />
            <rect x="132" y="70" width="16" height="14" rx="3" fill="#E2E8F0" />
            <rect x="154" y="70" width="16" height="14" rx="3" fill="#BBF7D0" />
            <path d="M158 77l3 3 6-6" fill="none" stroke="#16A34A" strokeWidth="1.6" strokeLinecap="round" />
            <rect x="52" y="58" width="58" height="14" rx="3" fill="#F59E0B" />
            <rect x="56" y="46" width="50" height="14" rx="3" fill="#FBBF24" />
            <rect x="60" y="34" width="42" height="14" rx="3" fill="#60A5FA" />
            <circle cx="96" cy="86" r="22" fill="#F59E0B" />
            <circle cx="96" cy="86" r="16" fill="#FEF3C7" />
            <path d="M96 86V74" stroke="#92400E" strokeWidth="2" strokeLinecap="round" />
            <path d="M96 86l8 4" stroke="#92400E" strokeWidth="2" strokeLinecap="round" />
            <circle cx="96" cy="86" r="2" fill="#92400E" />
            <rect x="88" y="62" width="16" height="6" rx="3" fill="#F59E0B" />
        </svg>
    );
}

export function TimetableHeader({ title, subtitle, actions }) {
    return (
        <div className="relative mb-5 overflow-hidden">
            <div className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-teal/10 blur-2xl md:block" aria-hidden="true" />
            <div className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-brand-green/10 blur-2xl md:block" aria-hidden="true" />
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-green text-white shadow-sm">
                        <CalendarIcon />
                    </span>
                    <div className="min-w-0">
                        <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{title}</h1>
                        {subtitle ? <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p> : null}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 lg:ps-4">
                    <div className="me-1 hidden md:block" aria-hidden="true">
                        <HeaderDeco />
                    </div>
                    {actions}
                </div>
            </div>
        </div>
    );
}

const TONES = {
    violet: { chip: 'border-violet-100 bg-violet-50 text-violet-900', icon: 'bg-violet-200 text-violet-800' },
    teal: { chip: 'border-teal/20 bg-teal/10 text-teal-900', icon: 'bg-teal/25 text-teal-800' },
    green: { chip: 'border-emerald-100 bg-emerald-50 text-emerald-900', icon: 'bg-emerald-200 text-emerald-800' },
    amber: { chip: 'border-amber-100 bg-amber-50 text-amber-900', icon: 'bg-amber-200 text-amber-800' },
    blue: { chip: 'border-sky-100 bg-sky-50 text-sky-900', icon: 'bg-sky-200 text-sky-800' },
    pink: { chip: 'border-fuchsia-100 bg-fuchsia-50 text-fuchsia-900', icon: 'bg-fuchsia-200 text-fuchsia-800' },
    navy: { chip: 'border-navy/10 bg-navy/5 text-navy', icon: 'bg-navy/10 text-navy' },
};

export function subjectKind(name) {
    const hay = String(name || '').toLowerCase();
    if (/math|numeracy|algebra|arithmetic|geometry|رياضيات|mathématique/.test(hay)) return 'violet';
    if (/english|literature|language arts|anglais|إنجليز/.test(hay)) return 'teal';
    if (/biolog|nature|أحياء|biologie/.test(hay)) return 'green';
    if (/civic|citizenship|مدني/.test(hay)) return 'pink';
    if (/social|geograph|histor|society|جغراف|تاريخ|اجتماع/.test(hay)) return 'amber';
    if (/science|chemist|physic|laborat|lab\b|علم|كيمياء|فيزياء|مختبر/.test(hay)) return 'blue';

    return 'navy';
}

function SubjectGlyph({ kind }) {
    const common = 'h-4 w-4';
    if (kind === 'violet') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 8h6M7 5v6M14 6l6 6M20 6l-6 6" />
            </svg>
        );
    }
    if (kind === 'teal') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z" />
            </svg>
        );
    }
    if (kind === 'green') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 22V11M8 13c-3-2-4-6-1-9 4 1 5 5 5 7M16 13c3-2 4-6 1-9-4 1-5 5-5 7" />
            </svg>
        );
    }
    if (kind === 'amber') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.5-2.2 4-5.5 4-9s-1.5-6.8-4-9m0 18c-2.5-2.2-4-5.5-4-9s1.5-6.8 4-9" />
            </svg>
        );
    }
    if (kind === 'blue') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 3h6M10 3v6L5 18a2 2 0 002 3h10a2 2 0 002-3l-5-9V3" />
            </svg>
        );
    }
    if (kind === 'pink') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z" />
            </svg>
        );
    }

    return <CalendarIcon className={common} />;
}

export function LessonChip({ slot, t, onRemove }) {
    const kind = subjectKind(slot.subject);
    const tone = TONES[kind] || TONES.navy;
    const meta = [slot.class, slot.room].filter(Boolean).join(' · ');

    return (
        <div className={`rounded-xl border px-2 py-2 ${tone.chip} ${slot.is_published ? '' : 'ring-1 ring-amber-300'}`}>
            <div className="flex items-start gap-1.5">
                <span className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md ${tone.icon}`} aria-hidden="true">
                    <SubjectGlyph kind={kind} />
                </span>
                <div className="min-w-0">
                    <p className="text-sm font-semibold leading-snug">{slot.subject || t('results.col.subject')}</p>
                    {meta ? <p className="mt-0.5 text-[11px] leading-snug opacity-80">{meta}</p> : null}
                    {!slot.is_published ? <p className="mt-0.5 text-[11px] font-medium text-amber-800">{t('timetable.draft')}</p> : null}
                    {onRemove ? (
                        <button type="button" className="mt-1 text-[11px] font-medium text-red-700 hover:underline" onClick={onRemove}>
                            {t('acad.common.remove')}
                        </button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export function startOfWeek(date) {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const offset = d.getDay() === 0 ? -6 : 1 - d.getDay();
    d.setDate(d.getDate() + offset);

    return d;
}

export function addDays(date, n) {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    d.setDate(d.getDate() + n);

    return d;
}

export function isoWeekday(date) {
    const day = date.getDay();

    return day === 0 ? 7 : day;
}

function localeTag(locale) {
    if (locale === 'fr') return 'fr';
    if (locale === 'ar') return 'ar';

    return 'en-GB';
}

export function formatWeekRange(monday, locale) {
    const friday = addDays(monday, 4);
    const loc = localeTag(locale);
    const start = new Intl.DateTimeFormat(loc, { month: 'short', day: 'numeric' }).format(monday);
    const end = new Intl.DateTimeFormat(loc, { month: 'short', day: 'numeric', year: 'numeric' }).format(friday);

    return `${start} – ${end}`;
}

export function formatColDate(date, locale) {
    return new Intl.DateTimeFormat(localeTag(locale), { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
}

export function weekChoices(from = new Date(), span = 8) {
    const current = startOfWeek(from);
    const rows = [];
    for (let i = -span; i <= span; i += 1) {
        rows.push(addDays(current, i * 7));
    }

    return rows;
}

export function mondayKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}
