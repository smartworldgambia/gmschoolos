const tones = {
    teal: {
        icon: 'bg-teal-50 text-teal-600',
        accent: 'text-teal-600',
    },
    blue: {
        icon: 'bg-sky-50 text-sky-600',
        accent: 'text-sky-600',
    },
    purple: {
        icon: 'bg-violet-50 text-violet-600',
        accent: 'text-violet-600',
    },
    orange: {
        icon: 'bg-amber-50 text-amber-600',
        accent: 'text-amber-600',
    },
    navy: {
        icon: 'bg-navy-50 text-navy',
        accent: 'text-navy',
    },
};

function ToneIcon({ tone }) {
    const common = 'h-5 w-5';
    const icons = {
        navy: 'M4 19V5m0 14h16M8 15v4m4-8v8m4-5v5',
        blue: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z',
        orange: 'M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-14v2m0 14v2',
        purple: 'M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-14v2m0 14v2',
        teal: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM6 20a6 6 0 0112 0',
    };

    return (
        <svg className={common} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d={icons[tone] ?? icons.teal} />
        </svg>
    );
}

export default function StatCard({
    title,
    value,
    trend,
    tone = 'teal',
    icon,
    className = '',
}) {
    const palette = tones[tone] ?? tones.teal;
    const mark = icon ?? <ToneIcon tone={tone} />;

    return (
        <div className={`console-card h-full ${className}`} role="group" aria-label={title}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[13px] font-medium text-slate-500">{title}</p>
                    <p className="mt-2 truncate text-[28px] font-bold leading-none tracking-tight text-navy sm:text-[32px]">
                        {value ?? '—'}
                    </p>
                    {trend ? <p className={`mt-2 text-xs font-medium ${palette.accent}`}>{trend}</p> : null}
                </div>
                <div
                    className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ${palette.icon}`}
                    aria-hidden="true"
                >
                    {mark}
                </div>
            </div>
        </div>
    );
}
