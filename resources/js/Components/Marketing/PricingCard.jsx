import { IconCheck } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';
import { Link } from '@inertiajs/react';

const FORM_CATEGORY = {
    nursery: 'Nursery',
    primary: 'Primary',
    basic_secondary: 'Basic Secondary',
    senior_secondary: 'Senior Secondary',
    combined: 'Combined',
    international: 'International',
    other: 'Other',
};

/** One annual price for one school type. No tiers, no monthly price. */
export default function PricingCard({ price, registerUrl }) {
    const { t } = useMarketingLocale();
    const href = `${registerUrl}?type=${encodeURIComponent(FORM_CATEGORY[price.type] ?? price.type)}`;

    return (
        <article className="group relative flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 transition duration-200 hover:border-brand-blue hover:bg-brand-blue/[0.04] sm:p-5">
            <h3 className="text-base font-extrabold text-navy transition-colors group-hover:text-brand-blue sm:text-lg">
                {t(`schoolType.${price.type}`)}
            </h3>

            <p className="mt-4 text-2xl font-extrabold tracking-tight text-navy transition-colors group-hover:text-brand-blue sm:text-3xl">
                <span dir="ltr">{price.label}</span>
            </p>
            <p className="mt-1 flex items-center gap-1.5 text-xs text-brand-mute">
                <span className="shrink-0 text-brand-green transition-colors group-hover:text-brand-blue">
                    <IconCheck className="h-3.5 w-3.5" />
                </span>
                {t('pricing.annual.perYear')}
            </p>

            <Link
                href={href}
                className="btn-cta mt-5 w-full rounded-xl border border-navy/20 bg-white px-3 py-2.5 text-center text-xs text-navy group-hover:border-brand-blue group-hover:bg-brand-blue group-hover:text-white focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2 sm:text-sm"
            >
                {t('pricing.annual.cta')}
            </Link>
        </article>
    );
}
