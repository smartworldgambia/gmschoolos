import FeatureCard from '@/Components/Marketing/FeatureCard';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

export default function FeaturesGrid() {
    const { t } = useMarketingLocale();

    const features = [
        { title: t('features.management.title'), description: t('features.management.desc'), icon: 'management', tone: 'blue' },
        { title: t('features.lms.title'), description: t('features.lms.desc'), icon: 'lms', tone: 'green' },
        { title: t('features.exams.title'), description: t('features.exams.desc'), icon: 'exams', tone: 'red' },
        { title: t('features.fees.title'), description: t('features.fees.desc'), icon: 'fees', tone: 'yellow' },
        { title: t('features.timetable.title'), description: t('features.timetable.desc'), icon: 'timetable', tone: 'purple' },
        { title: t('features.reports.title'), description: t('features.reports.desc'), icon: 'reports', tone: 'indigo' },
        { title: t('features.comms.title'), description: t('features.comms.desc'), icon: 'communication', tone: 'teal' },
        { title: t('features.more.title'), description: t('features.more.desc'), icon: 'more', tone: 'slate' },
    ];

    return (
        <section
            id="features"
            className="bg-surface pb-16 pt-10 sm:pb-20 sm:pt-12 max-md:bg-transparent max-md:pb-10 max-md:pt-2"
            aria-label={t('features.label')}
        >
            <div className="landing-container">
                <div className="lp-m-intro md:hidden">
                    <p className="lp-m-intro-eyebrow">{t('features.label')}</p>
                    <h2 id="features-heading-mobile" className="lp-m-intro-title">
                        {t('features.mobileTitle')}
                    </h2>
                    <p className="lp-m-intro-copy">{t('features.mobileSubtitle')}</p>
                </div>

                <div id="features-desktop" className="mx-auto max-w-2xl text-center max-md:hidden">
                    <p className="landing-section-label">{t('features.label')}</p>
                    <h2 id="features-heading" className="mt-3 text-2xl font-extrabold text-navy sm:text-3xl">
                        {t('features.title')}
                    </h2>
                    <p className="mt-3 text-sm text-brand-mute sm:text-base">{t('features.subtitle')}</p>
                </div>

                <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 max-md:mt-6">
                    {features.map((feature) => (
                        <FeatureCard key={feature.title} {...feature} />
                    ))}
                </div>
            </div>
        </section>
    );
}
