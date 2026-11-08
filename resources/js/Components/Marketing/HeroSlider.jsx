import { assetUrl } from '@/lib/assetUrl';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

const DEFAULT_SLIDES = [
    {
        src: '/images/marketing/hero-students.jpg',
        alt: 'Secondary students in Gambian school uniforms with a tablet',
        caption: 'Secondary learners',
        focus: 'object-[68%_center]',
    },
    {
        src: '/images/marketing/hero-classroom.jpg',
        alt: 'Teacher supporting students with a laptop in class',
        caption: 'Teaching & LMS',
        focus: 'object-[60%_center]',
    },
    {
        src: '/images/marketing/hero-primary.jpg',
        alt: 'Primary school children smiling in a Gambian school courtyard',
        caption: 'Primary learners',
        focus: 'object-[55%_center]',
    },
];

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

export default function HeroSlider({ slides = DEFAULT_SLIDES, autoPlayMs = 6000, children }) {
    const items = slides.length
        ? slides.map((slide, i) => ({
              focus: DEFAULT_SLIDES[i % DEFAULT_SLIDES.length]?.focus ?? 'object-center',
              ...slide,
          }))
        : DEFAULT_SLIDES;

    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const regionId = useId();
    const touchStartX = useRef(null);
    const active = items[index] ?? items[0];

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
        if (paused || items.length < 2) return undefined;
        const id = window.setInterval(() => {
            setIndex((current) => (current + 1) % items.length);
        }, autoPlayMs);
        return () => window.clearInterval(id);
    }, [paused, items.length, autoPlayMs]);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (mq.matches) setPaused(true);
        const onChange = () => setPaused(mq.matches);
        mq.addEventListener?.('change', onChange);
        return () => mq.removeEventListener?.('change', onChange);
    }, []);

    function onKeyDown(e) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prev();
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            next();
        }
    }

    function onTouchStart(e) {
        touchStartX.current = e.changedTouches[0]?.clientX ?? null;
    }

    function onTouchEnd(e) {
        if (touchStartX.current == null) return;
        const delta = e.changedTouches[0].clientX - touchStartX.current;
        touchStartX.current = null;
        if (Math.abs(delta) < 48) return;
        if (delta > 0) prev();
        else next();
    }

    return (
        <section
            id={regionId}
            role="region"
            aria-roledescription="carousel"
            aria-label="Gambia SchoolOS hero"
            aria-labelledby="hero-heading"
            tabIndex={0}
            onKeyDown={onKeyDown}
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocusCapture={() => setPaused(true)}
            onBlurCapture={(e) => {
                if (!e.currentTarget.contains(e.relatedTarget)) setPaused(false);
            }}
            className="lp-m-hero relative isolate min-h-[26rem] overflow-hidden bg-navy text-white outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green sm:min-h-[28rem] lg:min-h-[32rem] xl:min-h-[34rem] max-md:min-h-[19.5rem]"
        >
            {/* Full-bleed slides */}
            <div className="absolute inset-0" aria-hidden="true">
                {items.map((slide, i) => (
                    <div
                        key={slide.src}
                        className={`absolute inset-0 transition-opacity duration-[900ms] ease-out motion-reduce:transition-none ${
                            i === index ? 'opacity-100' : 'opacity-0'
                        }`}
                    >
                        <img
                            src={assetUrl(slide.src)}
                            alt=""
                            className={`h-full w-full object-cover ${slide.focus ?? 'object-center'}`}
                            style={
                                i === index
                                    ? { animation: 'heroKen 7s ease-out forwards' }
                                    : { transform: 'scale(1.08)' }
                            }
                            width={1920}
                            height={1080}
                            loading={i === 0 ? 'eager' : 'lazy'}
                            fetchPriority={i === 0 ? 'high' : 'auto'}
                            draggable={false}
                        />
                    </div>
                ))}
            </div>

            {/* Cinematic overlays: keep copy readable, let photography dominate the right */}
            <div
                className="pointer-events-none absolute inset-0 max-md:hidden"
                aria-hidden="true"
                style={{
                    background: `
                        linear-gradient(100deg,
                            rgba(6, 31, 67, 0.97) 0%,
                            rgba(8, 43, 85, 0.92) 26%,
                            rgba(8, 43, 85, 0.62) 46%,
                            rgba(8, 43, 85, 0.28) 64%,
                            rgba(8, 43, 85, 0.12) 82%,
                            rgba(8, 43, 85, 0.18) 100%
                        ),
                        linear-gradient(to top, rgba(6, 31, 67, 0.82) 0%, rgba(6, 31, 67, 0.25) 28%, transparent 48%),
                        linear-gradient(to bottom, rgba(6, 31, 67, 0.35) 0%, transparent 12%)
                    `,
                }}
            />
            <div
                className="pointer-events-none absolute inset-0 max-md:hidden"
                aria-hidden="true"
                style={{
                    background:
                        'radial-gradient(ellipse 55% 50% at 16% 42%, rgba(16,185,165,0.22), transparent 60%), radial-gradient(ellipse 45% 40% at 90% 30%, rgba(22,131,248,0.18), transparent 55%)',
                }}
            />
            <div className="lp-m-hero-wash pointer-events-none absolute inset-0 md:hidden" aria-hidden="true" />

            {/* Overlay content */}
            <div className="landing-container relative z-10 flex min-h-[26rem] flex-col justify-center py-10 sm:min-h-[28rem] sm:py-12 lg:min-h-[32rem] lg:py-14 xl:min-h-[34rem] max-md:min-h-[19.5rem] max-md:justify-start max-md:pt-4 max-md:pb-[7.4rem]">
                <div className="max-w-xl max-md:max-w-[18rem]">{children}</div>

                {/* Slider chrome */}
                {items.length > 1 && (
                    <div className="lp-m-chrome mt-8 flex items-center justify-between gap-4 sm:mt-9">
                        <div className="flex items-center gap-3 max-md:gap-2" role="tablist" aria-label="Slide controls">
                            {items.map((slide, i) => (
                                <button
                                    key={slide.src}
                                    type="button"
                                    role="tab"
                                    aria-selected={i === index}
                                    aria-label={`Show slide ${i + 1}: ${slide.caption ?? slide.alt}`}
                                    className={`lp-m-dot h-1.5 rounded-full transition-all duration-300 ${
                                        i === index
                                            ? 'is-on w-10 bg-brand-green shadow-[0_0_12px_rgba(8,168,90,0.55)]'
                                            : 'w-1.5 bg-white/45 hover:bg-white/80'
                                    }`}
                                    onClick={() => goTo(i)}
                                />
                            ))}
                            <span className="ml-2 hidden text-xs font-medium text-white/70 sm:inline max-md:!hidden">
                                {String(index + 1).padStart(2, '0')} / {String(items.length).padStart(2, '0')}
                            </span>
                        </div>

                        <div className="flex items-center gap-2 max-md:hidden">
                            <button
                                type="button"
                                className="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-md transition hover:bg-white hover:text-navy focus-visible:ring-2 focus-visible:ring-brand-green"
                                aria-controls={regionId}
                                aria-label="Previous slide"
                                onClick={prev}
                            >
                                <ArrowIcon direction="prev" />
                            </button>
                            <button
                                type="button"
                                className="flex h-11 w-11 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white backdrop-blur-md transition hover:bg-white hover:text-navy focus-visible:ring-2 focus-visible:ring-brand-green"
                                aria-controls={regionId}
                                aria-label="Next slide"
                                onClick={next}
                            >
                                <ArrowIcon direction="next" />
                            </button>
                        </div>
                    </div>
                )}
            </div>

            <svg className="lp-m-ribbon md:hidden" viewBox="0 0 240 72" preserveAspectRatio="none" aria-hidden="true">
                <path d="M8 58 C 70 18, 140 28, 240 10" fill="none" stroke="#CE1126" strokeWidth="9" strokeLinecap="round" />
                <path d="M8 50 C 70 10, 140 20, 240 2" fill="none" stroke="#0C47A1" strokeWidth="7" strokeLinecap="round" />
                <path d="M8 44 C 70 4, 140 14, 240 -4" fill="none" stroke="#F9F9F9" strokeWidth="4" strokeLinecap="round" />
                <path d="M8 38 C 70 -2, 140 8, 240 -10" fill="none" stroke="#3A7A28" strokeWidth="7" strokeLinecap="round" />
            </svg>

            <p className="sr-only" aria-live="polite">
                Slide {index + 1} of {items.length}
                {active?.alt ? `: ${active.alt}` : ''}
            </p>

            <style>{`
                @keyframes heroKen {
                    from { transform: scale(1.08); }
                    to { transform: scale(1); }
                }
                @media (prefers-reduced-motion: reduce) {
                    @keyframes heroKen {
                        from { transform: none; }
                        to { transform: none; }
                    }
                }
            `}</style>
        </section>
    );
}
