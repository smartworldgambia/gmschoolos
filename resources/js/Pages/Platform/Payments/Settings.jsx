import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import InputError from '@/Components/InputError';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

const inputClass = 'ui-input mt-1';

function BankForm({ l }) {
    const { t } = l;
    const form = useForm({ bank_name: '', account_name: '', account_number: '', branch: '', swift_code: '', currency: 'GMD', instructions: '' });

    const field = (name, label, props = {}) => (
        <label className="block text-sm text-slate-600">
            {label}
            <input className={inputClass} value={form.data[name]} onChange={(e) => form.setData(name, props.upper ? e.target.value.toUpperCase() : e.target.value)} {...props.input} />
            <InputError message={form.errors[name]} className="mt-1" />
        </label>
    );

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('platform.payments.banks.store'), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="console-card"
        >
            <h2 className="text-base font-semibold text-navy">{t('platformPayments.settings.bank.add')}</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                {field('bank_name', t('platformPayments.settings.bank.bankName'), { input: { required: true, maxLength: 120 } })}
                {field('account_name', t('platformPayments.settings.bank.accountName'), { input: { required: true, maxLength: 160 } })}
                {field('account_number', t('platformPayments.settings.bank.accountNumber'), { input: { required: true, maxLength: 60 } })}
                {field('branch', t('platformPayments.settings.bank.branch'), { input: { maxLength: 120 } })}
                {field('swift_code', t('platformPayments.settings.bank.swift'), { input: { maxLength: 20 } })}
                {field('currency', t('platformPayments.settings.bank.currency'), { upper: true, input: { required: true, maxLength: 3 } })}
                <div className="sm:col-span-2">{field('instructions', t('platformPayments.settings.bank.instructions'), { input: { maxLength: 1500 } })}</div>
            </div>
            <div className="mt-4">
                <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                    {t('platformPayments.settings.bank.add')}
                </button>
            </div>
        </form>
    );
}

export default function Settings({ methods = [], bankAccounts = [], webhookUrls = {}, canConfigure = false, nav = [], user, school }) {
    const l = useBillingLabels();
    const { t } = l;

    const statusTone = {
        implemented: 'verified',
        requires_configuration: 'warning',
        configured_untested: 'info',
        traffic_observed: 'info',
    };

    const methodColumns = [
        { key: 'key', label: t('platformPayments.settings.col.method'), render: (r) => l.method(r.key) },
        {
            key: 'enabled',
            label: t('platformPayments.settings.col.switch'),
            render: (r) => (
                <StatusBadge status={r.enabled ? 'active' : 'inactive'} label={r.enabled ? t('platformPayments.settings.on') : t('platformPayments.settings.off')} />
            ),
        },
        {
            key: 'status',
            label: t('platformPayments.settings.col.status'),
            render: (r) => <StatusBadge status={statusTone[r.status] ?? r.status} label={t(`platformPayments.settings.status.${r.status}`)} />,
        },
        {
            key: 'mode',
            label: t('platformPayments.settings.col.mode'),
            render: (r) => (r.mode ? t(`platformPayments.settings.mode.${r.mode}`) : t('platformPayments.no')),
        },
        {
            key: 'charge_currency',
            label: t('platformPayments.settings.col.currency'),
            render: (r) =>
                r.charge_currency ? (
                    <span dir="ltr">
                        {r.charge_currency}
                        {r.fx_rate ? ` \u00b7 ${t('platformPayments.settings.rate', { rate: r.fx_rate })}` : ''}
                    </span>
                ) : (
                    t('platformPayments.no')
                ),
        },
        {
            key: 'verified_transactions',
            label: t('platformPayments.settings.col.traffic'),
            render: (r) => r.verified_transactions ?? t('platformPayments.no'),
        },
        {
            key: 'webhooks_processed',
            label: t('platformPayments.settings.col.webhooks'),
            render: (r) => (r.webhooks_processed === null ? t('platformPayments.no') : `${r.webhooks_processed} / ${r.webhooks_failed}`),
        },
    ];

    return (
        <AppShell title={t('platformPayments.settings.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformPayments.settings.title')} />

            <PageHeader
                title={t('platformPayments.settings.title')}
                subtitle={t('platformPayments.settings.subtitle')}
                actions={
                    <Link href={route('platform.payments.index')} className="btn-outline whitespace-nowrap text-sm">
                        {t('platformPayments.show.back')}
                    </Link>
                }
            />

            <section>
                <h2 className="console-section-title">{t('platformPayments.settings.methods')}</h2>
                <DataTable columns={methodColumns} rows={methods} keyField="key" />
                <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{t('platformPayments.settings.note')}</p>
                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    {Object.entries(webhookUrls).map(([provider, url]) => (
                        <div key={provider} className="console-card">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {l.method(provider)} {'\u00b7'} {t('platformPayments.settings.webhookUrl')}
                            </dt>
                            <dd className="mt-1 break-all font-mono text-xs text-navy" dir="ltr">
                                {url}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            <section className="mt-8">
                <h2 className="console-section-title">{t('platformPayments.settings.banks')}</h2>
                {bankAccounts.length === 0 ? (
                    <p className="console-card text-sm text-slate-600">{t('platformPayments.settings.banksEmpty')}</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {bankAccounts.map((a) => (
                            <div key={a.id} className="console-card">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-semibold text-navy">{a.bank_name}</p>
                                        <p className="text-sm text-slate-600">{a.account_name}</p>
                                        <p className="mt-1 text-sm text-slate-600" dir="ltr">
                                            {a.account_number} {'\u00b7'} {a.currency}
                                        </p>
                                        {a.branch ? <p className="text-xs text-slate-500">{a.branch}</p> : null}
                                        {a.swift_code ? (
                                            <p className="text-xs text-slate-500" dir="ltr">
                                                {a.swift_code}
                                            </p>
                                        ) : null}
                                    </div>
                                    <StatusBadge status={a.is_active ? 'active' : 'inactive'} label={a.is_active ? t('platformPayments.settings.bank.active') : t('platformPayments.settings.bank.inactive')} />
                                </div>
                                {a.instructions ? <p className="mt-3 text-sm text-slate-600">{a.instructions}</p> : null}
                                {canConfigure ? (
                                    <div className="mt-4">
                                        <button type="button" onClick={() => router.post(route('platform.payments.banks.toggle', a.id), {}, { preserveScroll: true })} className="btn-outline whitespace-nowrap text-sm">
                                            {a.is_active ? t('platformPayments.settings.bank.deactivate') : t('platformPayments.settings.bank.activate')}
                                        </button>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="mt-6">
                {canConfigure ? <BankForm l={l} /> : <p className="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{t('platformPayments.settings.noRights')}</p>}
            </section>
        </AppShell>
    );
}
