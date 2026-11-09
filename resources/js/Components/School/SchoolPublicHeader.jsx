import { assetUrl } from '@/lib/assetUrl';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const stroke = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: '1.75',
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

function Ico({ children }) {
    return (
        <svg className="h-[1.15rem] w-[1.15rem]" viewBox="0 0 24 24" {...stroke} aria-hidden="true">
            {children}
        </svg>
    );
}

const NAV_ICONS = {
    home: (
        <Ico>
            <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1V10.5z" />
        </Ico>
    ),
    about: (
        <Ico>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5M12 8h.01" />
        </Ico>
    ),
    academics: (
        <Ico>
            <path d="M3 10 12 5l9 5-9 5-9-5z" />
            <path d="M7 12.5v4.2c2.2 1.5 7.8 1.5 10 0V12.5" />
        </Ico>
    ),
    admissions: (
        <Ico>
            <circle cx="9" cy="8" r="3.2" />
            <path d="M3.5 19c.4-3 2.6-4.8 5.5-4.8S14.1 16 14.5 19" />
            <path d="M17 9v6M14 12h6" />
        </Ico>
    ),
    news: (
        <Ico>
            <path d="M4 5h12v14H4z" />
            <path d="M16 8h4v11a2 2 0 0 1-2 2H6" />
            <path d="M7 9h6M7 13h6M7 17h4" />
        </Ico>
    ),
    contact: (
        <Ico>
            <path d="M4 13v-1a8 8 0 0 1 16 0v1" />
            <rect x="2.8" y="13" width="4.2" height="6.2" rx="1.6" />
            <rect x="17" y="13" width="4.2" height="6.2" rx="1.6" />
        </Ico>
    ),
};

