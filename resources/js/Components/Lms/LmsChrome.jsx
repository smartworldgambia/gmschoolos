import { subjectCoverStyle, subjectKind } from '@/lib/lmsTone';
import { useMemo, useState } from 'react';

export function CapIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"
            />
        </svg>
    );
}

export function BookIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z" />
        </svg>
    );
}

export function ClipboardIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
            />
        </svg>
    );
}

export function QuizIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        </svg>
    );
}

export function ProgressIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
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

export function PlayMini({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M6.5 4.8A1 1 0 018.1 4l8 5.2a1 1 0 010 1.6l-8 5.2A1 1 0 016.5 15.2V4.8z" />
        </svg>
    );
}

export function GridIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z" />
        </svg>
    );
}

export function ListIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    );
}

export function CalendarIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z" />
        </svg>
    );
}

export function InfoIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export function ChevronIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
    );
}

export function subjectHeroClass(kind) {
    const map = {
        math: 'lms-hero-icon-math',
        biology: 'lms-hero-icon-bio',
        english: 'lms-hero-icon-eng',
        social: 'lms-hero-icon-soc',
        science: 'lms-hero-icon-sci',
    };

    return map[kind] || 'lms-hero-icon-default';
}

export function SubjectGlyph({ kind, compact = false }) {
    const common = compact ? 'h-7 w-7 text-white/95' : 'h-12 w-12 text-white/95 drop-shadow-sm';
    if (kind === 'math') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M4 8h6M7 5v6M14 6l6 6M20 6l-6 6M4 16h6m4 0h6m-6 4h6" />
            </svg>
        );
    }
    if (kind === 'biology') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M12 22V11M8 13c-3-2-4-6-1-9 4 1 5 5 5 7M16 13c3-2 4-6 1-9-4 1-5 5-5 7M7 17c2 2 8 2 10 0" />
            </svg>
        );
    }
    if (kind === 'english') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z" />
            </svg>
        );
    }
    if (kind === 'social') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={1.6}
                    d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.5-2.2 4-5.5 4-9s-1.5-6.8-4-9m0 18c-2.5-2.2-4-5.5-4-9s1.5-6.8 4-9M3.6 9h16.8M3.6 15h16.8"
                />
            </svg>
        );
    }
    if (kind === 'science') {
        return (
            <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.6} d="M9 3h6M10 3v6L5 18a2 2 0 002 3h10a2 2 0 002-3l-5-9V3" />
            </svg>
        );
    }

    return <CapIcon className={common} />;
}

