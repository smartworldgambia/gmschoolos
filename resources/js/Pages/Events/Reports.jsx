import DataTable from '@/Components/SchoolOS/DataTable';
import { Stat } from '@/Components/SchoolOS/Form';
import EventsLayout from '@/Components/SchoolOS/EventsLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link } from '@inertiajs/react';

export default function Reports(props) {
    const { stats = {}, recent = [] } = props;
    const { t } = useLocale();

    return (
        <EventsLayout title={t('ev.rep.title')} subtitle={t('ev.rep.subtitle')} current="reports" canManage shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat label={t('ev.rep.published')} value={stats.published} />
                <Stat label={t('ev.rep.upcoming')} value={stats.upcoming} />
                <Stat label={t('ev.rep.completed')} value={stats.completed} />
            </div>
            <DataTable
                columns={[
                    { key: 'title', label: t('proc.col.title'), render: (r) => <Link href={route('events.show', r.id)} className="text-teal-700 underline">{r.title}</Link> },
                    { key: 'starts_at', label: t('ev.starts') },
                    { key: 'taken', label: t('ev.regs') },
                    { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'ev.status', r.status)} /> },
                ]}
                rows={recent}
                emptyMessage={t('ev.empty')}
            />
        </EventsLayout>
    );
}
