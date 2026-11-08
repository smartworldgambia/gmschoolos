import { FeatureIcon } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

const LABEL_KEYS = {
    schools: 'stats.schools',
    students: 'stats.students',
    teachers: 'stats.teachers',
    uptime: 'stats.uptime',
    compliance: 'stats.compliance',
};

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

    return (
        <section className="relative z-20 -mt-10 px-4 sm:-mt-12 sm:px-6 lg:px-8" aria-label={t('stats.aria')}>
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
    );
}
