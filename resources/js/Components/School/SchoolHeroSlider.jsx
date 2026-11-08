import { assetUrl } from '@/lib/assetUrl';
import { useLocale } from '@/i18n/LocaleProvider';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

function ArrowIcon({ direction = 'prev' }) {
    return (
        <svg className="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            {direction === 'prev' ? (
                <path
                    fillRule="evenodd"
                    d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z"
                    clipRule="evenodd"
                />
            ) : (
                <path
                    fillRule="evenodd"
                    d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
                    clipRule="evenodd"
                />
            )}
        </svg>
    );
}

/**
 * Full-bleed school hero carousel.
 * Controls sit mid-end (not under the overlapping feature strip).
 */
export default function SchoolHeroSlider({
    slides = [],
    autoPlayMs = 4500,
    label,
    children,
}) {
    const { t, dir } = useLocale();
    const items =
        slides.length > 0
            ? slides
            : [
                  { image: '/images/marketing/hero-students.jpg', alt: '' },
                  { image: '/images/marketing/hero-classroom.jpg', alt: '' },
                  { image: '/images/marketing/hero-primary.jpg', alt: '' },
              ];
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(false);
    const regionId = useId();
    const touchStartX = useRef(null);
    const active = items[index] ?? items[0];
    const isRtl = dir === 'rtl';
    const multi = items.length > 1;

    const goTo = useCallback(
        (nextIndex) => {
            const len = items.length;
            setIndex(((nextIndex % len) + len) % len);
        },
        [items.length],
    );

    const next = useCallback(() => {
        setIndex((current) => (current + 1) % items.length);
    }, [items.length]);

    const prev = useCallback(() => {
        setIndex((current) => (current - 1 + items.length) % items.length);
    }, [items.length]);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const apply = () => setReducedMotion(mq.matches);
        apply();
        mq.addEventListener?.('change', apply);
        return () => mq.removeEventListener?.('change', apply);
    }, []);

    useEffect(() => {
        if (paused || reducedMotion || !multi) return undefined;
        const id = window.setInterval(() => {
            setIndex((current) => (current + 1) % items.length);
        }, autoPlayMs);
        return () => window.clearInterval(id);
    }, [paused, reducedMotion, multi, items.length, autoPlayMs]);

    function onKeyDown(e) {
        if (!multi) return;
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (isRtl) next();
            else prev();
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            if (isRtl) prev();
            else next();
        }
    }

    function onTouchStart(e) {
        touchStartX.current = e.changedTouches[0]?.clientX ?? null;
    }

    function onTouchEnd(e) {
        if (touchStartX.current == null || !multi) return;
        const delta = e.changedTouches[0].clientX - touchStartX.current;
        touchStartX.current = null;
        if (Math.abs(delta) < 48) return;
        if (delta > 0) {
            if (isRtl) next();
            else prev();
        } else if (isRtl) prev();
        else next();
    }

    return (
        <section
            id={regionId}
            role="region"
            aria-roledescription="carousel"
            aria-label={label || t('publicSite.hero.sliderLabel')}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            className="relative z-[1] min-h-[33.88rem] overflow-hidden outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-bright sm:min-h-[38.72rem] lg:min-h-[43.56rem]"
        >
            <div className="absolute inset-0" aria-hidden="true">
                {items.map((slide, i) => {
                    const src = slide.image || slide.src;
                    return (
                        <div
                            key={`${src}-${i}`}
                            className={`absolute inset-0 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
                                i === index ? 'opacity-100' : 'opacity-0'
                            }`}
                        >
                            <img
                                src={assetUrl(src)}
                                alt=""
                                className="h-full w-full object-cover object-[center_30%]"
                                width={1920}
                                height={1080}
                                loading={i === 0 ? 'eager' : 'lazy'}
                                fetchPriority={i === 0 ? 'high' : 'auto'}
                                draggable={false}
                            />
                        </div>
                    );
                })}
            </div>

            <div
                className="pointer-events-none absolute inset-0 bg-gradient-to-r from-navy/40 via-navy/15 to-transparent"
                aria-hidden="true"
            />

            <div className="relative z-10 mx-auto flex min-h-[33.88rem] max-w-6xl items-center px-4 py-12 pb-24 sm:min-h-[38.72rem] sm:px-6 sm:pb-28 lg:min-h-[43.56rem] lg:py-16">
                <div className="w-full max-w-xl">{children}</div>
            </div>

            {multi ? (
                <>
                    <div className="absolute end-3 top-1/2 z-30 flex -translate-y-1/2 flex-col gap-2 sm:end-5">
                        <button
                            type="button"
                            className="flex h-11 w-11 items-center justify-center rounded-full border border-white/35 bg-white/20 text-white shadow-soft backdrop-blur-md transition hover:bg-white hover:text-navy"
                            aria-controls={regionId}
                            aria-label={t('publicSite.hero.prev')}
                            onClick={isRtl ? next : prev}
                        >
                            <ArrowIcon direction={isRtl ? 'next' : 'prev'} />
                        </button>
                        <button
                            type="button"
                            className="flex h-11 w-11 items-center justify-center rounded-full border border-white/35 bg-white/20 text-white shadow-soft backdrop-blur-md transition hover:bg-white hover:text-navy"
                            aria-controls={regionId}
                            aria-label={t('publicSite.hero.next')}
                            onClick={isRtl ? prev : next}
                        >
                            <ArrowIcon direction={isRtl ? 'prev' : 'next'} />
                        </button>
                    </div>

                    <div className="absolute inset-x-0 bottom-16 z-30 sm:bottom-20">
                        <div className="mx-auto flex max-w-6xl items-center justify-center gap-2 px-4 sm:justify-start sm:px-6">
                            <div
                                className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-navy/35 px-3 py-2 backdrop-blur-md"
                                role="tablist"
                                aria-label={t('publicSite.hero.slideControls')}
                            >
                                {items.map((slide, i) => (
                                    <button
                                        key={`dot-${i}`}
                                        type="button"
                                        role="tab"
                                        aria-selected={i === index}
                                        aria-label={t('publicSite.hero.showSlide', { n: i + 1 })}
                                        className={`h-2 rounded-full transition-all duration-300 ${
                                            i === index
                                                ? 'w-8 bg-white'
                                                : 'w-2 bg-white/55 hover:bg-white/90'
                                        }`}
                                        onClick={() => goTo(i)}
                                    />
                                ))}
                                <span className="ms-1 hidden text-[11px] font-semibold text-white/80 sm:inline">
                                    {index + 1}/{items.length}
                                </span>
                            </div>
                        </div>
                    </div>
                </>
            ) : null}

            <p className="sr-only" aria-live="polite">
                {t('publicSite.hero.slideStatus', {
                    current: index + 1,
                    total: items.length,
                    alt: active?.alt || '',
                })}
            </p>
        </section>
    );
}
