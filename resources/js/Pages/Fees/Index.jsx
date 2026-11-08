import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import {
    AlertCircleIcon,
    BoltMini,
    CardPayIcon,
    CoinsStackIcon,
    FeesHeroArt,
    FileDownIcon,
    HandCoinsIcon,
    ReportDocIcon,
    WalletIcon,
    methodIcon,
    methodTone,
} from '@/Components/SchoolOS/FeesChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function FamilyFees(props) {
    const { invoices = [], recentPayments = [], stats = {}, methods = [], submissionsOn = false } = props;
    const { t, locale } = useLocale();
    const [tab, setTab] = useState('invoices');
    const showStudent = useMemo(() => new Set(invoices.map((r) => r.student).filter(Boolean)).size > 1, [invoices]);
    const reportInvoice = invoices.find((r) => r.balance > 0 && r.status !== 'cancelled');
    const reportHref = submissionsOn && reportInvoice ? route('fees.invoices.show', reportInvoice.id) : null;

    const invoiceColumns = [
        {
            key: 'invoice_number',
            label: t('fin.col.invoice'),
            render: (r) => (
                <Link href={route('fees.invoices.show', r.id)} className="font-semibold text-teal hover:underline">
                    {r.invoice_number}
                </Link>
            ),
        },
        ...(showStudent ? [{ key: 'student', label: t('fin.col.student') }] : []),
        { key: 'total_amount', label: t('fin.col.total'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.total_amount) },
        { key: 'amount_paid', label: t('fin.col.paid'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.amount_paid) },
        { key: 'balance', label: t('fin.col.balance'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.balance) },
        { key: 'due_on', label: t('fin.col.due'), render: (r) => fmtDay(r.due_on, locale) || '—' },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
    ];

    const paymentColumns = [
        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) || '—' },
        ...(showStudent ? [{ key: 'student', label: t('fin.col.student') }] : []),
        { key: 'amount', label: t('fin.col.amount'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.amount) },
        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
        {
            key: 'receipt',
            label: t('fin.col.receipt'),
            render: (r) =>
                r.status === 'confirmed' ? (
                    <a href={route('fees.payments.receipt', r.id)} target="_blank" rel="noreferrer" className="font-medium text-teal hover:underline">
                        {r.receipt_number}
                    </a>
                ) : (
                    '—'
                ),
        },
        {
            key: 'download',
            label: '',
            render: (r) =>
                r.status === 'confirmed' ? (
                    <a
                        href={route('fees.payments.receipt', r.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-9 w-9 items-center justify-center rounded-full text-teal hover:bg-teal/10"
                        aria-label={t('fin.family.downloadReceipt')}
                    >
                        <FileDownIcon className="h-5 w-5" />
                    </a>
                ) : null,
        },
    ];

    return (
        <AppShell title={t('fin.index.familyTitle')} nav={props.nav} user={props.user} school={props.school} academicYear={props.academicYear}>
            <Head title={t('fin.index.familyTitle')} />

            <section className="fd-hero">
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                    <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md sm:h-16 sm:w-16">
                        <CoinsStackIcon className="h-7 w-7" />
                    </span>
                    <div className="min-w-0">
                        <h1 className="truncate text-[1.55rem] font-bold tracking-tight text-navy sm:text-[1.85rem]">{t('fin.index.familyTitle')}</h1>
                        <p className="mt-0.5 text-sm text-slate-500">{t('fin.index.familySubtitle')}</p>
                    </div>
                </div>
                <div className="hidden md:block" aria-hidden="true">
                    <FeesHeroArt label={t('appNav.fees')} />
                </div>
            </section>

            <div className="fd-stats">
                <article className="fd-stat fd-stat-billed">
                    <div>
                        <p className="sd-stat-label">{t('fin.family.statBilled')}</p>
                        <p className="sd-stat-value">{money(stats.billed)}</p>
                    </div>
                    <span className="sd-stat-icon bg-sky-100 text-sky-700">
                        <WalletIcon />
                    </span>
                </article>
                <article className="fd-stat fd-stat-paid">
                    <div>
                        <p className="sd-stat-label">{t('fin.family.statPaid')}</p>
                        <p className="sd-stat-value">{money(stats.collected)}</p>
                    </div>
                    <span className="sd-stat-icon bg-emerald-100 text-emerald-700">
                        <HandCoinsIcon />
                    </span>
                </article>
                <article className="fd-stat fd-stat-due">
                    <div>
                        <p className="sd-stat-label">{t('fin.family.statOutstanding')}</p>
                        <p className="sd-stat-value">{money(stats.outstanding)}</p>
                    </div>
                    <div className="flex items-start gap-2">
                        {stats.outstanding > 0 ? (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                <AlertCircleIcon className="h-4 w-4" />
                            </span>
                        ) : null}
                        <span className="sd-stat-icon bg-amber-100 text-amber-700">
                            <CoinsStackIcon />
                        </span>
                    </div>
                </article>
            </div>

            <div className="fd-mid">
                <section className="fd-panel">
                    <div className="mb-3 flex items-start gap-2">
                        <span className="mt-0.5 text-teal">
                            <CardPayIcon />
                        </span>
                        <div>
                            <h2 className="text-sm font-bold text-navy">{t('fin.family.payTitle')}</h2>
                            <p className="mt-1 text-sm text-slate-500">{t('fin.family.payIntro')}</p>
                        </div>
                    </div>
                    {methods.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('fin.family.noMethods')}</p>
                    ) : (
                        <ul className="fd-methods">
                            {methods.map((m) => (
                                <li key={m.id} className={`fd-method ${methodTone(m.method)}`}>
                                    <span className="shrink-0">{methodIcon(m.method)}</span>
                                    <div className="min-w-0 text-sm">
                                        <p className="font-bold text-navy">{m.label}</p>
                                        {m.account_name ? <p className="text-slate-600">{m.account_name}</p> : null}
                                        {m.bank_name ? <p className="text-slate-600">{m.bank_name}</p> : null}
                                        {m.account_number ? (
                                            <p dir="ltr" className="font-mono font-semibold text-navy">
                                                {m.account_number}
                                            </p>
                                        ) : null}
                                        {m.instructions ? <p className="mt-1 whitespace-pre-line text-slate-500">{m.instructions}</p> : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-3 text-xs text-slate-500">{submissionsOn ? t('fin.family.submitHint') : t('fin.family.submitOff')}</p>
                </section>

                <aside className="fd-panel">
                    <h2 className="sd-panel-title mb-3">
                        <BoltMini className="text-teal" />
                        {t('fin.family.quickTitle')}
                    </h2>
                    <div className="fd-quick">
                        {reportHref ? (
                            <Link href={reportHref} className="btn-teal">
                                <ReportDocIcon />
                                {t('fin.family.reportPayment')}
                            </Link>
                        ) : null}
                        <button type="button" className="btn-outline" onClick={() => setTab('payments')}>
                            <FileDownIcon className="h-5 w-5" />
                            {t('fin.family.viewReceipts')}
                        </button>
                    </div>
                </aside>
            </div>

            <section className="fd-panel fd-table">
                <div className="fd-tabs" role="tablist" aria-label={t('fin.tabs.label')}>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'invoices'}
                        className={`fd-tab ${tab === 'invoices' ? 'fd-tab-on' : ''}`}
                        onClick={() => setTab('invoices')}
                    >
                        <ReportDocIcon className="h-4 w-4" />
                        {t('fin.index.invoices')}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'payments'}
                        className={`fd-tab ${tab === 'payments' ? 'fd-tab-on' : ''}`}
                        onClick={() => setTab('payments')}
                    >
                        <FileDownIcon className="h-4 w-4" />
                        {t('fin.index.recentPayments')}
                    </button>
                </div>
                {tab === 'invoices' ? (
                    <DataTable
                        embedded
                        className="overflow-x-auto"
                        headClassName="border-b border-teal/20 bg-teal/15"
                        columns={invoiceColumns}
                        rows={invoices}
                        emptyMessage={t('fin.index.noInvoices')}
                    />
                ) : (
                    <DataTable
                        embedded
                        className="overflow-x-auto"
                        headClassName="border-b border-teal/20 bg-teal/15"
                        columns={paymentColumns}
                        rows={recentPayments}
                        emptyMessage={t('fin.index.noPayments')}
                    />
                )}
            </section>
        </AppShell>
    );
}

export default function Index(props) {
    const { mode, invoices = [], recentPayments = [], stats = {}, can = {}, period } = props;
    const { t, locale } = useLocale();
    const staff = mode === 'staff';

    if (!staff) {
        return <FamilyFees {...props} />;
    }

    const invoiceColumns = [
        {
            key: 'invoice_number',
            label: t('fin.col.invoice'),
            render: (r) => (
                <Link href={route('fees.invoices.show', r.id)} className="font-medium text-teal-700 hover:underline">
                    {r.invoice_number}
                </Link>
            ),
        },
        { key: 'student', label: t('fin.col.student') },
        { key: 'total_amount', label: t('fin.col.total'), render: (r) => money(r.total_amount) },
        { key: 'amount_paid', label: t('fin.col.paid'), render: (r) => money(r.amount_paid) },
        { key: 'balance', label: t('fin.col.balance'), render: (r) => money(r.balance) },
        { key: 'due_on', label: t('fin.col.due'), render: (r) => fmtDay(r.due_on, locale) || '—' },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
    ];

    const paymentColumns = [
        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) || '—' },
        { key: 'student', label: t('fin.col.student') },
        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
        {
            key: 'receipt',
            label: t('fin.col.receipt'),
            render: (r) =>
                r.status === 'confirmed' ? (
                    <a href={route('fees.payments.receipt', r.id)} target="_blank" rel="noreferrer" className="text-teal-700 hover:underline">
                        {r.receipt_number}
                    </a>
                ) : null,
        },
    ];

    return (
        <FinanceLayout
            title={t('fin.index.title')}
            subtitle={t('fin.index.subtitle')}
            current="overview"
            shell={props}
            actions={
                can.manage ? (
                    <Link href={route('fees.invoices.index')} className="btn-teal">
                        {t('fin.index.newInvoice')}
                    </Link>
                ) : null
            }
        >
            {period ? (
                <p className="mb-3 text-sm text-slate-500">
                    {t('fin.index.period')}: {fmtDay(period.from, locale)} – {fmtDay(period.to, locale)}
                </p>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('fin.stat.billed')} value={money(stats.billed)} tone="navy" />
                <StatCard title={t('fin.stat.collected')} value={money(stats.collected)} tone="teal" />
                <StatCard title={t('fin.stat.outstanding')} value={money(stats.outstanding)} tone="orange" />
                <StatCard title={t('fin.stat.rate')} value={stats.collection_rate != null ? `${stats.collection_rate}%` : '—'} tone="blue" />
            </div>

            {stats.overdue > 0 || stats.pending_payments > 0 ? (
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    {stats.overdue > 0 ? (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            {t('fin.index.overdueNote', { amount: money(stats.overdue) })}
                        </div>
                    ) : null}
                    {stats.pending_payments > 0 && can.verify ? (
                        <Link href={route('fees.payments.index')} className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 hover:bg-amber-100">
                            {t('fin.index.pendingNote', { n: stats.pending_payments })}
                        </Link>
                    ) : null}
            </div>
            ) : null}

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('fin.index.invoices')}</h2>
                <DataTable columns={invoiceColumns} rows={invoices} emptyMessage={t('fin.index.noInvoices')} />
            </section>

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('fin.index.recentPayments')}</h2>
                <DataTable columns={paymentColumns} rows={recentPayments} emptyMessage={t('fin.index.noPayments')} />
            </section>
        </FinanceLayout>
    );
}