function CoverScene({ kind }) {
    if (kind === 'math') {
        return (
            <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <rect width="400" height="180" fill="#092B55" />
                <rect x="16" y="14" width="368" height="152" rx="8" fill="#0c3a6e" stroke="#5b8fd4" strokeWidth="2" />
                <text x="36" y="58" fill="#dbeafe" fontSize="28" fontWeight="700" fontFamily="Georgia, serif">
                    a² + b² = c²
                </text>
                <path d="M48 148 L132 148 L132 78 Z" fill="none" stroke="#93c5fd" strokeWidth="3" />
                <circle cx="300" cy="88" r="42" fill="none" stroke="#60a5fa" strokeWidth="2" opacity="0.7" />
                <text x="286" y="98" fill="#93c5fd" fontSize="36" fontFamily="Georgia, serif">
                    π
                </text>
                <path d="M220 40 h90" stroke="#38bdf8" strokeWidth="2" opacity="0.5" />
                <path d="M220 40 L250 70 L280 50 L310 88" fill="none" stroke="#38bdf8" strokeWidth="2" />
            </svg>
        );
    }
    if (kind === 'biology') {
        return (
            <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <rect width="400" height="180" fill="#0B5C3A" />
                <ellipse cx="300" cy="90" rx="90" ry="70" fill="#147a4e" />
                <path d="M70 160 C90 90 150 70 170 20" fill="none" stroke="#86efac" strokeWidth="6" />
                <ellipse cx="118" cy="78" rx="28" ry="16" fill="#4ade80" transform="rotate(-30 118 78)" />
                <ellipse cx="150" cy="54" rx="26" ry="14" fill="#22c55e" transform="rotate(20 150 54)" />
                <circle cx="280" cy="88" r="36" fill="#d1fae5" opacity="0.35" />
                <path d="M268 70 h24 M280 58 v50 M260 108 h40" stroke="#ecfdf5" strokeWidth="3" />
                <circle cx="280" cy="70" r="10" fill="none" stroke="#ecfdf5" strokeWidth="3" />
            </svg>
        );
    }
    if (kind === 'english') {
        return (
            <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <rect width="400" height="180" fill="#7C2D12" />
                <rect x="48" y="38" width="70" height="104" rx="4" fill="#f59e0b" />
                <rect x="126" y="28" width="78" height="114" rx="4" fill="#fde68a" />
                <rect x="212" y="44" width="64" height="98" rx="4" fill="#fdba74" />
                <text x="300" y="118" fill="#fff7ed" fontSize="64" fontWeight="800" fontFamily="Georgia, serif">
                    Aa
                </text>
            </svg>
        );
    }
    if (kind === 'social') {
        return (
            <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <rect width="400" height="180" fill="#1E3A5F" />
                <circle cx="210" cy="90" r="62" fill="#0e7490" />
                <ellipse cx="210" cy="90" rx="24" ry="62" fill="none" stroke="#a5f3fc" strokeWidth="3" />
                <path d="M148 90 h124 M210 28 v124" stroke="#a5f3fc" strokeWidth="2.5" />
                <path d="M158 58 Q210 78 262 58 M158 122 Q210 102 262 122" fill="none" stroke="#67e8f9" strokeWidth="2" />
            </svg>
        );
    }
    if (kind === 'science') {
        return (
            <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <rect width="400" height="180" fill="#134E4A" />
                <rect x="70" y="28" width="18" height="50" rx="4" fill="#99f6e4" />
                <path d="M60 78 h38 l24 70 H36 Z" fill="#2dd4bf" />
                <rect x="188" y="22" width="18" height="44" rx="4" fill="#a5f3fc" />
                <path d="M176 66 h42 l28 78 H148 Z" fill="#22d3ee" />
                <circle cx="318" cy="78" r="34" fill="#5eead4" opacity="0.35" />
                <circle cx="300" cy="120" r="16" fill="#fde047" opacity="0.8" />
                <circle cx="340" cy="128" r="12" fill="#fb7185" opacity="0.85" />
            </svg>
        );
    }

    return (
        <svg viewBox="0 0 400 180" className="absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <rect width="400" height="180" fill="#082B50" />
            <rect x="210" y="36" width="140" height="92" rx="12" fill="#fff" opacity="0.12" />
            <rect x="226" y="52" width="108" height="8" rx="4" fill="#fff" opacity="0.35" />
            <rect x="226" y="70" width="78" height="8" rx="4" fill="#08B7A5" opacity="0.7" />
            <path d="M48 120c12-28 52-28 64 0H48z" fill="#0B5C3A" />
            <path d="M54 120h52v10c0 6-6 10-26 10s-26-4-26-10v-10z" fill="#009B63" />
        </svg>
    );
}

export function CourseCover({ course, tone, index = 0, compact = false }) {
    const kind = subjectKind(course);
    const src = course.cover_url || course.image_url || course.thumbnail_url || course.thumbnail;

    return (
        <div
            className={compact ? 'lms-course-cover lms-course-cover-compact' : 'lms-course-cover'}
            style={src ? undefined : { background: subjectCoverStyle(kind, index, tone.primaryColor, tone.accentColor) }}
        >
            {src ? <img src={src} alt="" /> : compact ? <SubjectGlyph kind={kind} compact /> : <CoverScene kind={kind} />}
        </div>
    );
}

