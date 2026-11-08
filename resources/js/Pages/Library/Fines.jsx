import DataTable from '@/Components/SchoolOS/DataTable';
import { Pager } from '@/Components/SchoolOS/Form';
import LibraryLayout from '@/Components/SchoolOS/LibraryLayout';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { router } from '@inertiajs/react';

const FILTERS = ['unpaid', 'paid', 'waived'];

export default function Fines(props) {
    const { fines, status = 'unpaid' } = props;
    const { t, locale } = useLocale();

    const columns = [
        { key: 'created_at', label: t('acad.common.date'), render: (r) => fmtDay(r.created_at, locale) },
        { key: 'borrower', label: t('lib.col.borrower') },
        { key: 'title', label: t('lib.col.title') },
        { key: 'reason', label: t('lib.fine.reason'), render: (r) => enumLabel(t, 'lib.fineReason', r.reason) },
        { key: 'amount', label: t('lib.fine.amount'), render: (r) => money(r.amount) },
        { key: 'status', label: t('lib.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'lib.fine', r.status)} /> },
        { key: 'note', label: t('acad.common.notes'), cellClassName: 'max-w-xs whitespace-normal' },
        {
            key: 'actions',
            label: '',
            cellClassName: 'whitespace-normal',
            render: (r) =>
                r.status === 'unpaid' ? (
                    <div className="flex flex-wrap items-start gap-2">
                        <ReasonAction url={route('library.fines.decide', r.id)} fieldName="note" fields={{ status: 'paid' }} required={false} label={t('lib.fine.markPaid')} tone="teal" reasonLabel={t('lib.fine.receiptNote')} />
                        <ReasonAction url={route('library.fines.decide', r.id)} fieldName="note" fields={{ status: 'waived' }} label={t('lib.fine.waive')} tone="danger" />
                    </div>
                ) : null,
        },
    ];

    return (
        <LibraryLayout title={t('lib.fines.title')} subtitle={t('lib.fines.subtitle')} current="fines" shell={props}>
            <div className="mb-4 flex flex-wrap gap-2">
                <button type="button" className={status === 'all' ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('library.fines.index'), { status: 'all' }, { preserveState: true, replace: true })}>
                    {t('acad.common.all')}
                </button>
                {FILTERS.map((s) => (
                    <button key={s} type="button" className={status === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('library.fines.index'), { status: s }, { preserveState: true, replace: true })}>
                        {t(`lib.fine.${s}`)}
                    </button>
                ))}
            </div>
            <DataTable columns={columns} rows={fines?.data ?? []} emptyMessage={t('lib.fines.empty')} />
            <Pager links={fines?.links} />
            <p className="mt-2 text-xs text-slate-500">{t('lib.fines.note')}</p>
        </LibraryLayout>
    );
}
