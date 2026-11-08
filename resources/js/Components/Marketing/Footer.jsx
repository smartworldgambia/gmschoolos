import BrandLogo from '@/Components/Marketing/BrandLogo';
import { handleSectionNavClick } from '@/Components/Marketing/scrollToSection';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

function safeLinks(social = {}) {
    return Object.entries(social).filter(([, url]) => Boolean(url));
}

export default function Footer({ contact = {}, social = {}, tagline = '' }) {
    const { t } = useMarketingLocale();
    const year = new Date().getFullYear();
    const socialLinks = safeLinks(social);

    return (
        <footer className="bg-navy-600 text-slate-300 max-md:pb-4" aria-labelledby="footer-heading">
            <div id="contact" className="landing-container grid gap-8 py-8 sm:grid-cols-2 lg:grid-cols-4 sm:py-10">
                <div className="sm:col-span-2 lg:col-span-1">
                    <BrandLogo className="[&_span:last-child]:text-white" />
                    <p id="footer-heading" className="mt-3 text-sm leading-relaxed text-slate-400">
                        {tagline || t('footer.tagline')}
                    </p>
                    {socialLinks.length > 0 && (
                        <ul className="mt-3 flex flex-wrap gap-3">
                            {socialLinks.map(([name, url]) => (
                                <li key={name}>
                                    <a
                                        href={url}
                                        className="text-sm font-semibold capitalize text-white/80 hover:text-white"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {name}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <p className="text-sm font-bold text-white">{t('footer.product')}</p>
                    <ul className="mt-3 space-y-2 text-sm">
                        <li>
                            <a href="#features" className="hover:text-white" onClick={handleSectionNavClick}>
                                {t('nav.features')}
                            </a>
                        </li>
                        <li>
                            <a href="#solutions" className="hover:text-white" onClick={handleSectionNavClick}>
                                {t('nav.solutions')}
                            </a>
                        </li>
                        <li>
                            <a href="#pricing" className="hover:text-white" onClick={handleSectionNavClick}>
                                {t('nav.pricing')}
                            </a>
                        </li>
                        <li>
                            <a href="/register-school" className="hover:text-white">
                                {t('footer.register')}
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p className="text-sm font-bold text-white">{t('footer.resources')}</p>
                    <ul className="mt-3 space-y-2 text-sm">
                        <li>
                            <a href="/health" className="hover:text-white">
                                {t('footer.health')}
                            </a>
                        </li>
                        <li>
                            <a href="/login" className="hover:text-white">
                                {t('footer.login')}
                            </a>
                        </li>
                        <li>
                            <a href="/privacy" className="hover:text-white">
                                {t('footer.privacy')}
                            </a>
                        </li>
                        <li>
                            <a href="/terms" className="hover:text-white">
                                {t('footer.terms')}
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p className="text-sm font-bold text-white">{t('footer.contact')}</p>
                    <ul className="mt-3 space-y-2 text-sm">
                        {contact.address ? <li className="leading-relaxed text-slate-300">{contact.address}</li> : null}
                        {(contact.phone || contact.phone_secondary) && (
                            <li className="whitespace-nowrap">
                                {t('footer.tel')}:{' '}
                                {contact.phone ? (
                                    <a href={`tel:${contact.phone.replace(/\s+/g, '')}`} className="hover:text-white">
                                        {contact.phone}
                                    </a>
                                ) : null}
                                {contact.phone && contact.phone_secondary ? (
                                    <span className="text-slate-500"> / </span>
                                ) : null}
                                {contact.phone_secondary ? (
                                    <a
                                        href={`tel:${contact.phone_secondary.replace(/\s+/g, '')}`}
                                        className="hover:text-white"
                                    >
                                        {contact.phone_secondary}
                                    </a>
                                ) : null}
                            </li>
                        )}
                        {contact.email ? (
                            <li className="whitespace-nowrap">
                                {t('footer.email')}:{' '}
                                <a href={`mailto:${contact.email}`} className="hover:text-white">
                                    {contact.email}
                                </a>
                            </li>
                        ) : (
                            <li className="text-slate-500">{t('footer.emailMissing')}</li>
                        )}
                        <li className="whitespace-nowrap">
                            {t('footer.website')}:{' '}
                            <a
                                href={contact.website || 'https://gambiaschoolos.com'}
                                className="hover:text-white"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {(contact.website || 'https://gambiaschoolos.com').replace(/^https?:\/\//, '')}
                            </a>
                        </li>
                        {contact.support_email && contact.support_email !== contact.email ? (
                            <li className="whitespace-nowrap">
                                {t('footer.support')}:{' '}
                                <a href={`mailto:${contact.support_email}`} className="hover:text-white">
                                    {contact.support_email}
                                </a>
                            </li>
                        ) : null}
                    </ul>
                </div>
            </div>

            <div className="border-t border-white/10">
                <div className="landing-container py-3 text-xs text-slate-500">
                    <p>{t('footer.copyright', { year })}</p>
                </div>
            </div>
        </footer>
    );
}