export function CoursesHeroArt() {
    return (
        <svg viewBox="0 0 280 140" className="h-[7.5rem] w-auto" aria-hidden="true">
            <ellipse cx="168" cy="126" rx="78" ry="10" fill="#E3EAF2" />
            <rect x="118" y="38" width="118" height="74" rx="12" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2" />
            <rect x="118" y="38" width="118" height="14" rx="12" fill="#DCE6F2" />
            <circle cx="130" cy="45" r="2.4" fill="#94A3B8" />
            <circle cx="140" cy="45" r="2.4" fill="#94A3B8" />
            <rect x="132" y="64" width="88" height="6" rx="3" fill="#C9D6E5" />
            <rect x="132" y="78" width="64" height="6" rx="3" fill="#08B7A5" opacity="0.8" />
            <circle cx="236" cy="78" r="28" fill="#2878D0" opacity="0.18" />
            <circle cx="236" cy="78" r="18" fill="none" stroke="#2878D0" strokeWidth="2" />
            <path d="M218 78 h36 M236 60 v36" stroke="#2878D0" strokeWidth="1.6" />
            <path d="M42 102c10-24 46-24 56 0H42z" fill="#0B5C3A" />
            <path d="M48 102h44v10c0 5-5 9-22 9s-22-4-22-9v-10z" fill="#009B63" />
            <rect x="62" y="78" width="10" height="24" rx="1" fill="#D97706" />
            <rect x="52" y="84" width="8" height="18" rx="1" fill="#2878D0" />
            <rect x="74" y="86" width="8" height="16" rx="1" fill="#00BFA6" />
            <path d="M88 22c8 10 4 22-6 24 8 2 12 12 4 20-12-8-22-4-24 8-4-12 4-22 16-24-10-6-10-18 2-24 0 10 8 14 8-4z" fill="#86efac" />
        </svg>
    );
}

export function CourseHeroArt({ kind = 'default' }) {
    if (kind === 'math') {
        return (
            <svg viewBox="0 0 260 140" className="h-[8.25rem] w-auto" aria-hidden="true">
                <text x="8" y="48" fill="#2878D0" opacity="0.35" fontSize="42" fontFamily="Georgia, serif">
                    π
                </text>
                <path d="M70 118 L130 118 L130 52 Z" fill="none" stroke="#082B50" strokeWidth="2.4" />
                <path d="M168 36 L210 70 L188 108" fill="none" stroke="#08B7A5" strokeWidth="2" />
                <rect x="188" y="78" width="52" height="40" rx="6" fill="#082B50" />
                <rect x="196" y="86" width="36" height="4" rx="2" fill="#93c5fd" />
                <rect x="196" y="96" width="24" height="4" rx="2" fill="#08B7A5" />
                <rect x="150" y="96" width="18" height="28" rx="2" fill="#D97706" />
                <rect x="132" y="104" width="16" height="20" rx="2" fill="#2878D0" />
                <rect x="170" y="108" width="14" height="16" rx="2" fill="#009B63" />
            </svg>
        );
    }
    if (kind === 'biology') {
        return (
            <svg viewBox="0 0 240 130" className="h-[8.25rem] w-auto" aria-hidden="true">
                <ellipse cx="150" cy="70" rx="70" ry="48" fill="#d1fae5" />
                <path d="M48 110 C70 50 120 44 138 12" fill="none" stroke="#059669" strokeWidth="5" />
                <ellipse cx="88" cy="64" rx="22" ry="12" fill="#34d399" transform="rotate(-28 88 64)" />
                <ellipse cx="118" cy="40" rx="20" ry="11" fill="#10b981" transform="rotate(18 118 40)" />
                <circle cx="176" cy="72" r="22" fill="none" stroke="#082B50" strokeWidth="3" />
                <path d="M176 50 v44 M158 72 h36" stroke="#082B50" strokeWidth="2" />
            </svg>
        );
    }

    return (
        <svg viewBox="0 0 240 130" className="h-[8.25rem] w-auto" aria-hidden="true">
            <rect x="110" y="28" width="110" height="72" rx="10" fill="#fff" stroke="#D7E6F5" strokeWidth="2" />
            <rect x="122" y="44" width="86" height="6" rx="3" fill="#C9D6E5" />
            <rect x="122" y="58" width="58" height="6" rx="3" fill="#08B7A5" />
            <path d="M36 96c10-22 44-22 54 0H36z" fill="#0B5C3A" />
            <path d="M42 96h42v8c0 4-5 8-21 8s-21-4-21-8v-8z" fill="#009B63" />
            <circle cx="210" cy="36" r="10" fill="#2878D0" opacity="0.25" />
        </svg>
    );
}

