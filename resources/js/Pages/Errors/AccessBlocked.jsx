import { useLocale } from '@/i18n/LocaleProvider';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Shown (HTTP 403) when a school is suspended or its trial/subscription has ended.
 * School data is never deleted - access simply pauses until the account is reinstated.
 */
export default function AccessBlocked({ reason = 'suspended', school = {}, support = {}, billing = null }) {
    const { t } = useLocale();

    const known = ['suspended', 'trial_expired', 'subscription_expired', 'payment_overdue'];
    const key = known.includes(reason) ? reason : 'suspended';
    const title = t(`errors.blocked.title.${key}`);

    return (
        <GuestLayout>
            <Head title={title} />

            <p className="text-xs font-semibold uppercase tracking-wide text-brand-bright">
                {t('errors.blocked.eyebrow')}
            </p>
            <h1 className="mt-2 text-xl font-extrabold text-navy">{title}</h1>
            <p className="mt-3 text-sm leading-6 text-slate-600">
                {t('errors.blocked.body', { school: school?.name || 'SchoolOS' })}
            </p>
            <p className="mt-3 rounded-xl bg-teal-50 px-3.5 py-3 text-sm leading-6 text-teal-900">
                {t('errors.blocked.dataSafe')}
            </p>

            {billing && ['trial_expired', 'subscription_expired', 'payment_overdue'].includes(key) ? (
                <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-sm leading-6 text-slate-700">
                    {billing.price_configured ? (
                        <p className="font-semibold text-navy">
                            {t('errors.blocked.amountDue', { amount: billing.amount_due_label })}
                        </p>
                    ) : (
                        <p>{t('errors.blocked.noPrice')}</p>
                    )}
                    {billing.ended_on ? <p>{t('errors.blocked.endedOn', { date: billing.ended_on })}</p> : null}
                </div>
            ) : null}

            <div className="mt-6 flex flex-wrap items-center gap-3">
                {billing?.pay_url ? (
                    <Link href={billing.pay_url} className="btn-navy px-4 py-2.5 text-sm">
                        {t('errors.blocked.viewBilling')}
                    </Link>
                ) : null}
                {support?.email && (
                    <a href={`mailto:${support.email}`} className="btn-teal px-4 py-2.5 text-sm">
                        {t('errors.blocked.contact')}
                    </a>
                )}
                <Link href={route('logout')} method="post" as="button" className="btn-outline px-4 py-2.5 text-sm">
                    {t('errors.blocked.signOut')}
                </Link>
            </div>
        </GuestLayout>
    );
}
