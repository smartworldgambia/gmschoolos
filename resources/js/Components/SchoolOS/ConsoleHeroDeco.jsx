const variants = {
    scale: (
        <g>
            <ellipse cx="86" cy="18" rx="22" ry="8" fill="#08B7A5" opacity="0.16" />
            <ellipse cx="102" cy="14" rx="12" ry="5" fill="#2878D0" opacity="0.14" />
            <rect x="18" y="28" width="22" height="34" rx="5" fill="#08B7A5" opacity="0.2" />
            <rect x="44" y="22" width="22" height="40" rx="5" fill="#2878D0" opacity="0.16" />
            <rect x="70" y="16" width="22" height="46" rx="5" fill="#082B50" opacity="0.1" />
            <rect x="22" y="34" width="14" height="3" rx="1.5" fill="#08B7A5" opacity="0.45" />
            <rect x="48" y="28" width="14" height="3" rx="1.5" fill="#2878D0" opacity="0.4" />
            <rect x="74" y="22" width="14" height="3" rx="1.5" fill="#082B50" opacity="0.25" />
            <path d="M96 40c8 4 14 2 20-6" stroke="#08B7A5" strokeWidth="3" strokeLinecap="round" fill="none" />
            <circle cx="108" cy="48" r="10" fill="#08B7A5" opacity="0.18" />
        </g>
    ),
    backups: (
        <g>
            <ellipse cx="78" cy="16" rx="20" ry="7" fill="#08B7A5" opacity="0.18" />
            <ellipse cx="96" cy="14" rx="12" ry="5" fill="#2878D0" opacity="0.14" />
            <ellipse cx="46" cy="26" rx="22" ry="8" fill="#08B7A5" opacity="0.22" />
            <path d="M24 26v22c0 5 10 8 22 8s22-3 22-8V26" fill="#2878D0" opacity="0.12" />
            <ellipse cx="46" cy="48" rx="22" ry="8" fill="#082B50" opacity="0.08" />
            <path
                d="M82 28l16 6v10c0 8-5.5 13-16 15-10.5-2-16-7-16-15V34l16-6z"
                fill="#08B7A5"
                opacity="0.2"
            />
            <path d="M82 36v16" stroke="#082B50" strokeOpacity="0.18" strokeWidth="2" />
        </g>
    ),
    monitoring: (
        <g>
            <circle cx="64" cy="40" r="28" fill="#08B7A5" opacity="0.12" />
            <circle cx="64" cy="40" r="18" fill="#2878D0" opacity="0.1" />
            <path
                d="M30 42c8-14 14-4 20-16 7 18 11 8 22 0 6 10 10 6 18 12"
                stroke="#08B7A5"
                strokeWidth="4"
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </g>
    ),
    marketing: (
        <g>
            <circle cx="88" cy="22" r="16" fill="#08B7A5" opacity="0.18" />
            <rect x="22" y="30" width="58" height="32" rx="10" fill="#2878D0" opacity="0.12" />
            <path d="M34 42h34" stroke="#082B50" strokeOpacity="0.16" strokeWidth="3" strokeLinecap="round" />
            <path d="M34 50h22" stroke="#08B7A5" strokeOpacity="0.45" strokeWidth="3" strokeLinecap="round" />
            <circle cx="92" cy="52" r="10" fill="#08B7A5" opacity="0.2" />
        </g>
    ),
    pricing: (
        <g>
            <rect x="28" y="20" width="52" height="40" rx="10" fill="#08B7A5" opacity="0.14" />
            <rect x="36" y="28" width="36" height="6" rx="3" fill="#082B50" opacity="0.1" />
            <rect x="36" y="40" width="24" height="6" rx="3" fill="#2878D0" opacity="0.2" />
            <circle cx="92" cy="26" r="14" fill="#2878D0" opacity="0.16" />
            <path d="M92 20v12M86 26h12" stroke="#082B50" strokeOpacity="0.25" strokeWidth="2" strokeLinecap="round" />
        </g>
    ),
    integrations: (
        <g>
            <rect x="22" y="24" width="36" height="28" rx="8" fill="#08B7A5" opacity="0.16" />
            <path d="M30 34h20M30 42h12" stroke="#082B50" strokeOpacity="0.18" strokeWidth="2.5" strokeLinecap="round" />
            <rect x="70" y="24" width="32" height="32" rx="8" fill="#2878D0" opacity="0.14" />
            <path d="M58 38h12" stroke="#08B7A5" strokeWidth="3" strokeLinecap="round" />
            <circle cx="98" cy="20" r="10" fill="#08B7A5" opacity="0.2" />
        </g>
    ),
    billing: (
        <g>
            <rect x="24" y="22" width="56" height="38" rx="10" fill="#2878D0" opacity="0.12" />
            <rect x="32" y="32" width="28" height="5" rx="2.5" fill="#082B50" opacity="0.12" />
            <rect x="32" y="42" width="18" height="5" rx="2.5" fill="#08B7A5" opacity="0.35" />
            <circle cx="92" cy="28" r="16" fill="#08B7A5" opacity="0.16" />
        </g>
    ),
    default: (
        <g>
            <circle cx="90" cy="22" r="16" fill="#08B7A5" opacity="0.2" />
            <rect x="22" y="30" width="52" height="32" rx="10" fill="#2878D0" opacity="0.12" />
            <path d="M34 42h28" stroke="#082B50" strokeOpacity="0.14" strokeWidth="3" strokeLinecap="round" />
        </g>
    ),
};

export default function ConsoleHeroDeco({ name = 'default' }) {
    return (
        <svg
            className="pointer-events-none h-24 w-32 shrink-0 sm:h-28 sm:w-40"
            viewBox="0 0 124 80"
            fill="none"
            aria-hidden="true"
        >
            {variants[name] ?? variants.default}
        </svg>
    );
}

export function decoFromUrl(url = '') {
    if (url.includes('/scale') || url.includes('/capacity')) return 'scale';
    if (url.includes('/backups')) return 'backups';
    if (url.includes('/monitoring') || url.includes('/ops')) return 'monitoring';
    if (url.includes('/marketing')) return 'marketing';
    if (url.includes('/pricing')) return 'pricing';
    if (url.includes('/integrations')) return 'integrations';
    if (url.includes('/billing') || url.includes('/payments')) return 'billing';
    return 'default';
}

export function titleIconFromUrl(url = '') {
    if (url.includes('/scale') || url.includes('/capacity')) return 'scale';
    if (url.includes('/backups')) return 'backups';
    if (url.includes('/monitoring')) return 'monitoring';
    if (url.includes('/ops')) return 'ops';
    if (url.includes('/marketing')) return 'marketing';
    if (url.includes('/pricing')) return 'pricing';
    if (url.includes('/integrations')) return 'integrations';
    if (url.includes('/billing')) return 'billing';
    if (url.includes('/payments')) return 'payments';
    if (url.includes('/schools')) return 'schools';
    if (url.includes('/registrations')) return 'registrations';
    return 'overview';
}
