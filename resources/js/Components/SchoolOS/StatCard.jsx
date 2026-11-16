const tones = {
    green: {
        icon: 'bg-emerald-50 text-emerald-600',
        accent: 'text-emerald-600',
        wash: 'kpi-card-green',
    },
    red: {
        icon: 'bg-red-50 text-red-600',
        accent: 'text-red-600',
        wash: 'kpi-card-red',
    },
    blue: {
        icon: 'bg-sky-50 text-sky-600',
        accent: 'text-sky-600',
        wash: 'kpi-card-blue',
    },
    purple: {
        icon: 'bg-violet-50 text-violet-600',
        accent: 'text-violet-600',
        wash: 'kpi-card-purple',
    },
    amber: {
        icon: 'bg-amber-50 text-amber-600',
        accent: 'text-amber-600',
        wash: 'kpi-card-amber',
    },
    orange: {
        icon: 'bg-amber-50 text-amber-600',
        accent: 'text-amber-600',
        wash: 'kpi-card-amber',
    },
    teal: {
        icon: 'bg-teal-50 text-teal-600',
        accent: 'text-teal-600',
        wash: 'kpi-card-teal',
    },
    navy: {
        icon: 'bg-navy-50 text-navy',
        accent: 'text-navy',
        wash: 'kpi-card-blue',
    },
};

function ToneIcon({ tone }) {
    const common = 'h-5 w-5';
    const icons = {
        green: 'M4.5 12.75l6 6 9-13.5',
        red: 'M19.5 12h-15M12 5.25v13.5',
        blue: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        purple: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
        amber: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        orange: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        teal: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
        navy: 'M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z',
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
        <div className={`kpi-card h-full ${palette.wash} ${className}`} role="group" aria-label={title}>
            <div className="relative z-[1] flex items-start justify-between gap-3">
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
