import { Link } from '@inertiajs/react';

export default function BrandLogo({
    href = '/',
    className = '',
    compact = false,
    label = 'Gambia SchoolOS',
    ariaLabel,
    tone = 'default',
}) {
    const onDark = tone === 'onDark';

    return (
        <Link
            href={href}
            className={`inline-flex items-center gap-2.5 focus-visible:rounded-lg ${className}`}
            aria-label={ariaLabel || label}
        >
            <span
                className={
                    onDark
                        ? 'relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/12 ring-1 ring-white/25 shadow-soft'
                        : 'relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-navy shadow-soft'
                }
            >
                <svg viewBox="0 0 32 32" className="h-5 w-5" aria-hidden="true">
                    <path
                        d="M6 10.5 16 5l10 5.5V22L16 27.5 6 22V10.5Z"
                        fill="none"
                        stroke="white"
                        strokeWidth="1.6"
                    />
                    <path d="M16 5v22.5" stroke="#10B9A5" strokeWidth="1.4" />
                    <path d="M6 10.5 16 16l10-5.5" stroke="#1683F8" strokeWidth="1.4" fill="none" />
                    <path d="M11 14.2 16 17l5-2.8" stroke="#08A85A" strokeWidth="1.2" fill="none" />
                </svg>
            </span>
            {!compact && (
                <span
                    className={
                        onDark
                            ? 'text-[15px] font-extrabold tracking-tight text-white sm:text-base'
                            : 'text-[15px] font-extrabold tracking-tight text-navy sm:text-base'
                    }
                >
                    {label}
                </span>
            )}
        </Link>
    );
}
