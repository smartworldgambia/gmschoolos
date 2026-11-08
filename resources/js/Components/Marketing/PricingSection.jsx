import PricingCard from '@/Components/Marketing/PricingCard';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

export default function PricingSection({ prices = [], registerUrl, trialDays = 30 }) {
    const { t } = useMarketingLocale();
    const currency = prices[0]?.currency ?? 'GMD';

    return (
        <section className="bg-surface pb-12 pt-2 sm:pb-16 sm:pt-3" aria-labelledby="pricing-heading">
            <div className="landing-container">
                <div id="pricing" className="mx-auto max-w-2xl scroll-mt-[4.75rem] text-center">
                    <p className="landing-section-label">{t('pricing.annual.label')}</p>
                    <h2 id="pricing-heading" className="mt-3 text-2xl font-extrabold text-navy sm:text-3xl">
                        {t('pricing.annual.title')}
                    </h2>
                    <p className="mt-3 text-sm text-brand-mute sm:text-base">
                        {t('pricing.annual.subtitle', { days: trialDays })}
                    </p>
                </div>

                {prices.length === 0 ? (
                    <p className="mt-10 text-center text-sm text-brand-mute">{t('pricing.annual.empty')}</p>
                ) : (
                    <>
                        <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-3 xl:gap-4">
                            {prices.map((price) => (
                                <PricingCard key={price.type} price={price} registerUrl={registerUrl} />
                            ))}
                        </div>
                        <p className="mt-6 text-center text-xs text-brand-mute">
                            {t('pricing.annual.note', { currency })}
                        </p>
                    </>
                )}
            </div>
        </section>
    );
}
