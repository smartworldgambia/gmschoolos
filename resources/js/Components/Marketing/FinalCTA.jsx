import { IconPlay } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { Link } from '@inertiajs/react';

export default function FinalCTA({ registerUrl, demoUrl }) {
    const { t } = useMarketingLocale();

    return (
        <section className="relative overflow-hidden bg-navy py-5 text-white sm:py-6" aria-labelledby="final-cta-heading">
            <div
                className="pointer-events-none absolute inset-0"
                aria-hidden="true"
                style={{
                    background:
                        'radial-gradient(ellipse 60% 80% at 0% 50%, rgba(16,185,165,0.25), transparent 55%), radial-gradient(ellipse 50% 70% at 100% 40%, rgba(22,131,248,0.3), transparent 50%)',
                }}
            />
            <div className="landing-container relative flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div className="max-w-xl">
                    <h2 id="final-cta-heading" className="text-xl font-extrabold leading-tight sm:text-2xl">
                        {t('cta.title')}
                    </h2>
                    <p className="mt-1 text-sm text-slate-300">{t('cta.subtitle')}</p>
                </div>
                <div className="flex flex-wrap gap-2.5">
                    <Link
                        href={registerUrl}
                        className="btn-cta rounded-lg bg-brand-green px-4 py-2.5 text-sm text-white hover:bg-emerald-600"
                    >
                        {t('cta.start')}
                        <span aria-hidden="true">→</span>
                    </Link>
                    <a
                        href={demoUrl}
                        className="btn-cta rounded-lg border border-white/30 bg-white/5 px-4 py-2.5 text-sm text-white hover:bg-white/10"
                    >
                        <IconPlay className="h-3.5 w-3.5" />
                        {t('cta.demo')}
                    </a>
                </div>
            </div>
        </section>
    );
}