function MenuGlyph({ open }) {
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

export function SchoolCrest({ name, primary, accent, className = 'h-10 w-10' }) {
    return (
        <span
            className={`relative flex shrink-0 items-center justify-center rounded-xl shadow-soft ${className}`}
            style={{ backgroundColor: primary }}
            aria-hidden="true"
        >
            <svg viewBox="0 0 40 40" className="h-[80%] w-[80%]">
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

function BrandMark({ school, name, tagline, primary, accent, onClick, showTagline = true }) {
    return (
        <a href="#home" onClick={onClick} className="flex min-w-0 shrink-0 items-center gap-2 md:max-w-[11rem] lg:max-w-[16rem]">
            {school?.logo_url || school?.logo_path ? (
                <img
                    src={assetUrl(school.logo_url ?? school.logo_path)}
                    alt=""
                    className="h-10 w-10 shrink-0 rounded-xl object-cover shadow-soft"
                />
            ) : (
                <SchoolCrest name={name} primary={primary} accent={accent} />
            )}
            <span className="min-w-0">
                <span className="block truncate text-sm font-extrabold tracking-tight text-navy">{name}</span>
                {showTagline ? (
                    <span className="hidden truncate text-[10px] font-medium text-slate-500 lg:block">{tagline}</span>
                ) : null}
            </span>
        </a>
    );
}

export default function SchoolPublicHeader({
    school,
    name,
    tagline,
    primary,
    accent,
    items,
    activeKey,
    onPickSection,
    loginUrl,
    admissionsUrl,
    applyLabel,
}) {
    const { t } = useLocale();
    const [menuOpen, setMenuOpen] = useState(false);
    const closeRef = useRef(null);
    const titleId = useId();

    useEffect(() => {
        if (!menuOpen) return undefined;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeRef.current?.focus();

        function onKey(e) {
            if (e.key === 'Escape') setMenuOpen(false);
        }
        document.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = prev;
            document.removeEventListener('keydown', onKey);
        };
    }, [menuOpen]);

    useEffect(() => {
        const mq = window.matchMedia('(min-width: 768px)');
        const sync = () => {
            if (mq.matches) setMenuOpen(false);
        };
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    }, []);

    function go(key) {
        onPickSection?.(key);
        setMenuOpen(false);
    }

    const drawer =
        menuOpen && typeof document !== 'undefined'
            ? createPortal(
                  <div className="md:hidden" role="presentation">
                      <button
                          type="button"
                          className="fixed inset-0 z-[60] bg-navy/45 backdrop-blur-[2px]"
                          aria-label={t('publicSite.menuClose')}
                          onClick={() => setMenuOpen(false)}
                      />
                      <div
                          id="public-site-menu"
                          role="dialog"
                          aria-modal="true"
                          aria-labelledby={titleId}
                          className="fixed inset-y-0 end-0 z-[70] flex w-[min(100%,22.5rem)] flex-col bg-white shadow-2xl"
                      >
                          <div className="h-1 w-full shrink-0" style={{ backgroundColor: primary }} />
                          <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-3.5">
                              <BrandMark
                                  school={school}
                                  name={name}
                                  tagline={tagline}
                                  primary={primary}
                                  accent={accent}
                                  showTagline={false}
                                  onClick={() => go('home')}
                              />
                              <button
                                  ref={closeRef}
                                  type="button"
                                  className="ms-auto inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-navy hover:bg-slate-200"
                                  aria-label={t('publicSite.menuClose')}
                                  onClick={() => setMenuOpen(false)}
                              >
                                  <MenuGlyph open />
                              </button>
                          </div>

                          <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-4" aria-labelledby={titleId}>
                              <p
                                  id={titleId}
                                  className="px-3 pb-2 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400"
                              >
                                  {t('publicSite.menu.explore')}
                              </p>
                              <ul className="flex flex-col gap-1">
                                  {items.map((item) => {
                                      const active = activeKey === item.key;
                                      return (
                                          <li key={item.key}>
                                              <a
                                                  href={item.href}
                                                  aria-current={active ? 'location' : undefined}
                                                  className={`flex items-center gap-3 rounded-2xl border-s-[3px] px-3 py-2.5 text-sm font-semibold transition ${
                                                      active ? 'text-navy' : 'border-transparent text-navy/80 hover:bg-surface'
                                                  }`}
                                                  style={
                                                      active
                                                          ? { backgroundColor: `${primary}14`, borderInlineStartColor: primary }
                                                          : undefined
                                                  }
                                                  onClick={() => go(item.key)}
                                              >
                                                  <span
                                                      className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
                                                          active ? 'text-white' : 'bg-surface text-navy'
                                                      }`}
                                                      style={active ? { backgroundColor: primary } : undefined}
                                                  >
                                                      {NAV_ICONS[item.key] ?? NAV_ICONS.home}
                                                  </span>
                                                  <span className="min-w-0 flex-1 truncate">{t(`publicSite.nav.${item.key}`)}</span>
                                                  <svg
                                                      className="h-4 w-4 shrink-0 text-slate-300 rtl:rotate-180"
                                                      viewBox="0 0 24 24"
                                                      fill="none"
                                                      stroke="currentColor"
                                                      strokeWidth="1.8"
                                                      aria-hidden="true"
                                                  >
                                                      <path strokeLinecap="round" strokeLinejoin="round" d="m9 6 6 6-6 6" />
                                                  </svg>
                                              </a>
                                          </li>
                                      );
                                  })}
                              </ul>
                          </nav>

                          <div className="mt-auto border-t border-slate-100 bg-surface/70 px-4 py-4">
                              <div className="flex flex-col gap-2">
                                  <Link
                                      href={loginUrl}
                                      className="btn-outline w-full !py-2.5 text-sm"
                                      onClick={() => setMenuOpen(false)}
                                  >
                                      {t('publicSite.login')}
                                  </Link>
                                  <Link
                                      href={admissionsUrl}
                                      className="btn-navy w-full !py-2.5 text-sm"
                                      style={{ backgroundColor: primary }}
                                      onClick={() => setMenuOpen(false)}
                                  >
                                      {applyLabel}
                                  </Link>
                              </div>
                          </div>
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <>
            <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 shadow-nav backdrop-blur-md">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-2 px-3 sm:h-[4.25rem] sm:gap-3 sm:px-6">
                    <BrandMark school={school} name={name} tagline={tagline} primary={primary} accent={accent} onClick={() => onPickSection?.('home')} />

                    <nav
                        className="ms-2 hidden min-w-0 flex-1 items-center justify-center md:flex"
                        aria-label={t('publicSite.navLabel')}
                    >
                        {items.map((item) => {
                            const active = activeKey === item.key;
                            return (
                                <a
                                    key={item.key}
                                    href={item.href}
                                    aria-current={active ? 'location' : undefined}
                                    onClick={() => onPickSection?.(item.key)}
                                    className={`relative shrink-0 whitespace-nowrap rounded-lg px-1.5 py-1.5 text-[12px] font-semibold transition hover:text-navy lg:px-2.5 lg:text-[13px] xl:px-3 ${
                                        active ? 'text-navy' : 'text-navy/70'
                                    }`}
                                >
                                    {t(`publicSite.nav.${item.key}`)}
                                    {active ? (
                                        <span
                                            className="absolute inset-x-1.5 -bottom-0.5 h-0.5 rounded-full lg:inset-x-2"
                                            style={{ backgroundColor: primary }}
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                </a>
                            );
                        })}
                    </nav>

                    <div className="ms-auto flex shrink-0 items-center gap-1 sm:gap-1.5 md:ms-0">
                        <LanguageSwitcher compact />
                        <Link href={loginUrl} className="btn-outline !hidden !px-3 !py-2 text-xs lg:!inline-flex">
                            {t('publicSite.login')}
                        </Link>
                        <Link
                            href={admissionsUrl}
                            className="btn-navy !hidden !px-3 !py-2 text-xs md:!inline-flex"
                            style={{ backgroundColor: primary }}
                        >
                            {applyLabel}
                        </Link>
                        <button
                            type="button"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-navy shadow-[0_8px_22px_rgba(8,38,91,0.08)] md:hidden"
                            aria-expanded={menuOpen}
                            aria-controls="public-site-menu"
                            aria-label={menuOpen ? t('publicSite.menuClose') : t('publicSite.menuOpen')}
                            onClick={() => setMenuOpen((open) => !open)}
                        >
                            <MenuGlyph open={menuOpen} />
                        </button>
                    </div>
                </div>
            </header>
            {drawer}
        </>
    );
}
