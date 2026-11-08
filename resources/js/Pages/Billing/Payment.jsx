import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import InputError from '@/Components/InputError';
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

const TONE = {
    pending: 'bg-amber-50 text-amber-900',
    pending_verification: 'bg-amber-50 text-amber-900',
    verified: 'bg-emerald-50 text-emerald-900',
    rejected: 'bg-red-50 text-red-900',
    failed: 'bg-red-50 text-red-900',
    cancelled: 'bg-slate-100 text-slate-700',
    expired: 'bg-slate-100 text-slate-700',
    refunded: 'bg-blue-50 text-blue-900',
};

function EvidenceForm({ payment, evidence, l }) {
    const { t } = l;
    const form = useForm({ evidence: null, claimed_reference: '', transferred_on: '', note: '' });

    function submit(e) {
        e.preventDefault();
        form.post(route('billing.payments.evidence', payment.reference), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submit} className="mt-6 border-t border-slate-100 pt-5">
            <h3 className="text-sm font-semibold text-navy">{t('pay.evidence.title')}</h3>
            <p className="mt-1 rounded-xl bg-amber-50 px-3.5 py-3 text-sm leading-6 text-amber-900">{t('pay.evidence.note')}</p>

            {payment.has_evidence ? (
                <p className="mt-3 text-sm text-slate-600">{t('pay.evidence.received', { file: payment.evidence_name ?? '' })}</p>
            ) : null}

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <label className="block text-sm text-slate-600 sm:col-span-2">
                    {t('pay.evidence.file', { mb: Math.round((evidence?.max_kb ?? 5120) / 1024) })}
                    <input
                        type="file"
                        required
                        accept={evidence?.accept}
                        className="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-navy"
                        onChange={(e) => form.setData('evidence', e.target.files?.[0] ?? null)}
                    />
                    <InputError message={form.errors.evidence} className="mt-1" />
                </label>
                <label className="block text-sm text-slate-600">
                    {t('pay.evidence.claimedRef')}
                    <input
                        className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                        value={form.data.claimed_reference}
                        maxLength={120}
                        onChange={(e) => form.setData('claimed_reference', e.target.value)}
                    />
                    <InputError message={form.errors.claimed_reference} className="mt-1" />
                </label>
                <label className="block text-sm text-slate-600">
                    {t('pay.evidence.date')}
                    <input
                        type="date"
                        className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                        value={form.data.transferred_on}
                        onChange={(e) => form.setData('transferred_on', e.target.value)}
                    />
                    <InputError message={form.errors.transferred_on} className="mt-1" />
                </label>
                <label className="block text-sm text-slate-600 sm:col-span-2">
                    {t('pay.evidence.noteLabel')}
                    <input
                        className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                        value={form.data.note}
                        maxLength={500}
                        onChange={(e) => form.setData('note', e.target.value)}
                    />
                    <InputError message={form.errors.note} className="mt-1" />
                </label>
            </div>

            <div className="mt-4">
                <button type="submit" disabled={form.processing || !form.data.evidence} className="btn-teal whitespace-nowrap text-sm disabled:opacity-50">
                    {payment.has_evidence ? t('pay.evidence.replace') : t('pay.evidence.upload')}
                </button>
            </div>
        </form>
    );
}

