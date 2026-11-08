import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import { Head, Link } from '@inertiajs/react';

function Row({ label, children }) {
    return (
        <div className="flex items-start justify-between gap-6 border-b border-slate-100 py-2.5 text-sm">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-end font-semibold text-navy">{children}</dd>
        </div>
    );
}

/**
 * Printable receipt. Deliberately outside the app shell so print output is just the document.
 * Shown for verified (and refunded) payments only - the server returns 404 for anything else.
 */
export default function Receipt({ receipt, backUrl }) {
    const l = useBillingLabels();
    const { t } = l;

    return (
        <div className="min-h-screen bg-slate-100 px-4 py-8 print:bg-white print:p-0">
            <Head title={`${t('pay.receipt.title')} ${receipt.receipt_number ?? ''}`} />

            <div className="mx-auto mb-4 flex max-w-2xl items-center justify-between print:hidden">
                <Link href={backUrl} className="btn-outline whitespace-nowrap text-sm">
                    {t('pay.receipt.back')}
                </Link>
                <button type="button" onClick={() => window.print()} className="btn-teal whitespace-nowrap text-sm">
                    {t('pay.receipt.print')}
                </button>
            </div>

            <article className="mx-auto max-w-2xl rounded-2xl bg-white p-8 shadow-sm print:max-w-none print:rounded-none print:shadow-none">
                <header className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                        <h1 className="text-2xl font-bold text-navy">{t('pay.receipt.title')}</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {t('pay.receipt.number')}{' '}
                            <span dir="ltr" className="font-semibold text-navy">
                                {receipt.receipt_number}
                            </span>
                        </p>
                    </div>
                    <div className="text-end text-sm text-slate-600">
                        <p className="text-xs uppercase tracking-wide text-slate-400">{t('pay.receipt.issuedBy')}</p>
                        <p className="font-semibold text-navy">{receipt.issuer?.name}</p>
                        {receipt.issuer?.address ? <p>{receipt.issuer.address}</p> : null}
                        {receipt.issuer?.email ? <p dir="ltr">{receipt.issuer.email}</p> : null}
                    </div>
                </header>

                {receipt.status === 'refunded' ? (
                    <p className="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-900">{t('pay.receipt.refunded')}</p>
                ) : null}

                <dl className="mt-4">
                    <Row label={t('pay.receipt.school')}>{receipt.school}</Row>
                    <Row label={t('pay.receipt.schoolType')}>{l.schoolType(receipt.school_type)}</Row>
                    <Row label={t('pay.receipt.method')}>{l.method(receipt.method)}</Row>
                    <Row label={t('pay.receipt.reference')}>
                        <span dir="ltr">{receipt.reference}</span>
                    </Row>
                    {receipt.transaction_reference ? (
                        <Row label={t('pay.receipt.transaction')}>
                            <span dir="ltr">{receipt.transaction_reference}</span>
                        </Row>
                    ) : null}
                    <Row label={t('pay.receipt.amount')}>{receipt.amount_label}</Row>
                    {receipt.tax_label ? <Row label={t('pay.receipt.tax')}>{receipt.tax_label}</Row> : null}
                    <Row label={t('pay.receipt.total')}>
                        <span className="text-base">{receipt.total_label}</span>
                    </Row>
                    {receipt.charged_label ? <Row label={t('pay.receipt.charged')}>{receipt.charged_label}</Row> : null}
                    {receipt.fx_rate && receipt.charged_label ? (
                        <Row label={t('pay.receipt.rate')}>
                            <span dir="ltr">{`1 ${receipt.currency} = ${Number(receipt.fx_rate)}`}</span>
                        </Row>
                    ) : null}
                    <Row label={t('pay.receipt.paidOn')}>{l.date(receipt.paid_at)}</Row>
                    <Row label={t('pay.receipt.period')}>{`${l.date(receipt.period_start)} \u2192 ${l.date(receipt.period_end)}`}</Row>
                    <Row label={t('pay.receipt.verifiedBy')}>{receipt.verified_by ?? t('pay.receipt.system')}</Row>
                </dl>

                <p className="mt-6 text-xs leading-5 text-slate-500">{t('pay.receipt.footer')}</p>
            </article>
        </div>
    );
}
