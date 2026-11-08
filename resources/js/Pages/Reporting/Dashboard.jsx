import { Card } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';
import Filters from './Filters';
import ReportTable from './ReportTable';
import { reportingShell } from './shell';

function Kpi({ label, value }) {
    return (
        <div className="rounded-xl bg-white p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-navy">{value ?? 0}</p>
        </div>
    );
}

export default function Dashboard({ nav, user, school, academicYear, tabs = [], current = 'dashboard', can = {}, kpis = {}, classOverview = [], recent = [], filters = {} }) {
    const { t } = useLocale();

    return (
        <ModuleLayout
            ns="rpt"
            title={t('rpt.title')}
            subtitle={t('rpt.subtitle')}
            tabs={tabs}
            current={current}
            shell={reportingShell({ nav, user, school, academicYear })}
            actions={
                can.prepare ? (
                    <Link href={route('reporting.generate')} className="btn-teal">
                        {t('rpt.generate')}
                    </Link>
                ) : null
            }
        >
            <p className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('rpt.notOfficial')}</p>
            <Filters action={route('reporting.index')} filters={filters} />

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <Kpi label={t('rpt.kpi.students')} value={kpis.students} />
                <Kpi label={t('rpt.kpi.pending')} value={kpis.pending} />
                <Kpi label={t('rpt.kpi.awaiting')} value={kpis.awaiting_approval} />
                <Kpi label={t('rpt.kpi.published')} value={kpis.published} />
                <Kpi label={t('rpt.kpi.generated')} value={kpis.generated} />
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-2">
                <Card title={t('rpt.classOverview')}>
                    {classOverview.length === 0 ? (
                        <p className="text-sm text-slate-500">{t('rpt.empty')}</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {classOverview.map((c) => (
                                <li key={c.id} className="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <p className="font-medium text-navy">{c.name}</p>
                                        <p className="text-xs text-slate-500">
                                            {c.level} · {c.students}
                                        </p>
                                    </div>
                                    <p className="font-semibold text-navy">{c.average ?? '—'}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                <Card
                    title={t('rpt.recent')}
                    actions={
                        <Link href={route('reporting.termly')} className="text-sm font-medium text-teal-700 hover:underline">
                            {t('rpt.viewAll')}
                        </Link>
                    }
                >
                    <ReportTable reports={recent} can={can} />
                </Card>
            </div>
        </ModuleLayout>
    );
}
