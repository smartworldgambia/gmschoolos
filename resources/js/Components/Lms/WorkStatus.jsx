import StatusBadge from '@/Components/SchoolOS/StatusBadge';

const MAP = {
    upcoming: { status: 'scheduled', key: 'lms.work.upcoming' },
    due: { status: 'due', key: 'lms.work.dueSoon' },
    overdue: { status: 'overdue', key: 'lms.work.overdue' },
    submitted: { status: 'submitted', key: 'lms.work.submitted' },
    graded: { status: 'graded', key: 'lms.work.graded' },
    returned: { status: 'returned', key: 'lms.work.returned' },
    missing: { status: 'missing', key: 'lms.work.missing' },
    to_mark: { status: 'due', key: 'lms.work.toMark' },
};

export default function WorkStatus({ state, t, className = '' }) {
    const row = MAP[state];
    if (!row) return null;

    return <StatusBadge status={row.status} label={t(row.key)} className={className} />;
}
