import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import { Link } from '@inertiajs/react';

/**
 * Trial / renewal countdown shown at the top of the school admin dashboard.
 * Urgent (<= 7 days) switches to an amber warning tone; layout is identical in EN / FR / AR.
 */
export default function SubscriptionBanner({ billing, billingUrl }) {
    const l = useBillingLabels();
    const { t } = l;

    if (!billing) return null;

    const live = ['trial', 'active'].includes(billing.status);
    const urgent = live && billing.days_remaining !== null && billing.days_remaining <= 7;
    const tone = !live
        ? 'border-red-200 bg-red-50 text-red-900'
        : urgent
          ? 'border-amber-200 bg-amber-50 text-amber-900'
          : 'border-blue-200 bg-blue-50 text-blue-900';

    let body;
    if (!billing.price_configured) {
        body = t('billing.banner.noPrice');
    } else if (billing.status === 'trial') {
        body = t('billing.banner.trialBody', { amount: billing.annual_amount_label });
    } else if (billing.status === 'active' && urgent) {
        body = t('billing.banner.renewBody', { amount: billing.annual_amount_label });
    } else if (billing.status === 'active') {
        body = t('billing.banner.activeBody', { date: l.date(billing.ends_at) });
    } else {
        body = t('billing.banner.renewBody', { amount: billing.annual_amount_label });
    }

    return (
        <section
            className={`mb-6 flex flex-col gap-3 rounded-2xl border px-5 py-4 sm:flex-row sm:items-center sm:justify-between ${tone}`}
            aria-label={t('billing.title')}
        >
            <div className="min-w-0">
                <p className="text-sm font-bold">{l.countdown(billing)}</p>
                <p className="mt-1 text-sm opacity-90">{body}</p>
            </div>
            {billingUrl ? (
                <Link href={billingUrl} className="btn-navy shrink-0 whitespace-nowrap text-sm">
                    {t('billing.banner.cta')}
                </Link>
            ) : null}
        </section>
    );
}
