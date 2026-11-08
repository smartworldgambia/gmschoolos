import { Select } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router } from '@inertiajs/react';
import Filters from './Filters';
import ReportTable from './ReportTable';
import { reportingShell } from './shell';

const STATUSES = ['draft', 'submitted', 'in_review', 'approved', 'published', 'superseded'];

export default function Index({ nav, user, school, academicYear, tabs = [], current, can = {}, reports, type, filters = {} }) {
    const { t } = useLocale();
    const titleKey = type ? `rpt.type.${type}` : current === 'approval' ? 'rpt.tab.approval' : current === 'register' ? 'rpt.tab.register' : 'rpt.title';
    const action = route(current === 'approval' ? 'reporting.approval' : current === 'register' ? 'reporting.register' : type === 'annual' ? 'reporting.annual' : type === 'final' ? 'reporting.final' : 'reporting.termly');

    return (
        <ModuleLayout
            ns="rpt"
            title={t(titleKey)}
            subtitle={t('rpt.subtitle')}
            tabs={tabs}
            current={current}
            shell={reportingShell({ nav, user, school, academicYear })}
            actions={
                can.prepare ? (
                    <Link href={route('reporting.generate', { type: type || 'termly' })} className="btn-teal">
                        {t('rpt.generate')}
                    </Link>
                ) : null
            }
        >
            <Filters
                action={action}
                filters={filters}
                type={type}
                extra={
                    <Select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(
                                action,
                                {
                                    academic_year_id: filters.academic_year_id || undefined,
                                    term_id: filters.term_id || undefined,
                                    class_id: filters.class_id || undefined,
                                    level_id: filters.level_id || undefined,
                                    q: filters.q || undefined,
                                    status: e.target.value || undefined,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="max-w-[11rem]"
                    >
                        <option value="">{t('rpt.col.status')}</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {enumLabel(t, 'rpt.status', s)}
                            </option>
                        ))}
                    </Select>
                }
            />
            <ReportTable reports={reports} can={can} />
        </ModuleLayout>
    );
}
