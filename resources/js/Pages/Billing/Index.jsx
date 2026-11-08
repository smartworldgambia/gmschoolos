import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Fact({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-1 text-sm font-semibold text-navy">{children}</dd>
        </div>
    );
}

function SubscribePanel({ billing, methods, openPayment, canManage, l }) {
    const { t } = l;
    const available = methods.filter((m) => m.enabled);
    const [method, setMethod] = useState(available[0]?.key ?? null);
    const form = useForm({ method: '', expected_charge: '' });
    const priced = Boolean(billing.annual_amount_label);
    const live = ['trial', 'active'].includes(billing.status);
    const chosen = methods.find((m) => m.key === method);

    function submit(e) {
        e.preventDefault();
        if (!chosen) return;
        // Display labels are translated; the submitted value is always the stable English slug.
        form.transform(() => ({
            method: chosen.key,
            // Gateways: the exact amount the school was shown. The server refuses if the price moved meanwhile.
            ...(chosen.charge ? { expected_charge: chosen.charge.amount } : {}),
        }));
        form.post(route('billing.payments.store'), { preserveScroll: true });
    }

    return (
        <section className="mt-6 rounded-2xl bg-white p-5 shadow-sm sm:p-6">
            <h2 className="text-base font-semibold text-navy">
                {live && billing.status === 'active' ? t('pay.renew.title') : t('pay.subscribe.title')}
            </h2>

            {openPayment ? (
                <div className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p className="font-semibold">{t('pay.open.title')}</p>
                    <p className="mt-1">{t('pay.open.body', { reference: openPayment.reference })}</p>
                    <Link href={openPayment.url} className="btn-teal mt-3 inline-flex whitespace-nowrap text-sm">
                        {t('pay.open.view')}
                    </Link>
                </div>
            ) : !priced ? (
                <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('pay.subscribe.notConfigured')}</p>
            ) : (
                <form onSubmit={submit} className="mt-3">
                    <p className="text-sm leading-6 text-slate-600">{t('pay.subscribe.lead')}</p>
                    {!canManage ? (
                        <p className="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{t('pay.subscribe.noRights')}</p>
                    ) : null}

                    <fieldset disabled={!canManage} className="mt-4 grid gap-3 sm:grid-cols-2">
                        <legend className="sr-only">{t('pay.subscribe.title')}</legend>
                        {methods.map((m) => (
                            <label
                                key={m.key}
                                className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm transition ${
                                    !m.enabled
                                        ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400'
                                        : method === m.key
                                          ? 'border-teal bg-teal/5'
                                          : 'border-slate-200 hover:border-teal/60'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="method"
                                    value={m.key}
                                    disabled={!m.enabled}
                                    checked={method === m.key}
                                    onChange={() => setMethod(m.key)}
                                    className="mt-1 text-teal focus:ring-teal"
                                />
                                <span className="min-w-0">
                                    <span className="block font-semibold text-navy">{l.method(m.key)}</span>
                                    <span className="mt-0.5 block text-xs leading-5 text-slate-500">
                                        {m.enabled
                                            ? t(`pay.method.${m.key}.desc`)
                                            : `${t('pay.unavailable')} \u00b7 ${t(`pay.reason.${m.reason ?? 'disabled'}`)}`}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    {chosen?.charge ? (
                        <p className="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
                            {chosen.charge.rate
                                ? t('pay.charge.converted', {
                                      amount: chosen.charge.label,
                                      total: billing.annual_amount_label,
                                      from: billing.currency ?? '',
                                      rate: chosen.charge.rate,
                                      to: chosen.charge.currency,
                                  })
                                : t('pay.charge.note', { amount: chosen.charge.label })}
                        </p>
                    ) : null}

                    <InputError message={form.errors.payment} className="mt-3" />
                    <InputError message={form.errors.method} className="mt-3" />

                    <div className="mt-4">
                        <button
                            type="submit"
                            disabled={!canManage || !chosen || !chosen.enabled || form.processing}
                            className="btn-teal whitespace-nowrap text-sm disabled:opacity-50"
                        >
                            {t('pay.continue')}
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}

export default function Index({
    billing = null,
    payments = [],
    methods = [],
    openPayment = null,
    canManage = false,
    support = {},
    nav = [],
    user,
    school,
    academicYear,
}) {
    const l = useBillingLabels();
    const { t } = l;

    const live = billing && ['trial', 'active'].includes(billing.status);
    const anyOnline = methods.some((m) => m.enabled);

    const columns = [
        { key: 'receipt_number', label: t('billing.col.receipt'), render: (r) => r.receipt_number ?? r.reference },
        { key: 'paid_at', label: t('billing.col.date'), render: (r) => l.date(r.paid_at) },
        { key: 'method', label: t('billing.col.method'), render: (r) => l.method(r.method) },
        { key: 'total_label', label: t('billing.col.amount') },
        {
            key: 'period',
            label: t('billing.col.period'),
            render: (r) => `${l.date(r.period_start)} \u2192 ${l.date(r.period_end)}`,
        },
        {
            key: 'status',
            label: t('billing.col.status'),
            render: (r) => <StatusBadge status={r.status} label={l.paymentStatus(r.status)} />,
        },
        {
            key: 'actions',
            label: t('pay.col.actions'),
            render: (r) => (
                <span className="flex items-center gap-3 whitespace-nowrap">
                    {r.url ? (
                        <Link href={r.url} className="font-medium text-teal hover:underline">
                            {t('pay.history.view')}
                        </Link>
                    ) : null}
                    {r.receipt_url ? (
                        <Link href={r.receipt_url} className="font-medium text-teal hover:underline">
                            {t('pay.history.receipt')}
                        </Link>
                    ) : null}
                </span>
            ),
        },
    ];

    return (
        <AppShell title={t('billing.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('billing.title')} />

            <PageHeader title={t('billing.title')} subtitle={t('billing.subtitle')} />

            {!billing ? (
                <p className="rounded-xl bg-white p-6 text-sm text-slate-600 shadow-sm">{t('billing.empty')}</p>
            ) : (
                <>
                    <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={billing.status} label={l.status(billing.status)} />
                            <p className="text-sm font-semibold text-navy">{l.countdown(billing)}</p>
                        </div>

                        <dl className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <Fact label={t('billing.summary.schoolType')}>{l.schoolType(billing.school_type)}</Fact>
                            <Fact label={t('billing.summary.annualFee')}>
                                {billing.annual_amount_label ?? t('billing.notSet')}
                                {billing.tax_rate ? (
                                    <span className="ms-2 text-xs font-normal text-slate-500">
                                        {t('billing.summary.taxIncluded', { rate: Number(billing.tax_rate) })}
                                    </span>
                                ) : null}
                            </Fact>
                            <Fact label={t('billing.summary.cycle')}>{t('billing.cycle.annual')}</Fact>
                            <Fact label={t('billing.summary.payment')}>
                                <StatusBadge status={billing.payment_status} label={l.payStatus(billing.payment_status)} />
                            </Fact>
                            {billing.status === 'trial' ? (
                                <Fact label={t('billing.summary.trialEnds')}>{l.date(billing.trial_ends_at)}</Fact>
                            ) : live ? (
                                <Fact label={t('billing.summary.renewsOn')}>{l.date(billing.renews_at ?? billing.ends_at)}</Fact>
                            ) : (
                                <Fact label={t('billing.summary.endedOn')}>{l.date(billing.ends_at)}</Fact>
                            )}
                            {live ? (
                                <Fact label={t('billing.summary.daysLeft')}>{billing.days_remaining}</Fact>
                            ) : (
                                <Fact label={t('billing.summary.amountDue')}>
                                    {billing.outstanding_label ?? t('billing.notSet')}
                                </Fact>
                            )}
                        </dl>
                    </section>

                    <SubscribePanel billing={billing} methods={methods} openPayment={openPayment} canManage={canManage} l={l} />

                    <div className="mt-6 grid gap-6 lg:grid-cols-3">
                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6 lg:col-span-2">
                            <h2 className="text-base font-semibold text-navy">{t('billing.how.title')}</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-600">{t('billing.how.body')}</p>
                            {!anyOnline ? (
                                <p className="mt-3 rounded-xl bg-amber-50 px-3.5 py-3 text-sm text-amber-900">
                                    {t('billing.how.noOnline')}
                                </p>
                            ) : null}
                            {support?.email || support?.phone ? (
                                <p className="mt-3 text-sm text-slate-600">
                                    {t('billing.how.contact')}{' '}
                                    {support.email ? (
                                        <a className="font-semibold text-teal hover:underline" href={`mailto:${support.email}`}>
                                            {support.email}
                                        </a>
                                    ) : null}
                                    {support.email && support.phone ? ' \u00b7 ' : null}
                                    {support.phone ? <span dir="ltr">{support.phone}</span> : null}
                                </p>
                            ) : null}
                        </section>

                        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-base font-semibold text-navy">{t('billing.how.methods')}</h2>
                            <ul className="mt-3 space-y-2 text-sm">
                                {methods.map((m) => (
                                    <li key={m.key} className="flex items-center justify-between gap-3">
                                        <span className="text-navy">{l.method(m.key)}</span>
                                        <span
                                            className={`whitespace-nowrap text-xs font-semibold ${
                                                m.enabled ? 'text-emerald-700' : 'text-slate-400'
                                            }`}
                                        >
                                            {m.enabled ? t('billing.how.available') : t('billing.how.soon')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                </>
            )}

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    {t('billing.history.title')}
                </h2>
                <DataTable columns={columns} rows={payments} emptyMessage={t('billing.history.empty')} />
            </section>
        </AppShell>
    );
}
