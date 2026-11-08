import { assetUrl } from '@/lib/assetUrl';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import SchoolAdmissionsSection from '@/Components/School/SchoolAdmissionsSection';
import SchoolContactSection from '@/Components/School/SchoolContactSection';
import SchoolHeroSlider from '@/Components/School/SchoolHeroSlider';
import { useLocale } from '@/i18n/LocaleProvider';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

function pickTranslated(t, key, fallback) {
    const value = t(key);
    return value === key ? fallback : value;
}

const NAV_KEYS = [
    { key: 'home', href: '#home' },
    { key: 'about', href: '#about' },
    { key: 'academics', href: '#academics' },
    { key: 'admissions', href: '#admissions' },
    { key: 'news', href: '#news' },
    { key: 'contact', href: '#contact' },
];

const DEFAULT_FEATURES = [
    { key: 'teachers', icon: 'teachers' },
    { key: 'facilities', icon: 'facilities' },
    { key: 'activities', icon: 'activities' },
    { key: 'safety', icon: 'safety' },
];

const LEVEL_IMAGE_FALLBACKS = [
    '/images/marketing/hero-primary.jpg',
    '/images/marketing/hero-classroom.jpg',
    '/images/marketing/hero-students.jpg',
    '/images/marketing/hero-classroom.jpg',
];

function FeatureIcon({ name }) {
    const common = {
        className: 'h-7 w-7',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.7,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': true,
    };

    switch (name) {
        case 'teachers':
            return (
                <svg {...common}>
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            );
        case 'facilities':
            return (
                <svg {...common}>
                    <path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6" />
                </svg>
            );
        case 'activities':
            return (
                <svg {...common}>
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            );
        case 'safety':
            return (
                <svg {...common}>
                    <path d="M12 3 4 7v5c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V7l-8-4Z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            );
        default:
            return (
                <svg {...common}>
                    <circle cx="12" cy="12" r="9" />
                </svg>
            );
    }
}

function SchoolCrest({ name, primary, accent }) {
    return (
        <span
            className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-soft"
            style={{ backgroundColor: primary }}
            aria-hidden="true"
        >
            <svg viewBox="0 0 40 40" className="h-8 w-8">
                <path
                    d="M20 4 8 10v10c0 7.2 4.8 12.2 12 13.5C27.2 32.2 32 27.2 32 20V10L20 4Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="1.8"
                />
                <path d="M20 8v22" stroke={accent || '#1683F8'} strokeWidth="1.4" />
                <path d="M12 14.5 20 19l8-4.5" stroke="#FFFFFF" strokeWidth="1.3" fill="none" opacity="0.85" />
                <text
                    x="20"
                    y="27"
                    textAnchor="middle"
                    fill="white"
                    fontSize="9"
                    fontWeight="700"
                    fontFamily="Plus Jakarta Sans, sans-serif"
                >
                    {String(name || 'S').charAt(0).toUpperCase()}
                </text>
            </svg>
        </span>
    );
}

function MenuIcon({ open }) {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            {open ? (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M6 18 18 6M6 6l12 12" />
            ) : (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M4 7h16M4 12h16M4 17h16" />
            )}
        </svg>
    );
}