export function EmptyIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="mx-auto h-36 w-auto sm:h-44" aria-hidden="true">
            <circle cx="120" cy="86" r="68" fill="#EAF3FF" />
            <path d="M196 46l2.4 5.6 5.6 2.4-5.6 2.4-2.4 5.6-2.4-5.6-5.6-2.4 5.6-2.4 2.4-5.6z" fill="#2878D0" opacity="0.75" />
            <rect x="86" y="52" width="92" height="58" rx="10" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2.2" />
            <rect x="100" y="68" width="64" height="6" rx="3" fill="#C9D6E5" />
            <rect x="100" y="82" width="46" height="6" rx="3" fill="#08B7A5" opacity="0.65" />
            <path d="M48 70c8-18 32-18 40 0H48z" fill="#0B5C3A" />
            <path d="M52 70h32v8c0 4-4 8-16 8s-16-4-16-8v-8z" fill="#009B63" />
            <ellipse cx="120" cy="154" rx="52" ry="8" fill="#E3EAF2" />
        </svg>
    );
}

function weekdayLabels(locale) {
    const loc = locale === 'ar' ? 'ar' : locale === 'fr' ? 'fr' : 'en-GB';

    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(Date.UTC(2026, 10, 2 + i));

        return new Intl.DateTimeFormat(loc, { weekday: 'short', timeZone: 'UTC' }).format(d);
    });
}

export function MiniCalendar({ marks = [], locale = 'en', prevLabel, nextLabel }) {
    const [cursor, setCursor] = useState(() => new Date());
    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const markSet = useMemo(() => new Set(marks.filter(Boolean)), [marks]);
    const first = new Date(year, month, 1);
    const startOffset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    const loc = locale === 'ar' ? 'ar' : locale === 'fr' ? 'fr' : 'en-GB';
    const title = new Intl.DateTimeFormat(loc, { month: 'long', year: 'numeric' }).format(cursor);
    const labels = weekdayLabels(locale);
    const cells = [];
    for (let i = 0; i < startOffset; i += 1) {
        cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d += 1) {
        cells.push(d);
    }

    return (
        <div className="lms-cal">
            <div className="lms-cal-head">
                <button type="button" className="lms-cal-nav" aria-label={prevLabel} onClick={() => setCursor(new Date(year, month - 1, 1))}>
                    ‹
                </button>
                <p className="lms-cal-title">{title}</p>
                <button type="button" className="lms-cal-nav" aria-label={nextLabel} onClick={() => setCursor(new Date(year, month + 1, 1))}>
                    ›
                </button>
            </div>
            <div className="lms-cal-grid">
                {labels.map((label, i) => (
                    <span key={i} className="lms-cal-dow">
                        {label}
                    </span>
                ))}
                {cells.map((day, i) => {
                    if (!day) {
                        return <span key={`e-${i}`} />;
                    }
                    const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
                    const marked = markSet.has(iso);

                    return (
                        <span key={iso} className={`lms-cal-day${isToday ? ' lms-cal-today' : ''}${marked ? ' lms-cal-mark' : ''}`}>
                            {day}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

export function LmsHeader({ title, subtitle, actions }) {
    return (
        <div className="relative mb-5 overflow-hidden">
            <div className="pointer-events-none absolute -end-8 -top-10 hidden h-40 w-72 rounded-full bg-sky-100 blur-2xl lg:block" aria-hidden="true" />
            <div className="pointer-events-none absolute end-24 top-0 hidden h-32 w-48 rounded-full bg-teal/10 blur-2xl lg:block" aria-hidden="true" />
            <div className="pointer-events-none absolute end-2 top-0 hidden opacity-95 lg:block" aria-hidden="true">
                <CoursesHeroArt />
            </div>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                    <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-blue text-white shadow-sm">
                        <CapIcon />
                    </span>
                    <div className="min-w-0 lg:pe-40">
                        <h1 className="text-[26px] font-bold tracking-tight text-navy sm:text-[30px]">{title}</h1>
                        {subtitle ? <p className="mt-1 max-w-xl text-sm leading-relaxed text-slate-500 sm:text-[15px]">{subtitle}</p> : null}
                    </div>
                </div>
                {actions ? <div className="flex flex-wrap items-center gap-2 lg:ps-4">{actions}</div> : null}
            </div>
        </div>
    );
}
