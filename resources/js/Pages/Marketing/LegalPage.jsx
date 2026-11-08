import BrandLogo from '@/Components/Marketing/BrandLogo';
import Footer from '@/Components/Marketing/Footer';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useLocale } from '@/i18n/LocaleProvider';
import { getLegalSections } from '@/i18n/legalDocuments';
import { Head, Link } from '@inertiajs/react';

export default function LegalPage({
    document = 'privacy',
    title = 'Legal',
    description = '',
    updatedAt = '',
    marketing = {},
    seo = {},
}) {
    const { locale, t } = useLocale();
    const sections = getLegalSections(locale, document);
    const localizedTitle =
        document === 'privacy'
            ? t('legal.privacyTitle')
            : document === 'terms'
              ? t('legal.termsTitle')
              : title;
    const pageTitle = seo.title ?? `${localizedTitle} — Gambia SchoolOS`;

    return (
        <>
            <Head title={pageTitle}>
                <meta head-key="description" name="description" content={seo.description ?? description} />
            </Head>

            <div className="min-h-screen bg-surface">
                <header className="border-b border-slate-200/80 bg-white">
                    <div className="landing-container flex h-16 items-center justify-between gap-3">
                        <BrandLogo />
                        <div className="flex items-center gap-3 text-sm font-semibold">
                            <LanguageSwitcher />
                            <Link href="/" className="text-navy/70 transition hover:text-navy">
                                {t('legal.home')}
                            </Link>
                            <Link
                                href="/register-school"
                                className="btn-cta rounded-lg bg-brand-green px-3 py-2 text-sm text-white hover:bg-emerald-600"
                            >
                                {t('legal.getStarted')}
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="landing-container py-10 sm:py-14">
                    <p className="landing-section-label">{t('legal.label')}</p>
                    <h1 className="mt-3 text-3xl font-extrabold text-navy sm:text-4xl">{localizedTitle}</h1>
                    {description ? (
                        <p className="mt-3 max-w-2xl text-sm text-brand-mute sm:text-base">{description}</p>
                    ) : null}
                    {updatedAt ? (
                        <p className="mt-2 text-xs font-medium text-brand-mute">
                            {t('legal.lastUpdated', { date: updatedAt })}
                        </p>
                    ) : null}

                    <div className="mt-10 max-w-3xl space-y-8">
                        {sections.map((section) => (
                            <section key={section.heading} aria-labelledby={section.heading}>
                                <h2 id={section.heading} className="text-lg font-bold text-navy">
                                    {section.heading}
                                </h2>
                                <div className="mt-3 space-y-3 text-sm leading-relaxed text-brand-ink sm:text-base">
                                    {section.body.map((paragraph) => (
                                        <p key={paragraph.slice(0, 48)}>{paragraph}</p>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>

                    <p className="mt-12 text-sm text-brand-mute">
                        {t('legal.alsoSee')}{' '}
                        {document === 'privacy' ? (
                            <Link href="/terms" className="font-semibold text-brand-blue hover:underline">
                                {t('legal.termsTitle')}
                            </Link>
                        ) : (
                            <Link href="/privacy" className="font-semibold text-brand-blue hover:underline">
                                {t('legal.privacyTitle')}
                            </Link>
                        )}
                        .
                    </p>
                </main>

                <Footer
                    contact={{
                        email: marketing.contact_email,
                        phone: marketing.phone,
                        phone_secondary: marketing.phone_secondary,
                        address: marketing.address,
                        website: marketing.website,
                        support_email: marketing.support_email,
                    }}
                    social={marketing.social ?? {}}
                    tagline={marketing.tagline}
                />
            </div>
        </>
    );
}