export default function PublicSite({
    school,
    landing = {},
    levels = [],
    news = [],
    loginUrl = '/login',
    admissionsUrl = '/login',
    contactUrl = null,
}) {
    const { t } = useLocale();
    const [menuOpen, setMenuOpen] = useState(false);
    const [activeKey, setActiveKey] = useState('home');
    const spyLock = useRef(0); // ignore scroll-spy while a nav click is smooth-scrolling

    const name = school?.name ?? t('publicSite.fallbackName');
    const tagline = school?.tagline || t('publicSite.defaultTagline');
    const about = school?.about ?? '';
    const primary = school?.primary_color || '#1A2B56';
    const accent = school?.accent_color || '#1683F8';
    const template = landing?.template || school?.category || 'combined';

    const withName = (value) => String(value || '').replaceAll('{name}', name);

    const heroHeadline =
        landing?.hero_headline ||
        pickTranslated(t, `publicSite.hero.headline.${template}`, t('publicSite.hero.headline'));
    const heroBody = withName(
        landing?.hero_body ||
            pickTranslated(t, `publicSite.hero.body.${template}`, t('publicSite.hero.body')),
    );
    const heroSlides = useMemo(() => {
        const fromLanding = Array.isArray(landing?.hero_slides)
            ? landing.hero_slides
                  .map((slide) => ({
                      image: slide.image || slide.src || '',
                      alt: slide.alt || '',
                  }))
                  .filter((slide) => slide.image)
            : [];
        if (fromLanding.length > 0) return fromLanding;
        const primary = landing?.hero_image || school?.hero_image || '/images/marketing/hero-students.jpg';
        const defaults = [
            { image: primary, alt: name },
            { image: '/images/marketing/hero-classroom.jpg', alt: '' },
            { image: '/images/marketing/hero-primary.jpg', alt: '' },
        ];
        // Keep unique images so a custom primary that matches a default still yields multiple slides.
        const seen = new Set();
        return defaults.filter((slide) => {
            if (seen.has(slide.image)) return false;
            seen.add(slide.image);
            return true;
        });
    }, [landing?.hero_slides, landing?.hero_image, school?.hero_image, name]);
    const ctaPrimary = landing?.cta_primary || t('publicSite.cta.primary');
    const ctaSecondary = landing?.cta_secondary || t('publicSite.cta.secondary');
    const ctaApplyNow = t('publicSite.cta.applyNow');
    const levelsHeading = landing?.levels_heading || t('publicSite.levels.headingOur');
    const levelsIntro = withName(landing?.levels_intro || t('publicSite.levelsFallback', { name }));
    const aboutHeadline = landing?.about_headline || t('publicSite.about');
    // Admissions photo mosaic: the admin's own photos first (admissions image, then hero slides), padded with defaults.
    const admissionsImages = useMemo(() => {
        const pool = [
            landing?.admissions_image,
            ...[...heroSlides].reverse().map((slide) => slide?.image),
            '/images/marketing/hero-primary.jpg',
            '/images/marketing/hero-classroom.jpg',
            '/images/marketing/hero-students.jpg',
        ].filter((src) => typeof src === 'string' && src.trim() !== '');
        return [...new Set(pool)].slice(0, 3);
    }, [landing?.admissions_image, heroSlides]);
    const aboutImage = landing?.about_image || '/images/marketing/hero-classroom.jpg';
    const admissionsTitle = landing?.admissions_title || t('publicSite.admissions.title');
    const admissionsBody = withName(
        landing?.admissions_body || t('publicSite.admissions.body', { name }),
    );
    const admissionsSteps = (landing?.admissions_steps?.length
        ? landing.admissions_steps
        : [
              { title: t('publicSite.admissions.step1.title'), body: t('publicSite.admissions.step1.body') },
              { title: t('publicSite.admissions.step2.title'), body: t('publicSite.admissions.step2.body') },
              { title: t('publicSite.admissions.step3.title'), body: t('publicSite.admissions.step3.body') },
          ]
    ).filter((step) => step?.title);
    const contactBlurb = withName(landing?.contact_blurb || t('publicSite.contact.blurb', { name }));

    const features = (landing?.features?.length ? landing.features : DEFAULT_FEATURES).map((feature, index) => {
        const key = feature.key || feature.icon || `feature-${index}`;
        return {
            key,
            icon: feature.icon || key,
            title: feature.title || pickTranslated(t, `publicSite.features.${key}.title`, key),
            subtitle: feature.subtitle || pickTranslated(t, `publicSite.features.${key}.subtitle`, ''),
        };
    });

    const levelCards =
        landing?.level_cards?.length > 0
            ? landing.level_cards.map((card, index) => ({
                  id: card.code || card.name || index,
                  name:
                      card.name ||
                      pickTranslated(t, `publicSite.levelCard.${card.code}.name`, card.code || ''),
                  description:
                      card.description ||
                      pickTranslated(t, `publicSite.levelCard.${card.code}.description`, ''),
                  image: card.image || LEVEL_IMAGE_FALLBACKS[index % LEVEL_IMAGE_FALLBACKS.length],
              }))
            : levels.map((level, index) => ({
                  id: level.id ?? level.code ?? level.name,
                  name:
                      level.name ||
                      pickTranslated(t, `publicSite.levelCard.${level.code}.name`, level.code || ''),
                  description:
                      level.description ||
                      pickTranslated(t, `publicSite.levelCard.${level.code}.description`, ''),
                  image: LEVEL_IMAGE_FALLBACKS[index % LEVEL_IMAGE_FALLBACKS.length],
              }));

    function formatNewsDate(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    useEffect(() => {
        if (!menuOpen) return undefined;
        function onKey(e) {
            if (e.key === 'Escape') setMenuOpen(false);
        }
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [menuOpen]);

    function pickSection(key) {
        spyLock.current = Date.now() + 900;
        setActiveKey(key);
    }

    // Deep links (/#contact): the page is rendered client-side, so the browser's own
    // hash-scroll has already missed. Jump to the section once mounted (and again after
    // images settle layout).
    useEffect(() => {
        const id = window.location.hash.replace('#', '');
        if (!id) return undefined;
        const jump = () => document.getElementById(id)?.scrollIntoView({ behavior: 'auto', block: 'start' });
        const frame = window.requestAnimationFrame(jump);
        window.addEventListener('load', jump, { once: true });
        return () => {
            window.cancelAnimationFrame(frame);
            window.removeEventListener('load', jump);
        };
    }, []);

    // Scroll-spy: highlight the nav item for the section currently under the header.
    useEffect(() => {
        const ids = NAV_KEYS.map((item) => item.key).filter((key) => key !== 'home');
        let frame = 0;

        function update() {
            frame = 0;
            if (Date.now() < spyLock.current) return;
            const probe = window.scrollY + 120; // just under the sticky header
            let current = 'home';
            for (const id of ids) {
                const el = document.getElementById(id);
                if (el && el.getBoundingClientRect().top + window.scrollY <= probe) current = id;
            }
            // Short last sections can never reach the probe line — pin to the end of the page.
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                current = ids[ids.length - 1];
            }
            setActiveKey(current);
        }

        function onScroll() {
            if (!frame) frame = window.requestAnimationFrame(update);
        }

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, []);

    return (
        <>
            <Head title={name} />

            <div id="home" className="min-h-screen bg-surface font-sans text-brand-ink">
                <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 shadow-nav backdrop-blur-md">
                    <div className="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 sm:px-6 lg:gap-3">
                        <a href="#home" className="flex min-w-0 shrink items-center gap-2.5 lg:max-w-[14rem] xl:max-w-[16rem]">
                            {school?.logo_url || school?.logo_path ? (
                                <img
                                    src={assetUrl(school.logo_url ?? school.logo_path)}
                                    alt=""
                                    className="h-10 w-10 shrink-0 rounded-xl object-cover shadow-soft"
                                />
                            ) : (
                                <SchoolCrest name={name} primary={primary} accent={accent} />
                            )}
                            <div className="min-w-0">
                                <p className="truncate text-sm font-extrabold tracking-tight text-navy">
                                    {name}
                                </p>
                                <p className="truncate text-[10px] font-medium text-slate-500 sm:text-[11px]">
                                    {tagline}
                                </p>
                            </div>
                        </a>

                        <nav
                            className="ms-auto hidden min-w-0 flex-1 items-center justify-center gap-0.5 whitespace-nowrap xl:flex"
                            aria-label={t('publicSite.navLabel')}
                        >
                            {NAV_KEYS.map((item) => (
                                <a
                                    key={item.key}
                                    href={item.href}
                                    aria-current={activeKey === item.key ? 'location' : undefined}
                                    onClick={() => pickSection(item.key)}
                                    className={`rounded-lg px-2 py-1.5 text-[13px] font-semibold transition hover:bg-surface hover:text-navy ${
                                        activeKey === item.key ? 'bg-surface text-navy' : 'text-navy/80'
                                    }`}
                                >
                                    {t(`publicSite.nav.${item.key}`)}
                                </a>
                            ))}
                        </nav>

                        <div className="ms-auto flex shrink-0 items-center gap-1.5 xl:ms-0">
                            <LanguageSwitcher compact className="hidden sm:block" />
                            <Link
                                href={loginUrl}
                                className="btn-outline !hidden !px-3 !py-2 text-xs lg:!inline-flex"
                            >
                                {t('publicSite.login')}
                            </Link>
                            <Link
                                href={admissionsUrl}
                                className="btn-navy !hidden !px-3 !py-2 text-xs sm:!inline-flex"
                                style={{ backgroundColor: primary }}
                            >
                                {ctaApplyNow}
                            </Link>
                            <button
                                type="button"
                                className="inline-flex rounded-lg border border-slate-200 p-2 text-navy xl:hidden"
                                aria-expanded={menuOpen}
                                aria-controls="public-site-menu"
                                aria-label={menuOpen ? t('publicSite.menuClose') : t('publicSite.menuOpen')}
                                onClick={() => setMenuOpen((v) => !v)}
                            >
                                <MenuIcon open={menuOpen} />
                            </button>
                        </div>
                    </div>

                    {menuOpen ? (
                        <div
                            id="public-site-menu"
                            className="border-t border-slate-100 bg-white px-4 py-4 xl:hidden"
                        >
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <LanguageSwitcher />
                            </div>
                            <nav className="flex flex-col gap-1" aria-label={t('publicSite.navLabel')}>
                                {NAV_KEYS.map((item) => (
                                    <a
                                        key={item.key}
                                        href={item.href}
                                        aria-current={activeKey === item.key ? 'location' : undefined}
                                        className={`rounded-lg px-3 py-2.5 text-sm font-semibold text-navy hover:bg-surface ${
                                            activeKey === item.key ? 'bg-surface' : ''
                                        }`}
                                        onClick={() => {
                                            pickSection(item.key);
                                            setMenuOpen(false);
                                        }}
                                    >
                                        {t(`publicSite.nav.${item.key}`)}
                                    </a>
                                ))}
                            </nav>
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Link
                                    href={loginUrl}
                                    className="btn-outline !px-3 !py-2 text-xs"
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {t('publicSite.login')}
                                </Link>
                                <Link
                                    href={admissionsUrl}
                                    className="btn-navy !px-3 !py-2 text-xs"
                                    style={{ backgroundColor: primary }}
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {ctaApplyNow}
                                </Link>
                            </div>
                        </div>
                    ) : null}
                </header>

                <SchoolHeroSlider slides={heroSlides}>
                    <div className="rounded-3xl border border-white/30 bg-navy/35 p-6 text-white shadow-card backdrop-blur-md sm:p-8 lg:p-10">
                        <h1
                            id="school-hero-heading"
                            className="text-2xl font-extrabold leading-tight tracking-tight drop-shadow-sm sm:text-3xl lg:text-4xl"
                        >
                            {heroHeadline}
                        </h1>
                        <p className="mt-4 text-sm leading-relaxed text-white/95 sm:text-base">{heroBody}</p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <Link
                                href={admissionsUrl}
                                className="btn-cta rounded-xl px-5 py-3 text-sm text-white shadow-lg"
                                style={{ backgroundColor: accent }}
                            >
                                {ctaPrimary}
                            </Link>
                            <a
                                href="#about"
                                className="btn-cta rounded-xl border border-white/80 bg-white/15 px-5 py-3 text-sm text-white hover:bg-white/25"
                            >
                                {ctaSecondary}
                            </a>
                        </div>
                    </div>
                </SchoolHeroSlider>

                <section
                    id="student-life"
                    className="relative z-[2] -mt-6 bg-transparent px-4 sm:-mt-8 sm:px-6"
                    aria-label={t('publicSite.featuresHeading')}
                >
                    <div className="mx-auto grid max-w-6xl gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                        {features.map((feature) => (
                            <div
                                key={feature.key}
                                className="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-card ring-1 ring-slate-100"
                            >
                                <span
                                    className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-white"
                                    style={{ backgroundColor: primary }}
                                >
                                    <FeatureIcon name={feature.icon} />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="text-sm font-bold text-navy sm:text-base">{feature.title}</h2>
                                    {feature.subtitle ? (
                                        <p className="mt-1 text-xs leading-snug text-slate-500 sm:text-sm">
                                            {feature.subtitle}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="about" className="border-y border-slate-200 bg-white py-16 sm:py-20">
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-14">
                        <div className="order-2 overflow-hidden rounded-3xl shadow-card ring-1 ring-slate-100 lg:order-1">
                            <img
                                src={assetUrl(aboutImage)}
                                alt=""
                                className="aspect-[4/3] h-full w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <div className="order-1 lg:order-2">
                            <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand-bright">
                                {t('publicSite.section.about')}
                            </p>
                            <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">
                                {aboutHeadline}
                            </h2>
                            <div
                                className="mt-4 h-1 w-14 rounded-full"
                                style={{ backgroundColor: accent }}
                                aria-hidden="true"
                            />
                            {about ? (
                                <p className="mt-5 whitespace-pre-line text-base leading-relaxed text-slate-600">
                                    {about}
                                </p>
                            ) : (
                                <p className="mt-5 text-sm text-slate-500">{t('publicSite.aboutEmpty')}</p>
                            )}
                            <div className="mt-8 flex flex-wrap gap-3">
                                <a href="#admissions" className="btn-navy" style={{ backgroundColor: primary }}>
                                    {ctaApplyNow}
                                </a>
                                <a href="#contact" className="btn-outline">
                                    {t('publicSite.nav.contact')}
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="academics" className="bg-surface py-16 sm:py-20">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6">
                        <div className="mx-auto max-w-2xl text-center">
                            <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand-bright">
                                {t('publicSite.section.academics')}
                            </p>
                            <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">
                                {levelsHeading}
                            </h2>
                            <p className="mt-3 text-sm leading-relaxed text-slate-500 sm:text-base">{levelsIntro}</p>
                        </div>

                        {levelCards.length === 0 ? (
                            <div className="mt-10 rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center">
                                <p className="text-sm text-slate-500">{t('publicSite.levelsEmpty')}</p>
                            </div>
                        ) : (
                            <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {levelCards.map((level) => (
                                    <article
                                        key={level.id}
                                        className="group overflow-hidden rounded-2xl bg-white shadow-soft ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:shadow-card"
                                    >
                                        <div className="aspect-[4/3] overflow-hidden bg-surface-muted">
                                            <img
                                                src={assetUrl(level.image)}
                                                alt=""
                                                className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                                loading="lazy"
                                            />
                                        </div>
                                        <div className="p-4 sm:p-5">
                                            <h3 className="text-base font-bold text-navy">{level.name}</h3>
                                            {level.description ? (
                                                <p className="mt-2 text-sm leading-relaxed text-slate-500">
                                                    {level.description}
                                                </p>
                                            ) : null}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                <SchoolAdmissionsSection
                    school={school}
                    landing={landing}
                    name={name}
                    title={admissionsTitle}
                    body={admissionsBody}
                    steps={admissionsSteps}
                    ctaPrimary={ctaPrimary}
                    admissionsUrl={admissionsUrl}
                    images={admissionsImages}
                    primary={primary}
                    accent={accent}
                />

                <section id="news" className="border-y border-slate-200 bg-white py-16 sm:py-20">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand-bright">
                                    {t('publicSite.section.news')}
                                </p>
                                <h2 className="mt-3 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">
                                    {t('publicSite.news')}
                                </h2>
                            </div>
                            <p className="max-w-md text-sm text-slate-500">{t('publicSite.newsIntro')}</p>
                        </div>

                        {news.length === 0 ? (
                            <div className="mt-10 rounded-2xl border border-dashed border-slate-200 bg-surface px-6 py-12 text-center">
                                <p className="text-sm text-slate-500">{t('publicSite.newsEmpty')}</p>
                            </div>
                        ) : (
                            <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {news.slice(0, 6).map((post, index) => (
                                    <article
                                        key={post.id ?? post.title}
                                        className="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-100 bg-surface shadow-soft transition hover:-translate-y-0.5 hover:shadow-card"
                                    >
                                        <div className="aspect-[16/10] overflow-hidden bg-surface-muted">
                                            <img
                                                src={assetUrl(
                                                    post.image_url ||
                                                        LEVEL_IMAGE_FALLBACKS[index % LEVEL_IMAGE_FALLBACKS.length],
                                                )}
                                                alt=""
                                                className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                                loading="lazy"
                                            />
                                        </div>
                                        <div className="flex flex-1 flex-col p-5">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-brand-bright">
                                                {formatNewsDate(post.published_at ?? post.date)}
                                            </p>
                                            <h3 className="mt-2 text-lg font-bold text-navy">{post.title}</h3>
                                            {post.excerpt || post.body ? (
                                                <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-500">
                                                    {post.excerpt ?? post.body}
                                                </p>
                                            ) : null}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                <SchoolContactSection
                    school={school}
                    landing={landing}
                    contactUrl={contactUrl}
                    blurb={contactBlurb}
                    name={name}
                    primary={primary}
                    accent={accent}
                />

                <footer className="bg-navy-600 text-slate-300" aria-labelledby="footer-heading">
                    <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 sm:px-6 sm:py-12 lg:grid-cols-4">
                        <div className="sm:col-span-2 lg:col-span-1">
                            <div className="flex items-center gap-3">
                                {school?.logo_url || school?.logo_path ? (
                                    <img
                                        src={assetUrl(school.logo_url ?? school.logo_path)}
                                        alt=""
                                        className="h-10 w-10 rounded-xl object-cover ring-1 ring-white/15"
                                    />
                                ) : (
                                    <SchoolCrest name={name} primary={accent} accent="#ffffff" />
                                )}
                                <p className="text-base font-extrabold tracking-tight text-white">{name}</p>
                            </div>
                            <p id="footer-heading" className="mt-3 text-sm leading-relaxed text-slate-400">
                                {tagline}
                            </p>
                            <div className="mt-5 flex flex-wrap gap-2">
                                <Link href={loginUrl} className="btn-outline !border-white/25 !bg-transparent !px-3 !py-2 text-xs !text-white hover:!bg-white/10">
                                    {t('publicSite.login')}
                                </Link>
                                <Link
                                    href={admissionsUrl}
                                    className="btn-cta rounded-lg px-3 py-2 text-xs text-white"
                                    style={{ backgroundColor: accent }}
                                >
                                    {ctaApplyNow}
                                </Link>
                            </div>
                        </div>

                        <div>
                            <p className="text-sm font-bold text-white">{t('publicSite.footer.explore')}</p>
                            <ul className="mt-3 space-y-2 text-sm">
                                {NAV_KEYS.map((item) => (
                                    <li key={item.key}>
                                        <a href={item.href} className="hover:text-white">
                                            {t(`publicSite.nav.${item.key}`)}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <p className="text-sm font-bold text-white">{t('publicSite.footer.portal')}</p>
                            <ul className="mt-3 space-y-2 text-sm">
                                <li>
                                    <Link href={loginUrl} className="hover:text-white">
                                        {t('publicSite.login')}
                                    </Link>
                                </li>
                                <li>
                                    <Link href={admissionsUrl} className="hover:text-white">
                                        {ctaApplyNow}
                                    </Link>
                                </li>
                                <li>
                                    <a href="/privacy" className="hover:text-white">
                                        {t('publicSite.footer.privacy')}
                                    </a>
                                </li>
                                <li>
                                    <a href="/terms" className="hover:text-white">
                                        {t('publicSite.footer.terms')}
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p className="text-sm font-bold text-white">{t('publicSite.footer.contact')}</p>
                            <ul className="mt-3 space-y-2 text-sm">
                                {school?.address || school?.region ? (
                                    <li className="leading-relaxed text-slate-300">
                                        {[school.address, school.region].filter(Boolean).join(', ')}
                                    </li>
                                ) : null}
                                {school?.phone ? (
                                    <li className="whitespace-nowrap">
                                        {t('publicSite.footer.tel')}:{' '}
                                        <a
                                            href={`tel:${String(school.phone).replace(/\s+/g, '')}`}
                                            className="hover:text-white"
                                        >
                                            {school.phone}
                                        </a>
                                    </li>
                                ) : null}
                                {school?.email ? (
                                    <li className="whitespace-nowrap">
                                        {t('publicSite.footer.email')}:{' '}
                                        <a href={`mailto:${school.email}`} className="hover:text-white">
                                            {school.email}
                                        </a>
                                    </li>
                                ) : (
                                    <li className="text-slate-500">{t('publicSite.contact.empty')}</li>
                                )}
                            </ul>
                        </div>
                    </div>

                    <div className="border-t border-white/10">
                        <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p>{t('publicSite.footer.copyright', { year: new Date().getFullYear(), name })}</p>
                            <p>
                                {t('publicSite.powered')}{' '}
                                <span className="font-semibold text-white/80">Gambia SchoolOS</span>
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
