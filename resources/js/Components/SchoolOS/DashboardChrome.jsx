import { subjectKind } from '@/Components/SchoolOS/TimetableChrome';
import { initials } from '@/Components/Comm/MessagesChrome';

export function WaveIcon({ className = 'h-8 w-8' }) {
    return (
        <svg className={className} viewBox="0 0 32 32" aria-hidden="true">
            <text x="2" y="26" fontSize="24">
                👋
            </text>
        </svg>
    );
}

export function CapIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
        </svg>
    );
}

export function BooksIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z" />
        </svg>
    );
}

export function ClipboardIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
    );
}

export function QuizMarkIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export function CoinsIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 12a4 4 0 108 0 4 4 0 00-8 0zm8.5-6.5a4 4 0 010 13M7.5 5.5a4 4 0 000 13" />
        </svg>
    );
}

export function CalendarMiniIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z" />
        </svg>
    );
}

export function PlayIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M14.752 11.168l-6.5-3.75A1 1 0 007 8.25v7.5a1 1 0 001.252.968l6.5-3.75a1 1 0 000-1.8z" />
        </svg>
    );
}

export function BoltIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M13 3L4 14h7l-1 7 9-11h-7l1-7z" />
        </svg>
    );
}

export function MegaphoneMini({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M11 5L6 9H3v6h3l5 4V5zm7 1.5a6.5 6.5 0 010 11" />
        </svg>
    );
}

export function TrophyIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 5h8v2a4 4 0 01-4 4 4 4 0 01-4-4V5zm0 0H5a2 2 0 002 4h1M16 5h3a2 2 0 01-2 4h-1M12 11v3m-3 5h6m-3 0v-2" />
        </svg>
    );
}

export function PinIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 21s7-5.4 7-11a7 7 0 10-14 0c0 5.6 7 11 7 11z" />
            <circle cx="12" cy="10" r="2.2" />
        </svg>
    );
}

const LESSON_TONES = {
    violet: { row: 'bg-violet-50', icon: 'bg-violet-200 text-violet-800' },
    teal: { row: 'bg-teal/10', icon: 'bg-teal/25 text-teal-800' },
    green: { row: 'bg-emerald-50', icon: 'bg-emerald-200 text-emerald-800' },
    amber: { row: 'bg-amber-50', icon: 'bg-amber-200 text-amber-800' },
    blue: { row: 'bg-sky-50', icon: 'bg-sky-200 text-sky-800' },
    pink: { row: 'bg-fuchsia-50', icon: 'bg-fuchsia-200 text-fuchsia-800' },
    navy: { row: 'bg-navy/5', icon: 'bg-navy/10 text-navy' },
};

