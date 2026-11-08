export function WalletIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 8.5A2.5 2.5 0 015.5 6h13A2.5 2.5 0 0121 8.5v9a2.5 2.5 0 01-2.5 2.5h-13A2.5 2.5 0 013 17.5v-9z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M21 12h-3.2a1.8 1.8 0 000 3.6H21" />
        </svg>
    );
}

export function HandCoinsIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M14 8a3 3 0 11-6 0 3 3 0 016 0zM4.5 14.5c1.4-1.6 3.5-2.5 7.5-2.5s6.1.9 7.5 2.5" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M3 18.5c.8-1 2.2-1.6 4.2-1.6h.6M21 18.5c-.8-1-2.2-1.6-4.2-1.6h-.6" />
        </svg>
    );
}

export function AlertCircleIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 9v4m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z" />
        </svg>
    );
}

export function CoinsStackIcon({ className = 'h-6 w-6' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <ellipse cx="12" cy="7" rx="7" ry="3" strokeWidth={1.8} />
            <path strokeLinecap="round" strokeWidth={1.8} d="M5 7v4c0 1.7 3.1 3 7 3s7-1.3 7-3V7M5 11v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4" />
        </svg>
    );
}

export function CardPayIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <rect x="3" y="6" width="18" height="12" rx="2" strokeWidth={1.8} />
            <path strokeLinecap="round" strokeWidth={1.8} d="M3 10h18" />
        </svg>
    );
}

export function BoltMini({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M13 3L4 14h7l-1 7 9-11h-7l1-7z" />
        </svg>
    );
}

export function FileDownIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M12 3v10m0 0l-3.5-3.5M12 13l3.5-3.5M5 17.5V19a2 2 0 002 2h10a2 2 0 002-2v-1.5" />
        </svg>
    );
}

export function ReportDocIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 4h6l4 4v10a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z" />
            <path strokeLinecap="round" strokeWidth={1.8} d="M14 4v4h4M9 13h6M9 16h4" />
        </svg>
    );
}

export function WavePayIcon({ className = 'h-7 w-7' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 32 32" aria-hidden="true">
            <rect width="32" height="32" rx="10" fill="#E8F6FF" />
            <path d="M8 18c1.6-2 3.2-3 5.2-3 2.4 0 3.4 3 5.6 3 2 0 3.6-1 5.2-3" stroke="#0EA5E9" strokeWidth="2.2" strokeLinecap="round" />
            <path d="M8 22c1.6-2 3.2-3 5.2-3 2.4 0 3.4 3 5.6 3 2 0 3.6-1 5.2-3" stroke="#38BDF8" strokeWidth="2.2" strokeLinecap="round" />
        </svg>
    );
}

export function BankPayIcon({ className = 'h-7 w-7' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 32 32" aria-hidden="true">
            <rect width="32" height="32" rx="10" fill="#EEF2FF" />
            <path d="M8 14l8-5 8 5M10 14v7h12v-7M8 22h16" stroke="#4338CA" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M13 17v4M16 17v4M19 17v4" stroke="#4338CA" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}

export function OtherPayIcon({ className = 'h-7 w-7' }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 32 32" aria-hidden="true">
            <rect width="32" height="32" rx="10" fill="#ECFDF5" />
            <path d="M10 16h12M16 10v12" stroke="#059669" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

export function methodIcon(method) {
    if (method === 'wave') {
        return <WavePayIcon />;
    }
    if (method === 'bank_transfer') {
        return <BankPayIcon />;
    }

    return <OtherPayIcon />;
}

export function methodTone(method) {
    if (method === 'wave') {
        return 'fd-method-wave';
    }
    if (method === 'bank_transfer') {
        return 'fd-method-bank';
    }

    return 'fd-method-other';
}

export function FeesHeroArt({ label = 'Fees' }) {
    const text = String(label || 'Fees').slice(0, 10);

    return (
        <svg viewBox="0 0 260 150" className="h-[7.25rem] w-auto max-w-[16.5rem] sm:h-[8.5rem]" aria-hidden="true">
            <ellipse cx="130" cy="138" rx="96" ry="9" fill="#D7EFE8" opacity="0.9" />
            <path d="M18 92c10-28 4-46 22-56" fill="none" stroke="#16A34A" strokeWidth="5" strokeLinecap="round" />
            <ellipse cx="46" cy="42" rx="18" ry="13" fill="#22C55E" />
            <ellipse cx="32" cy="52" rx="12" ry="9" fill="#4ADE80" />
            <path d="M242 96c-10-30-2-48-22-58" fill="none" stroke="#15803D" strokeWidth="5" strokeLinecap="round" />
            <ellipse cx="214" cy="44" rx="18" ry="13" fill="#16A34A" />
            <ellipse cx="228" cy="54" rx="12" ry="9" fill="#4ADE80" />
            <rect x="42" y="78" width="58" height="42" rx="4" fill="#2563EB" />
            <rect x="48" y="84" width="46" height="6" rx="2" fill="#93C5FD" />
            <rect x="58" y="70" width="58" height="42" rx="4" fill="#F59E0B" />
            <rect x="64" y="76" width="46" height="6" rx="2" fill="#FDE68A" />
            <rect x="74" y="62" width="58" height="44" rx="4" fill="#0EA5E9" />
            <rect x="80" y="68" width="46" height="6" rx="2" fill="#BAE6FD" />
            <path d="M88 50l22-10 22 10v6l-22 10-22-10v-6z" fill="#0F172A" />
            <path d="M110 40v8" stroke="#F8FAFC" strokeWidth="2" strokeLinecap="round" />
            <circle cx="110" cy="50" r="2" fill="#FBBF24" />
            <rect x="148" y="36" width="78" height="96" rx="10" fill="#FFFFFF" stroke="#DCE6F2" strokeWidth="2" />
            <rect x="160" y="48" width="54" height="10" rx="3" fill="#08B7A5" />
            <text x="187" y="56" textAnchor="middle" fill="#FFFFFF" style={{ fontSize: '7px', fontWeight: 800 }}>
                {text}
            </text>
            <circle cx="166" cy="74" r="5" fill="#D1FAE5" />
            <path d="M163.5 74l1.6 1.6 3.4-3.4" stroke="#059669" strokeWidth="1.4" strokeLinecap="round" />
            <rect x="176" y="71" width="34" height="5" rx="2" fill="#E2E8F0" />
            <circle cx="166" cy="94" r="5" fill="#D1FAE5" />
            <path d="M163.5 94l1.6 1.6 3.4-3.4" stroke="#059669" strokeWidth="1.4" strokeLinecap="round" />
            <rect x="176" y="91" width="34" height="5" rx="2" fill="#E2E8F0" />
            <circle cx="166" cy="114" r="5" fill="#D1FAE5" />
            <path d="M163.5 114l1.6 1.6 3.4-3.4" stroke="#059669" strokeWidth="1.4" strokeLinecap="round" />
            <rect x="176" y="111" width="28" height="5" rx="2" fill="#E2E8F0" />
        </svg>
    );
}
