import { FeatureIcon } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

const LABEL_KEYS = {
    schools: 'stats.schools',
    students: 'stats.students',
    teachers: 'stats.teachers',
    uptime: 'stats.uptime',
    compliance: 'stats.compliance',
};

const MOBILE_TONES = {
    schools: 'blue',
    students: 'green',
    teachers: 'purple',
    parents: 'orange',
    classes: 'pink',
    gambiaReady: 'indigo',
};

function StatGlyph({ name }) {
    const common = {
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: '1.8',
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': 'true',
        viewBox: '0 0 24 24',
        className: 'h-[22px] w-[22px]',
    };

    if (name === 'schools') {
        return (
            <svg {...common}>
                <path d="M4 20h16M6 20V9l6-4 6 4v11M10 20v-5h4v5" />
                <path d="M10 11h.01M14 11h.01" />
            </svg>
        );
    }
    if (name === 'students') {
        return (
            <svg {...common}>
                <circle cx="9" cy="8" r="3.1" />
                <path d="M3.6 19c.5-3 2.7-4.7 5.4-4.7s4.9 1.7 5.4 4.7" />
                <circle cx="17" cy="9" r="2.3" />
                <path d="M20.6 18.6c-.35-2.1-1.8-3.4-3.6-3.4" />
            </svg>
        );
    }
    if (name === 'teachers') {
        return (
            <svg {...common}>
                <circle cx="10" cy="8" r="3.1" />
                <path d="M4 19.2c.5-3 2.8-4.8 6-4.8s5.5 1.8 6 4.8" />
                <path d="M16.2 5.2 20 4.4v5.2l-3.8.8V8.6" />
                <path d="M16.2 8.2h3.2" />
            </svg>
        );
    }
    if (name === 'parents') {
        return (
            <svg {...common}>
                <circle cx="12" cy="8" r="3.2" />
                <path d="M5.2 19.2c.6-3.3 3.2-5.2 6.8-5.2s6.2 1.9 6.8 5.2" />
            </svg>
        );
    }
    if (name === 'classes') {
        return (
            <svg {...common}>
                <rect x="4" y="5.5" width="16" height="14" rx="2.2" />
                <path d="M8 3.8v3.2M16 3.8v3.2M4 10h16" />
                <path d="M12 13v4M10 15h4" />
            </svg>
        );
    }
    return (
        <svg {...common}>
            <path d="M12 21.2s7.2-3.6 7.2-9V5.4L12 3 4.8 5.4V12.2c0 5.4 7.2 9 7.2 9z" />
            <path d="m8.8 12 2.1 2.1 4.3-4.3" />
        </svg>
    );
}

export default function StatisticsSection({ stats = [], mode = 'live', live = null }) {
    const { t, locale } = useMarketingLocale();
    const formatCount = (value) =>
        new Intl.NumberFormat(locale === 'ar' ? 'ar' : locale === 'fr' ? 'fr' : 'en', {
            maximumFractionDigits: 0,
        }).format(Number(value) || 0);

    const items =
        (mode === 'live' || live) && live
            ? [
                  { key: 'schools', label: t('stats.live.schools'), value: formatCount(live.schools), icon: 'schools' },
                  { key: 'students', label: t('stats.live.students'), value: formatCount(live.students), icon: 'students' },
                  { key: 'teachers', label: t('stats.live.teachers'), value: formatCount(live.staff ?? live.teachers), icon: 'teachers' },
                  { key: 'parents', label: t('stats.live.parents'), value: formatCount(live.parents), icon: 'communication' },
                  { key: 'classes', label: t('stats.live.classes'), value: formatCount(live.classes), icon: 'timetable' },
              ]
            : mode === 'benefits'
              ? stats
              : (stats ?? []).map((item) => ({
                    ...item,
                    label: LABEL_KEYS[item.key] ? t(LABEL_KEYS[item.key]) : item.label,
                }));

    if (!items?.length) return null;

    const mobileItems = [
        ...items.map((item) => ({
            ...item,
            label: item.key && t(`stats.mobile.${item.key}`) !== `stats.mobile.${item.key}`
                ? t(`stats.mobile.${item.key}`)
                : item.label,
        })),
        ...(mode === 'live' || live
            ? [{ key: 'gambiaReady', label: t('stats.live.gambiaReady'), value: '✓', icon: 'compliance' }]
            : []),
    ];

    return (
        <>
            <section className="relative z-20 -mt-10 px-4 sm:-mt-12 sm:px-6 lg:px-8 max-md:hidden" aria-label={t('stats.aria')}>
                <div className="landing-container">
                    <div className="rounded-2xl bg-white p-4 shadow-card sm:p-6">
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 lg:gap-2">
                            {items.map((item) => (
                                <div
                                    key={item.key ?? item.label}
                                    className="flex flex-col items-center gap-2 px-2 py-2 text-center sm:flex-row sm:items-center sm:text-left lg:justify-center"
                                >
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy/10 text-navy">
                                        <FeatureIcon name={item.icon ?? 'schools'} className="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p className="text-lg font-extrabold text-navy sm:text-xl">{item.value}</p>
                                        <p className="text-xs font-medium text-brand-mute">{item.label}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <section className="lp-m-stats md:hidden" aria-label={t('stats.aria')}>
                <div className="lp-m-stats-panel">
                    {mobileItems.map((item) => (
                        <article
                            key={item.key ?? item.label}
                            className={`lp-m-stat lp-m-stat--${MOBILE_TONES[item.key] ?? 'blue'}`}
                        >
                            <span className="lp-m-stat-icon">
                                <StatGlyph name={item.key} />
                            </span>
                            <p className="lp-m-stat-value">{item.value}</p>
                            <p className="lp-m-stat-label">{item.label}</p>
                        </article>
                    ))}
                </div>
            </section>
        </>
    );
}
