import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import InputError from '@/Components/InputError';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Fact({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 break-words text-sm font-semibold text-navy">{children}</dd>
        </div>
    );
}

const today = () => new Date().toISOString().slice(0, 10);

function Field({ label, error, children }) {
    return (
        <label className="block text-sm text-slate-600">
            {label}
            {children}
            <InputError message={error} className="mt-1" />
        </label>
    );
}

const inputClass = 'ui-input mt-1';

function CashForm({ payment, l }) {
    const { t } = l;
    const form = useForm({ amount: payment.total, paid_at: today(), receipt_number: '', notes: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('platform.payments.cash.confirm', payment.id), { preserveScroll: true });
            }}
            className="console-card"
        >
            <h2 className="text-base font-semibold text-navy">{t('platformPayments.cash.title')}</h2>
            <p className="mt-1 text-xs text-slate-500">{t('platformPayments.show.fullOnly', { total: payment.total_label })}</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Field label={t('platformPayments.cash.amount')} error={form.errors.amount}>
                    <input type="number" min="1" required className={inputClass} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                </Field>
                <Field label={t('platformPayments.cash.paidAt')} error={form.errors.paid_at}>
                    <input type="date" required max={today()} className={inputClass} value={form.data.paid_at} onChange={(e) => form.setData('paid_at', e.target.value)} />
                </Field>
                <Field label={t('platformPayments.cash.receipt')} error={form.errors.receipt_number}>
                    <input className={inputClass} maxLength={60} value={form.data.receipt_number} onChange={(e) => form.setData('receipt_number', e.target.value)} />
                </Field>
                <Field label={t('platformPayments.cash.notes')} error={form.errors.notes}>
                    <input className={inputClass} maxLength={1000} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </Field>
            </div>
            <div className="mt-4">
                <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                    {t('platformPayments.cash.confirm')}
                </button>
            </div>
        </form>
    );
}

function BankForm({ payment, l }) {
    const { t } = l;
    const form = useForm({
        amount: payment.total,
        transaction_reference: '',
        paid_at: today(),
        notes: '',
        confirm_funds: false,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('platform.payments.bank.approve', payment.id), { preserveScroll: true });
            }}
            className="console-card"
        >
            <h2 className="text-base font-semibold text-navy">{t('platformPayments.bank.title')}</h2>
            <p className="mt-1 text-xs text-slate-500">{t('platformPayments.show.fullOnly', { total: payment.total_label })}</p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Field label={t('platformPayments.bank.amount')} error={form.errors.amount}>
                    <input type="number" min="1" required className={inputClass} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                </Field>
                <Field label={t('platformPayments.bank.paidAt')} error={form.errors.paid_at}>
                    <input type="date" required max={today()} className={inputClass} value={form.data.paid_at} onChange={(e) => form.setData('paid_at', e.target.value)} />
                </Field>
                <div className="sm:col-span-2">
                    <Field label={t('platformPayments.bank.txn')} error={form.errors.transaction_reference}>
                        <input required className={inputClass} maxLength={120} value={form.data.transaction_reference} onChange={(e) => form.setData('transaction_reference', e.target.value)} />
                    </Field>
                </div>
                <div className="sm:col-span-2">
                    <Field label={t('platformPayments.bank.notes')} error={form.errors.notes}>
                        <input className={inputClass} maxLength={1000} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                </div>
            </div>
            <label className="mt-4 flex items-start gap-2 text-sm text-navy">
                <input type="checkbox" className="mt-0.5 rounded border-slate-300 text-teal focus:ring-teal" checked={form.data.confirm_funds} onChange={(e) => form.setData('confirm_funds', e.target.checked)} />
                <span>{t('platformPayments.bank.confirmFunds')}</span>
            </label>
            <InputError message={form.errors.confirm_funds} className="mt-1" />
            <div className="mt-4">
                <button type="submit" disabled={form.processing || !form.data.confirm_funds} className="btn-teal whitespace-nowrap text-sm disabled:opacity-50">
                    {t('platformPayments.bank.approve')}
                </button>
            </div>
        </form>
    );
}

