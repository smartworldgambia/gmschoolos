import HeroSlider from '@/Components/Marketing/HeroSlider';
import { IconCheck, IconPlay } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { Link } from '@inertiajs/react';

export default function HeroSection({ registerUrl, demoUrl, heroSlides = [] }) {
    const { t } = useMarketingLocale();
    const trust = [
        t('hero.trust.easy'),
        t('hero.trust.secure'),
        t('hero.trust.cloud'),
        t('hero.trust.gambia'),
    ];

    return (
        <HeroSlider slides={heroSlides}>
            <p className="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-teal-200 backdrop-blur-md max-md:hidden">
                {t('hero.badge')}
            </p>

            <h1
                id="hero-heading"
                className="mt-4 text-3xl font-extrabold leading-[1.1] tracking-tight text-white drop-shadow-sm sm:text-4xl lg:text-[2.75rem] xl:text-5xl max-md:mt-0 max-md:text-[1.62rem] max-md:leading-[1.15] max-md:drop-shadow-none"
            >
                <span className="md:hidden">
                    <span className="block">{t('hero.mobile.line1')}</span>
                    <span className="lp-m-hero-underline">{t('hero.mobile.line2')}</span>
                </span>
                <span className="max-md:hidden">
                    {t('hero.titleBefore')}{' '}
                    <span className="text-brand-green">{t('hero.titleHighlight')}</span>
                </span>
            </h1>

            <p className="lp-m-hero-mods md:hidden">
                <span className="block whitespace-nowrap">{t('hero.mobile.modules')}</span>
                <span className="block whitespace-nowrap">{t('hero.mobile.modules2')}</span>
            </p>

            <ul className="lp-m-hero-checks md:hidden">
                <li>
                    <span className="lp-m-check" aria-hidden="true">
                        <IconCheck className="h-3 w-3" />
                    </span>
                    {t('hero.trust.cloud')}
                </li>
                <li>
                    <span className="lp-m-check" aria-hidden="true">
                        <IconCheck className="h-3 w-3" />
                    </span>
                    {t('hero.trust.gambia')}
                </li>
            </ul>

            <p className="mt-4 max-w-lg text-base leading-relaxed text-slate-200/95 sm:text-lg max-md:hidden">{t('hero.subtitle')}</p>

            <div className="mt-6 flex flex-wrap gap-3 max-md:hidden">
                <Link
                    href={registerUrl}
                    className="btn-cta rounded-xl bg-brand-green px-5 py-3 text-sm text-white shadow-lg shadow-emerald-950/30 hover:bg-emerald-600"
                >
                    {t('hero.ctaStart')}
                    <span aria-hidden="true">→</span>
                </Link>
                <a
                    href={demoUrl}
                    className="btn-cta rounded-xl border border-white/35 bg-white/10 px-5 py-3 text-sm text-white backdrop-blur-md hover:bg-white/20"
                >
                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/15">
                        <IconPlay className="h-3 w-3" />
                    </span>
                    {t('hero.ctaDemo')}
                </a>
            </div>

            <ul className="mt-6 grid grid-cols-2 gap-x-4 gap-y-2.5 sm:flex sm:flex-wrap sm:gap-x-5 max-md:hidden">
                {trust.map((item) => (
                    <li key={item} className="flex items-center gap-2 text-sm text-white/90">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-brand-green/25 text-brand-green ring-1 ring-brand-green/30">
                            <IconCheck className="h-3.5 w-3.5" />
                        </span>
                        {item}
                    </li>
                ))}
            </ul>
        </HeroSlider>
    );
}
