import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card } from '@/Components/SchoolOS/Form';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link } from '@inertiajs/react';

export default function Index(props) {
    const { stats = {}, pendingLeave = [], awayToday = [], endingContracts = [], expiringDocuments = [], latestRun = null, can = {} } = props;
    const { t, locale } = useLocale();

    return (
        <HrLayout title={t('hr.overview.title')} subtitle={t('hr.overview.subtitle')} current="overview" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <StatCard title={t('hr.overview.staff')} value={stats.staff} tone="navy" />
                <StatCard title={t('hr.overview.away')} value={stats.away} tone="blue" />
                <StatCard title={t('hr.overview.pendingLeave')} value={stats.pending_leave} tone="orange" />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title={t('hr.overview.needsDecision')} actions={<Link href={route('hr.leave.index')} className="btn-outline">{t('hr.overview.allLeave')}</Link>}>
                    <DataTable
                        columns={[
                            { key: 'staff', label: t('hr.col.staff') },
                            { key: 'leave_type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.leaveType', r.leave_type) },
                            { key: 'starts_on', label: t('hr.col.period'), render: (r) => `${fmtDay(r.starts_on, locale)} – ${fmtDay(r.ends_on, locale)}` },
                            { key: 'days', label: t('hr.col.days') },
                        ]}
                        rows={pendingLeave}
                        emptyMessage={t('hr.overview.nothingPending')}
                    />
                </Card>

                <Card title={t('hr.overview.awayToday')}>
                    <DataTable
                        columns={[
                            { key: 'staff', label: t('hr.col.staff') },
                            { key: 'leave_type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.leaveType', r.leave_type) },
                            { key: 'ends_on', label: t('hr.overview.backAfter'), render: (r) => fmtDay(r.ends_on, locale) },
                        ]}
                        rows={awayToday}
                        emptyMessage={t('hr.overview.nobodyAway')}
                    />
                </Card>

                <Card title={t('hr.overview.endingContracts')}>
                    <DataTable
                        columns={[
                            { key: 'staff', label: t('hr.col.staff'), render: (r) => <Link href={route('hr.staff.show', r.staff_id)} className="text-teal-700 underline">{r.staff}</Link> },
                            { key: 'ends_on', label: t('hr.col.endsOn'), render: (r) => fmtDay(r.ends_on, locale) },
                        ]}
                        rows={endingContracts}
                        emptyMessage={t('hr.overview.noEndingContracts')}
                    />
                </Card>

                {can.manage ? (
                    <Card title={t('hr.overview.expiringDocs')}>
                        <DataTable
                            columns={[
                                { key: 'staff', label: t('hr.col.staff'), render: (r) => <Link href={route('hr.staff.show', r.staff_id)} className="text-teal-700 underline">{r.staff}</Link> },
                                { key: 'title', label: t('hr.col.document') },
                                { key: 'expires_on', label: t('hr.col.expires'), render: (r) => fmtDay(r.expires_on, locale) },
                            ]}
                            rows={expiringDocuments}
                            emptyMessage={t('hr.overview.noExpiringDocs')}
                        />
                    </Card>
                ) : null}
            </div>

            {can.pay && latestRun ? (
                <Card title={t('hr.overview.latestPayroll')} className="mt-6 max-w-xl">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-semibold text-navy">{latestRun.period_label}</p>
                            <p className="text-sm text-slate-600">{t('hr.col.net')}: {money(latestRun.net_total)}</p>
                        </div>
                        <StatusBadge status={latestRun.status} label={enumLabel(t, 'hr.status', latestRun.status)} />
                        <Link href={route('hr.payroll.show', latestRun.id)} className="btn-outline">{t('hr.overview.openRun')}</Link>
                    </div>
                </Card>
            ) : null}
        </HrLayout>
    );
}