export default function Payment({
    payment,
    events = [],
    bankAccounts = [],
    cash = {},
    evidence = {},
    canManage = false,
    errors = {},
    nav = [],
    user,
    school,
    academicYear,
}) {
    const l = useBillingLabels();
    const { t } = l;

    const open = ['pending', 'pending_verification'].includes(payment.status);
    const provider = l.method(payment.method);

    function post(name, confirmKey) {
        if (confirmKey && !window.confirm(t(confirmKey))) return;
        router.post(route(name, payment.reference), {}, { preserveScroll: true });
    }

    return (
        <AppShell title={t('pay.show.title', { reference: payment.reference })} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('pay.show.title', { reference: payment.reference })} />

            <PageHeader
                title={t('pay.show.title', { reference: payment.reference })}
                subtitle={l.method(payment.method)}
                actions={
                    <Link href={route('billing.index')} className="btn-outline whitespace-nowrap text-sm">
                        {t('pay.show.back')}
                    </Link>
                }
            />

            <div className={`mb-6 flex flex-wrap items-center gap-3 rounded-2xl px-5 py-4 ${TONE[payment.status] ?? 'bg-slate-100 text-slate-700'}`}>
                <StatusBadge status={payment.status} label={l.paymentStatus(payment.status)} />
                <p className="text-sm font-semibold">{t(`pay.state.${payment.status}`)}</p>
            </div>

            {errors.payment ? <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{errors.payment}</p> : null}
            {!canManage && open ? <p className="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{t('pay.noRights')}</p> : null}

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                        <dl className="grid gap-5 sm:grid-cols-2">
                            <Fact label={t('pay.fact.reference')}>
                                <span dir="ltr">{payment.reference}</span>
                            </Fact>
                            <Fact label={t('pay.fact.method')}>{provider}</Fact>
                            <Fact label={t('pay.fact.amount')}>{payment.amount_label}</Fact>
                            {payment.tax_label ? <Fact label={t('pay.fact.tax')}>{payment.tax_label}</Fact> : null}
                            <Fact label={t('pay.fact.total')}>{payment.total_label}</Fact>
                            {payment.charged_label ? <Fact label={t('pay.fact.charged')}>{payment.charged_label}</Fact> : null}
                            {payment.fx_rate ? (
                                <Fact label={t('pay.fact.rate')}>
                                    <span dir="ltr">{`1 ${payment.currency} = ${Number(payment.fx_rate)} ${payment.charged_currency}`}</span>
                                </Fact>
                            ) : null}
                            <Fact label={t('pay.fact.created')}>{l.dateTime(payment.created_at)}</Fact>
                            {open && payment.expires_at ? <Fact label={t('pay.fact.expires')}>{l.date(payment.expires_at)}</Fact> : null}
                            {payment.receipt_number ? <Fact label={t('pay.fact.receipt')}>{payment.receipt_number}</Fact> : null}
                            {payment.paid_at ? <Fact label={t('pay.fact.paidOn')}>{l.date(payment.paid_at)}</Fact> : null}
                            {payment.period_start ? (
                                <Fact label={t('pay.fact.period')}>{`${l.date(payment.period_start)} \u2192 ${l.date(payment.period_end)}`}</Fact>
                            ) : null}
                        </dl>

                        {payment.receipt_url ? (
                            <div className="mt-5">
                                <Link href={payment.receipt_url} className="btn-navy whitespace-nowrap text-sm">
                                    {t('pay.history.receipt')}
                                </Link>
                            </div>
                        ) : null}
                    </section>

                    {payment.status === 'rejected' ? (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <p className="text-sm font-semibold text-red-700">{t('pay.rejected.reason', { reason: payment.rejection_reason ?? '' })}</p>
                            <p className="mt-2 text-sm text-slate-600">{t('pay.rejected.next')}</p>
                        </section>
                    ) : null}

                    {payment.status === 'failed' && payment.failure_reason === 'mismatch' ? (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <p className="text-sm text-red-700">{t('pay.gateway.mismatch', { reference: payment.reference })}</p>
                        </section>
                    ) : null}

                    {open && payment.method === 'cash' ? (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-base font-semibold text-navy">{t('pay.cash.title')}</h2>
                            <ol className="mt-3 list-decimal space-y-2 ps-5 text-sm leading-6 text-slate-600">
                                <li>{t('pay.cash.step1', { office: cash.office ?? '' })}</li>
                                <li>{t('pay.cash.step2', { reference: payment.reference, total: payment.total_label })}</li>
                                <li>{t('pay.cash.step3')}</li>
                            </ol>
                            {cash.instructions ? <p className="mt-3 text-sm text-slate-600">{cash.instructions}</p> : null}
                        </section>
                    ) : null}

                    {open && payment.method === 'bank_transfer' ? (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-base font-semibold text-navy">{t('pay.bank.title')}</h2>
                            <p className="mt-2 text-sm text-slate-600">{t('pay.bank.reference', { reference: payment.reference })}</p>
                            {bankAccounts.length === 0 ? (
                                <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('pay.bank.none')}</p>
                            ) : (
                                <div className="mt-4 space-y-4">
                                    {bankAccounts.map((a) => (
                                        <div key={a.id} className="rounded-xl border border-slate-200 p-4">
                                            <dl className="grid gap-4 sm:grid-cols-2">
                                                <Fact label={t('pay.bank.bank')}>{a.bank_name}</Fact>
                                                <Fact label={t('pay.bank.accountName')}>{a.account_name}</Fact>
                                                <Fact label={t('pay.bank.accountNumber')}>
                                                    <span dir="ltr">{a.account_number}</span>
                                                </Fact>
                                                {a.branch ? <Fact label={t('pay.bank.branch')}>{a.branch}</Fact> : null}
                                                {a.swift_code ? (
                                                    <Fact label={t('pay.bank.swift')}>
                                                        <span dir="ltr">{a.swift_code}</span>
                                                    </Fact>
                                                ) : null}
                                                <Fact label={t('pay.bank.currency')}>{a.currency}</Fact>
                                            </dl>
                                            {a.instructions ? <p className="mt-3 text-sm text-slate-600">{a.instructions}</p> : null}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {payment.can_upload && canManage ? <EvidenceForm payment={payment} evidence={evidence} l={l} /> : null}
                            {payment.has_evidence && !(payment.can_upload && canManage) ? (
                                <p className="mt-4 text-sm text-slate-600">{t('pay.evidence.received', { file: payment.evidence_name ?? '' })}</p>
                            ) : null}
                        </section>
                    ) : null}

                    {open && payment.is_gateway ? (
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <p className="text-sm leading-6 text-slate-600">{t('pay.gateway.waiting', { provider })}</p>
                            <p className="mt-2 text-sm text-slate-500">{t('pay.gateway.lateNote')}</p>
                            <div className="mt-4 flex flex-wrap gap-3">
                                {payment.checkout_url && canManage ? (
                                    <a href={payment.checkout_url} className="btn-teal whitespace-nowrap text-sm">
                                        {t('pay.gateway.continue')}
                                    </a>
                                ) : null}
                                <button type="button" onClick={() => post('billing.payments.refresh')} className="btn-outline whitespace-nowrap text-sm">
                                    {t('pay.gateway.check')}
                                </button>
                            </div>
                        </section>
                    ) : null}

                    {payment.can_cancel && canManage ? (
                        <div>
                            <button type="button" onClick={() => post('billing.payments.cancel', 'pay.cancel.confirm')} className="btn-outline whitespace-nowrap text-sm">
                                {t('pay.cancel')}
                            </button>
                        </div>
                    ) : null}
                </div>

                <section className="h-fit rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                    <h2 className="text-base font-semibold text-navy">{t('pay.timeline.title')}</h2>
                    <ol className="mt-4 space-y-3 border-s border-slate-200 ps-4 text-sm">
                        {events.map((e, i) => (
                            <li key={`${e.type}-${i}`} className="relative">
                                <span className="absolute -start-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-teal" aria-hidden="true" />
                                <p className="font-medium text-navy">{l.payEvent(e.type)}</p>
                                <p className="text-xs text-slate-500">{l.dateTime(e.at)}</p>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </AppShell>
    );
}
