import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import ChartCard from '@/Components/SchoolOS/ChartCard';
import DataTable from '@/Components/SchoolOS/DataTable';
import FilterBar from '@/Components/SchoolOS/FilterBar';
import SectionCard from '@/Components/SchoolOS/SectionCard';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { Link, router } from '@inertiajs/react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

function formatChartMoney(value) {
    return money(value ?? 0);
}

export default function Index({
    stats = {},
    period = {},
    series = [],
    recent = [],
    current = 'overview',
    ...shell
}) {
    const { t } = useLocale();
    const hasSeriesActivity = series.some((point) => (point.income ?? 0) > 0 || (point.expenses ?? 0) > 0);

    function applyPeriod(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        router.get(route('accounting.index'), { from: fd.get('from'), to: fd.get('to') }, { preserveState: true });
    }

    const actions = (
        <>
            {shell.can?.export ? (
                <Link href={route('accounting.income', { from: period.from, to: period.to, export: 1 })} className="btn-outline text-sm">
                    {t('ais.export')}
                </Link>
            ) : null}
            {shell.can?.post ? (
                <Link href={route('accounting.journals')} className="btn-primary text-sm">
                    + {t('ais.newTransaction')}
                </Link>
            ) : null}
        </>
    );

    return (
        <AccountingLayout
            title={t('ais.overview.title')}
            subtitle={t('ais.overview.subtitle')}
            current={current}
            shell={shell}
            actions={actions}
            icon="ais"
        >
            {stats.setup_needed ? (
                <div className="console-banner-warn" role="status">
                    <span className="console-banner-icon-warn" aria-hidden="true">
                        !
                    </span>
                    <p>
                        {t('ais.setup.needed')}{' '}
                        {shell.can?.manage ? (
                            <Link href={route('accounting.setup')} className="font-semibold text-navy underline underline-offset-2">
                                {t('ais.setup.open')}
                            </Link>
                        ) : null}
                    </p>
                </div>
            ) : null}

            <form onSubmit={applyPeriod}>
                <FilterBar
                    actions={
                        <button type="submit" className="btn-outline text-sm">
                            {t('ais.apply')}
                        </button>
                    }
                >
                    <label className="text-sm">
                        <span className="mb-1 block text-slate-500">{t('ais.from')}</span>
                        <input type="date" name="from" defaultValue={period.from} className="ui-input" />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block text-slate-500">{t('ais.to')}</span>
                        <input type="date" name="to" defaultValue={period.to} className="ui-input" />
                    </label>
                </FilterBar>
            </form>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard title={t('ais.stat.income')} value={money(stats.income)} tone="green" />
                <StatCard title={t('ais.stat.expenses')} value={money(stats.expenses)} tone="red" />
                <StatCard title={t('ais.stat.surplus')} value={money(stats.surplus)} tone="blue" />
                <StatCard title={t('ais.stat.cashBank')} value={money(stats.cash_bank)} tone="purple" />
                <StatCard title={t('ais.stat.ar')} value={money(stats.ar)} tone="amber" />
                <StatCard title={t('ais.stat.ap')} value={money(stats.ap)} tone="blue" />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-5">
                <ChartCard
                    title={t('ais.chart.title')}
                    className="xl:col-span-3"
                    empty={!hasSeriesActivity}
                    emptyTitle={t('ais.chart.empty')}
                >
                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={series} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#E3EAF2" />
                                <XAxis dataKey="label" tick={{ fill: '#64748B', fontSize: 12 }} axisLine={{ stroke: '#E3EAF2' }} tickLine={false} />
                                <YAxis tickFormatter={(v) => formatChartMoney(v)} tick={{ fill: '#64748B', fontSize: 11 }} axisLine={false} tickLine={false} width={72} />
                                <Tooltip formatter={(value) => formatChartMoney(value)} />
                                <Legend />
                                <Line type="monotone" dataKey="income" name={t('ais.stat.income')} stroke="#16A34A" strokeWidth={2.5} dot={false} />
                                <Line type="monotone" dataKey="expenses" name={t('ais.stat.expenses')} stroke="#DC2626" strokeWidth={2.5} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </ChartCard>

                <SectionCard title={t('ais.recent.title')} className="xl:col-span-2">
                    <DataTable
                        embedded
                        emptyMessage={t('ais.recent.empty')}
                        columns={[
                            { key: 'date', label: t('ais.col.date') },
                            { key: 'memo', label: t('ais.col.description'), cellClassName: 'max-w-[10rem] truncate' },
                            {
                                key: 'type',
                                label: t('ais.col.type'),
                                render: (row) => (
                                    <StatusBadge
                                        status={row.type === 'expense' ? 'danger' : row.type === 'income' ? 'success' : 'info'}
                                        label={
                                            row.type === 'expense'
                                                ? t('ais.type.expense')
                                                : row.type === 'income'
                                                  ? t('ais.type.income')
                                                  : t(`ais.source.${row.source}`)
                                        }
                                    />
                                ),
                            },
                            {
                                key: 'amount',
                                label: t('ais.col.amountGmd'),
                                cellClassName: 'font-semibold text-navy',
                                render: (row) => money(row.amount),
                            },
                        ]}
                        rows={recent}
                    />
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Link href={route('fees.index')} className="btn-outline text-sm">
                            {t('ais.link.fees')}
                        </Link>
                        <Link href={route('accounting.trial-balance')} className="btn-navy text-sm">
                            {t('ais.link.trial')}
                        </Link>
                        <Link href={route('accounting.cashbook')} className="btn-outline text-sm">
                            {t('ais.link.cashbook')}
                        </Link>
                    </div>
                </SectionCard>
            </div>
        </AccountingLayout>
    );
}
