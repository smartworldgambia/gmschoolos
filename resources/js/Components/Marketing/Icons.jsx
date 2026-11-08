export function IconCheck({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fillRule="evenodd"
                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                clipRule="evenodd"
            />
        </svg>
    );
}

export function IconPlay({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.841Z" />
        </svg>
    );
}

export function IconMenu({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 7h16M4 12h16M4 17h16" />
        </svg>
    );
}

export function IconClose({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M6 18 18 6M6 6l12 12" />
        </svg>
    );
}

export function IconChevron({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fillRule="evenodd"
                d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                clipRule="evenodd"
            />
        </svg>
    );
}

const stroke = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.7, strokeLinecap: 'round', strokeLinejoin: 'round' };

export function FeatureIcon({ name, className = 'h-6 w-6' }) {
    const icons = {
        management: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
        ),
        lms: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                <path d="M6 12v5c3 3 9 3 12 0v-5" />
            </svg>
        ),
        exams: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                <rect x="9" y="3" width="6" height="4" rx="1" />
                <path d="M9 12h6M9 16h4" />
            </svg>
        ),
        fees: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <path d="M2 10h20M6 15h2" />
            </svg>
        ),
        timetable: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" />
                <path d="M16 2v4M8 2v4M3 10h18" />
            </svg>
        ),
        reports: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-3" />
            </svg>
        ),
        communication: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
            </svg>
        ),
        more: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
        ),
        schools: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" />
            </svg>
        ),
        students: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
        ),
        teachers: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21v-1a6 6 0 0 1 12 0v1" />
            </svg>
        ),
        uptime: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M22 12A10 10 0 1 1 12 2" />
                <path d="M22 4v6h-6M12 6v6l4 2" />
            </svg>
        ),
        compliance: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <path d="m9 12 2 2 4-4" />
            </svg>
        ),
        nursery: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M12 22c4-4 6-7.5 6-11a6 6 0 1 0-12 0c0 3.5 2 7 6 11z" />
                <circle cx="12" cy="11" r="2" />
            </svg>
        ),
        primary: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M22 10 12 5 2 10l10 5 10-5z" />
                <path d="M6 12v5c3 2 9 2 12 0v-5" />
            </svg>
        ),
        secondary: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
        ),
        tertiary: (
            <svg className={className} viewBox="0 0 24 24" {...stroke} aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6M12 18v-6M9 15h6" />
            </svg>
        ),
    };

    return icons[name] ?? icons.more;
}
