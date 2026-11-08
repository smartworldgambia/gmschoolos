import DataTable from '@/Components/SchoolOS/DataTable';
import { Stat } from '@/Components/SchoolOS/Form';
import SafeguardingLayout from '@/Components/SchoolOS/SafeguardingLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router } from '@inertiajs/react';

export default function Index(props) {
    const { cases = [], filter = 'open', stats = {} } = props;
    const { t } = useLocale();

    return (
        <SafeguardingLayout title={t('sg.title')} subtitle={t('sg.subtitle')} current="cases" canManage shell={props} actions={<Link href={route('safeguarding.report')} className="btn-teal">{t('sg.report')}</Link>}>
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
                <Stat label={t('sg.stat.open')} value={stats.open} />
                <Stat label={t('sg.stat.critical')} value={stats.critical} tone={stats.critical > 0 ? 'red' : 'navy'} />
            </div>
            <p className="mb-3 text-sm text-slate-600">{t('sg.listNote')}</p>
            <div className="mb-4 flex flex-wrap gap-2">
                {['open', 'investigating', 'closed'].map((s) => (
                    <button key={s} type="button" className={filter === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('safeguarding.index'), { status: s }, { preserveState: true, replace: true })}>
                        {t(`sg.status.${s}`)}
                    </button>
                ))}
            </div>
            <DataTable
                columns={[
                    { key: 'reference', label: t('sg.col.ref'), render: (r) => <Link href={route('safeguarding.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.reference}</Link> },
                    { key: 'category', label: t('inv.col.type'), render: (r) => enumLabel(t, 'sg.category', r.category) },
                    { key: 'severity', label: t('sg.col.severity'), render: (r) => <StatusBadge status={r.severity === 'critical' ? 'critical' : r.severity === 'high' ? 'overdue' : 'pending'} label={enumLabel(t, 'sg.severity', r.severity)} /> },
                    { key: 'student', label: t('tr.student') },
                    { key: 'assignee', label: t('sg.col.assignee') },
                    { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'sg.status', r.status)} /> },
                    { key: 'created_at', label: t('acad.common.date') },
                ]}
                rows={cases}
                emptyMessage={t('sg.empty')}
            />
        </SafeguardingLayout>
    );
}