function RejectForm({ payment, l }) {
    const { t } = l;
    const form = useForm({ reason: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('platform.payments.reject', payment.id), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="console-card"
        >
            <h2 className="text-base font-semibold text-navy">{t('platformPayments.reject.title')}</h2>
            <div className="mt-3">
                <Field label={t('platformPayments.reject.reason')} error={form.errors.reason}>
                    <textarea required minLength={5} maxLength={500} rows={3} className={inputClass} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                </Field>
            </div>
            <div className="mt-4">
                <button type="submit" disabled={form.processing} className="btn-outline whitespace-nowrap text-sm">
                    {t('platformPayments.reject.submit')}
                </button>
            </div>
        </form>
    );
}

export default function Show({ payment, events = [], webhookEvents = [], canVerify = false, errors = {}, nav = [], user, school }) {
    const l = useBillingLabels();
    const { t } = l;

    const manual = ['cash', 'bank_transfer'].includes(payment.method);
    const canAct = canVerify && payment.is_open;

    const eventColumns = [
        { key: 'at', label: t('platformPayments.col.when'), render: (r) => l.dateTime(r.at) },
        { key: 'type', label: t('platformPayments.col.event'), render: (r) => l.payEvent(r.type) },
        { key: 'actor', label: t('platformPayments.col.by'), render: (r) => r.actor ?? t('platformPayments.no') },
    ];

    const webhookStatus = (s) => {
        const key = `platformPayments.rec.${s}`;
        const v = t(key);
        return v === key ? s : v;
    };

    const webhookColumns = [
        { key: 'at', label: t('platformPayments.col.when'), render: (r) => l.dateTime(r.at) },
        { key: 'provider', label: t('platformPayments.show.provider'), render: (r) => l.method(r.provider) },
        { key: 'type', label: t('platformPayments.col.type') },
        { key: 'status', label: t('platformPayments.col.status'), render: (r) => <StatusBadge status={r.status === 'processed' ? 'verified' : r.status} label={webhookStatus(r.status)} /> },
        { key: 'attempts', label: t('platformPayments.col.attempts') },
    ];

    return (
        <AppShell title={t('platformPayments.show.title', { reference: payment.reference })} nav={nav} user={user} school={school}>
            <Head title={t('platformPayments.show.title', { reference: payment.reference })} />

            <PageHeader
                title={t('platformPayments.show.title', { reference: payment.reference })}
                subtitle={payment.school}
                actions={
                    <>
                        {payment.receipt_url ? (
                            <Link href={payment.receipt_url} className="btn-navy whitespace-nowrap text-sm">
                                {t('platformPayments.show.receipt')}
                            </Link>
                        ) : null}
                        <Link href={route('platform.payments.index')} className="btn-outline whitespace-nowrap text-sm">
                            {t('platformPayments.show.back')}
                        </Link>
                    </>
                }
            />

            {errors.payment ? <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{errors.payment}</p> : null}
            {!canVerify ? <p className="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{t('platformPayments.noRights')}</p> : null}

            {payment.mismatch ? (
                <div className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p className="font-semibold">{t('platformPayments.show.mismatch')}</p>
                    {payment.provider_observed ? (
                        <p className="mt-1" dir="ltr">
                            {t('platformPayments.show.observed', {
                                observed: typeof payment.provider_observed === 'string' ? payment.provider_observed : JSON.stringify(payment.provider_observed),
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {payment.settlement_error ? (
                <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{t('platformPayments.show.settlementError', { error: payment.settlement_error })}</p>
            ) : null}
            {payment.status === 'refunded' ? <p className="mb-4 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-900">{t('platformPayments.show.refundedNote')}</p> : null}
            {payment.status === 'rejected' && payment.rejection_reason ? (
                <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{t('platformPayments.show.rejectedReason', { reason: payment.rejection_reason })}</p>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="console-card">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-base font-semibold text-navy">{t('platformPayments.show.summary')}</h2>
                            <StatusBadge status={payment.status} label={l.paymentStatus(payment.status)} />
                        </div>
                        <dl className="mt-4 grid gap-5 sm:grid-cols-2">
                            <Fact label={t('platformPayments.show.school')}>
                                {payment.school_url ? (
                                    <Link href={payment.school_url} className="text-teal hover:underline">
                                        {payment.school}
                                    </Link>
                                ) : (
                                    payment.school
                                )}
                            </Fact>
                            <Fact label={t('platformPayments.show.schoolType')}>{l.schoolType(payment.school_type)}</Fact>
                            <Fact label={t('pay.fact.method')}>{l.method(payment.method)}</Fact>
                            <Fact label={t('platformPayments.show.total')}>{payment.total_label}</Fact>
                            {payment.charged_label ? <Fact label={t('platformPayments.show.charged')}>{payment.charged_label}</Fact> : null}
                            {payment.fx_rate && payment.charged_label ? (
                                <Fact label={t('platformPayments.show.rate')}>
                                    <span dir="ltr">{Number(payment.fx_rate)}</span>
                                </Fact>
                            ) : null}
                            {payment.provider ? <Fact label={t('platformPayments.show.provider')}>{l.method(payment.provider)}</Fact> : null}
                            {payment.provider_reference ? (
                                <Fact label={t('platformPayments.show.providerRef')}>
                                    <span dir="ltr">{payment.provider_reference}</span>
                                </Fact>
                            ) : null}
                            {payment.transaction_reference ? (
                                <Fact label={t('platformPayments.show.txn')}>
                                    <span dir="ltr">{payment.transaction_reference}</span>
                                </Fact>
                            ) : null}
                            {payment.provider_confirmed_at ? <Fact label={t('platformPayments.show.confirmedAt')}>{l.dateTime(payment.provider_confirmed_at)}</Fact> : null}
                            {payment.initiated_by ? <Fact label={t('platformPayments.show.initiatedBy')}>{payment.initiated_by}</Fact> : null}
                            {payment.verified_by ? <Fact label={t('platformPayments.show.verifiedBy')}>{payment.verified_by}</Fact> : null}
                            {payment.rejected_by ? <Fact label={t('platformPayments.show.rejectedBy')}>{payment.rejected_by}</Fact> : null}
                            {payment.receipt_number ? <Fact label={t('pay.fact.receipt')}>{payment.receipt_number}</Fact> : null}
                            {payment.period_start ? (
                                <Fact label={t('platformPayments.show.period')}>{`${l.date(payment.period_start)} \u2192 ${l.date(payment.period_end)}`}</Fact>
                            ) : null}
                        </dl>

                        {canVerify && payment.is_gateway && (payment.is_open || payment.status === 'failed') ? (
                            <div className="mt-5">
                                <button type="button" onClick={() => router.post(route('platform.payments.recheck', payment.id), {}, { preserveScroll: true })} className="btn-outline whitespace-nowrap text-sm">
                                    {t('platformPayments.show.recheck')}
                                </button>
                            </div>
                        ) : null}
                    </section>

                    {payment.method === 'bank_transfer' ? (
                        <section className="console-card">
                            <h2 className="text-base font-semibold text-navy">{t('platformPayments.show.evidenceTitle')}</h2>
                            <p className="mt-2 rounded-xl bg-amber-50 px-3.5 py-3 text-sm text-amber-900">{t('platformPayments.show.evidenceWarn')}</p>
                            {payment.evidence ? (
                                <div className="mt-3 text-sm text-slate-600">
                                    <p>
                                        <span dir="ltr" className="font-semibold text-navy">
                                            {payment.evidence.name}
                                        </span>{' '}
                                        <span className="text-xs text-slate-400">{l.dateTime(payment.evidence.uploaded_at)}</span>
                                    </p>
                                    {payment.claimed ? (
                                        <p className="mt-1">
                                            {t('platformPayments.show.claimed', {
                                                reference: payment.claimed.reference ?? t('platformPayments.no'),
                                                date: payment.claimed.transferred_on ? l.date(payment.claimed.transferred_on) : t('platformPayments.no'),
                                            })}
                                        </p>
                                    ) : null}
                                    <a href={payment.evidence.url} className="btn-navy mt-3 inline-flex whitespace-nowrap text-sm">
                                        {t('platformPayments.show.evidenceDownload')}
                                    </a>
                                </div>
                            ) : (
                                <p className="mt-3 text-sm text-slate-600">{t('platformPayments.show.evidenceNone')}</p>
                            )}
                        </section>
                    ) : null}

                    {canAct && payment.method === 'cash' ? <CashForm payment={payment} l={l} /> : null}
                    {canAct && payment.method === 'bank_transfer' ? <BankForm payment={payment} l={l} /> : null}
                    {canAct && manual ? <RejectForm payment={payment} l={l} /> : null}
                </div>

                <div className="space-y-6">
                    <section>
                        <h2 className="console-section-title">{t('platformPayments.show.events')}</h2>
                        <DataTable columns={eventColumns} rows={events.map((e, i) => ({ ...e, id: i }))} emptyMessage={t('platformPayments.show.eventsEmpty')} />
                    </section>
                    {webhookEvents.length > 0 ? (
                        <section>
                            <h2 className="console-section-title">{t('platformPayments.show.webhooks')}</h2>
                            <DataTable columns={webhookColumns} rows={webhookEvents.map((e, i) => ({ ...e, id: i }))} emptyMessage={t('platformPayments.show.eventsEmpty')} />
                        </section>
                    ) : null}
                </div>
            </div>
        </AppShell>
    );
}