export function TodayLesson({ slot }) {
    const kind = subjectKind(slot.subject);
    const tone = LESSON_TONES[kind] || LESSON_TONES.navy;
    const meta = [slot.class, slot.room].filter(Boolean).join(' · ');

    return (
        <li className={`flex items-center gap-3 rounded-2xl px-3 py-2.5 ${tone.row}`}>
            <span dir="ltr" className="w-[6.5rem] shrink-0 text-sm font-semibold tabular-nums text-navy">
                {slot.starts_at} – {slot.ends_at}
            </span>
            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${tone.icon}`} aria-hidden="true">
                <BooksIcon className="h-4 w-4" />
            </span>
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-navy">{slot.subject}</p>
                {meta ? <p className="truncate text-[11px] text-slate-500">{meta}</p> : null}
            </div>
        </li>
    );
}

export function ProgressRing({ value }) {
    const pct = Math.max(0, Math.min(100, Number(value) || 0));
    const r = 34;
    const c = 2 * Math.PI * r;
    const offset = c * (1 - pct / 100);

    return (
        <svg viewBox="0 0 88 88" className="h-[5.5rem] w-[5.5rem] shrink-0" aria-hidden="true">
            <circle cx="44" cy="44" r={r} fill="none" stroke="#E7F7F4" strokeWidth="8" />
            <circle
                cx="44"
                cy="44"
                r={r}
                fill="none"
                stroke="#08B7A5"
                strokeWidth="8"
                strokeLinecap="round"
                strokeDasharray={c}
                strokeDashoffset={offset}
                transform="rotate(-90 44 44)"
            />
            <text x="44" y="48" textAnchor="middle" className="fill-navy" style={{ fontSize: '16px', fontWeight: 700 }}>
                {value == null ? '—' : `${pct}%`}
            </text>
        </svg>
    );
}

export function SchoolHeroArt({ name }) {
    const label = String(name || '').slice(0, 22);

    return (
        <svg viewBox="0 0 280 150" className="h-[7.5rem] w-auto max-w-[18rem] sm:h-[8.75rem]" aria-hidden="true">
            <ellipse cx="150" cy="138" rx="110" ry="10" fill="#D7EFE8" opacity="0.9" />
            <ellipse cx="48" cy="36" rx="22" ry="10" fill="#FFFFFF" />
            <ellipse cx="62" cy="36" rx="16" ry="8" fill="#F8FBFF" />
            <ellipse cx="230" cy="28" rx="26" ry="11" fill="#FFFFFF" />
            <ellipse cx="246" cy="28" rx="14" ry="7" fill="#F8FBFF" />
            <circle cx="24" cy="58" r="4" fill="#FDE68A" />
            <path d="M38 118c18-42 8-62 28-72" fill="none" stroke="#16A34A" strokeWidth="6" strokeLinecap="round" />
            <ellipse cx="72" cy="52" rx="22" ry="16" fill="#22C55E" />
            <ellipse cx="54" cy="62" rx="16" ry="12" fill="#4ADE80" />
            <rect x="48" y="92" width="8" height="28" rx="2" fill="#92400E" />
            <path d="M236 118c-16-40-4-60-24-72" fill="none" stroke="#15803D" strokeWidth="6" strokeLinecap="round" />
            <ellipse cx="206" cy="54" rx="24" ry="17" fill="#16A34A" />
            <ellipse cx="224" cy="64" rx="16" ry="12" fill="#4ADE80" />
            <rect x="218" y="94" width="8" height="26" rx="2" fill="#92400E" />
            <rect x="86" y="58" width="108" height="72" rx="8" fill="#F8E7C9" stroke="#E8C99A" strokeWidth="2" />
            <rect x="86" y="48" width="108" height="16" rx="6" fill="#3B82F6" />
            <polygon points="80,56 140,28 200,56" fill="#60A5FA" />
            <rect x="124" y="88" width="32" height="42" rx="3" fill="#F59E0B" />
            <circle cx="148" cy="110" r="2" fill="#92400E" />
            <rect x="98" y="72" width="16" height="14" rx="2" fill="#93C5FD" />
            <rect x="166" y="72" width="16" height="14" rx="2" fill="#93C5FD" />
            <rect x="98" y="100" width="16" height="14" rx="2" fill="#93C5FD" />
            <rect x="166" y="100" width="16" height="14" rx="2" fill="#93C5FD" />
            <rect x="118" y="36" width="44" height="10" rx="2" fill="#FFFFFF" />
            <text x="140" y="44" textAnchor="middle" fill="#1D4ED8" style={{ fontSize: '6px', fontWeight: 700 }}>
                {label}
            </text>
            <rect x="198" y="34" width="22" height="14" rx="1" fill="#CE1126" />
            <rect x="198" y="39" width="22" height="4" fill="#0C1C8C" />
            <rect x="198" y="43" width="22" height="5" fill="#3A7728" />
            <rect x="208" y="28" width="2" height="20" fill="#64748B" />
        </svg>
    );
}

export function StudentAvatar({ name, photoUrl }) {
    if (photoUrl) {
        return <img src={photoUrl} alt="" className="h-[4.5rem] w-[4.5rem] rounded-full object-cover ring-4 ring-white shadow-md sm:h-20 sm:w-20" />;
    }

    return (
        <span className="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-teal/15 text-xl font-bold text-teal ring-4 ring-white shadow-md sm:h-20 sm:w-20 sm:text-2xl">
            {initials(name)}
        </span>
    );
}

export function localeTag(locale) {
    if (locale === 'fr') return 'fr';
    if (locale === 'ar') return 'ar';

    return 'en-GB';
}

export function formatHeroDate(date, locale) {
    return new Intl.DateTimeFormat(localeTag(locale), { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(date);
}

export function formatEventDay(iso, locale) {
    if (!iso) return '';

    return new Intl.DateTimeFormat(localeTag(locale), { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(iso));
}

export function formatEventTime(iso, locale) {
    if (!iso) return '';

    return new Intl.DateTimeFormat(localeTag(locale), { hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
}
