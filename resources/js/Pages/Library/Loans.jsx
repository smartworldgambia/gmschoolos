import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import LibraryLayout from '@/Components/SchoolOS/LibraryLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const VIEWS = ['out', 'overdue', 'returned'];

/** Inline return form: condition, an optional fine for a lost or damaged copy, and a note. */
function ReturnPanel({ loan }) {
    const { t } = useLocale();
    const [open, setOpen] = useState(false);
    const f = useForm({ condition: 'good', fine_amount: '', note: '' });
    f.transform((d) => ({ condition: d.condition, fine_amount: d.condition === 'good' || d.fine_amount === '' ? null : Number(d.fine_amount), note: d.note || null }));

    if (!open) {
        return (
            <button type="button" className="btn-teal" onClick={() => setOpen(true)}>
                {t('lib.loan.return')}
            </button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                f.post(route('library.loans.return', loan.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
            }}
            className="w-full min-w-[16rem] max-w-sm whitespace-normal rounded-lg border border-slate-200 bg-slate-50 p-3"
        >
            <ErrorSummary errors={f.errors} />
            <Field label={t('lib.loan.condition')}>
                <Select value={f.data.condition} onChange={(e) => f.setData('condition', e.target.value)}>
                    <option value="good">{t('lib.condition.good')}</option>
                    <option value="damaged">{t('lib.condition.damaged')}</option>
                    <option value="lost">{t('lib.condition.lost')}</option>
                </Select>
            </Field>
            {f.data.condition !== 'good' ? (
                <>
                    <Field label={t('lib.loan.fineAmount')} hint={t('lib.loan.fineHint')} className="mt-2">
                        <Input type="number" min="0" step="1" value={f.data.fine_amount} onChange={(e) => f.setData('fine_amount', e.target.value)} />
                    </Field>
                    <Field label={t('acad.common.notes')} className="mt-2">
                        <Input value={f.data.note} maxLength={190} onChange={(e) => f.setData('note', e.target.value)} />
                    </Field>
                </>
            ) : null}
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="submit" className="btn-teal" disabled={f.processing}>
                    {t('lib.loan.return')}
                </button>
                <button type="button" className="btn-outline" onClick={() => setOpen(false)}>
                    {t('acad.common.cancel')}
                </button>
            </div>
        </form>
    );
}

export default function Loans(props) {
    const { loans, view = 'out', books = [], students = [], staff = [], rules = {} } = props;
    const { t, locale } = useLocale();

    const f = useForm({ library_book_id: '', borrower: 'student', student_id: '', staff_id: '', due_on: '' });
    f.transform((d) => ({
        library_book_id: d.library_book_id,
        student_id: d.borrower === 'student' ? d.student_id : null,
        staff_id: d.borrower === 'staff' ? d.staff_id : null,
        due_on: d.due_on || null,
    }));

    const columns = [
        { key: 'book', label: t('lib.col.title') },
        { key: 'accession', label: t('lib.col.accession') },
        { key: 'borrower', label: t('lib.col.borrower') },
        { key: 'loaned_on', label: t('lib.col.loanedOn'), render: (r) => fmtDay(r.loaned_on, locale) },
        { key: 'due_on', label: t('lib.col.dueOn'), render: (r) => fmtDay(r.due_on, locale) },
        ...(view === 'returned' ? [{ key: 'returned_on', label: t('lib.col.returnedOn'), render: (r) => fmtDay(r.returned_on, locale) }] : []),
        { key: 'status', label: t('lib.col.status'), render: (r) => <StatusBadge status={r.status} label={r.days_late > 0 ? `${enumLabel(t, 'lib.loan', r.status)} (${r.days_late})` : enumLabel(t, 'lib.loan', r.status)} /> },
        {
            key: 'actions',
            label: '',
            cellClassName: 'whitespace-normal',
            render: (r) =>
                r.status === 'out' || r.status === 'overdue' ? (
                    <div className="flex flex-wrap items-start gap-2">
                        <ReturnPanel loan={r} />
                        {r.status === 'out' ? (
                            <button type="button" className="btn-outline" onClick={() => router.post(route('library.loans.renew', r.id), {}, { preserveScroll: true })}>
                                {t('lib.loan.renew')}
                            </button>
                        ) : null}
                    </div>
                ) : null,
        },
    ];

    return (
        <LibraryLayout
            title={t('lib.loans.title')}
            subtitle={t('lib.loans.subtitle')}
            current="loans"
            shell={props}
            actions={
                <a href={route('library.overdue.export')} className="btn-outline">
                    {t('lib.loans.exportOverdue')}
                </a>
            }
        >
            <div className="mb-4 flex flex-wrap gap-2">
                {VIEWS.map((v) => (
                    <button key={v} type="button" className={view === v ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('library.loans.index'), { view: v }, { preserveState: true, replace: true })}>
                        {t(`lib.view.${v}`)}
                    </button>
                ))}
            </div>

            <DataTable columns={columns} rows={loans?.data ?? []} emptyMessage={t('lib.loans.empty')} />
            <Pager links={loans?.links} />

            <Card title={t('lib.loans.lend')} className="mt-8 max-w-3xl">
                <p className="mb-3 text-sm text-slate-600">{t('lib.loans.rulesLine', { days: rules.loan_days, students: rules.max_loans_student, staff: rules.max_loans_staff })}</p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(route('library.loans.store'), { preserveScroll: true, onSuccess: () => f.reset('library_book_id', 'student_id', 'staff_id', 'due_on') });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('lib.col.title')}>
                            <Select value={f.data.library_book_id} onChange={(e) => f.setData('library_book_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {books.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.title} ({b.copies_available})
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('lib.loans.borrowerType')}>
                            <Select value={f.data.borrower} onChange={(e) => f.setData('borrower', e.target.value)}>
                                <option value="student">{t('lib.borrower.student')}</option>
                                <option value="staff">{t('lib.borrower.staff')}</option>
                            </Select>
                        </Field>
                        {f.data.borrower === 'student' ? (
                            <Field label={t('lib.borrower.student')}>
                                <Select value={f.data.student_id} onChange={(e) => f.setData('student_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {students.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        ) : (
                            <Field label={t('lib.borrower.staff')}>
                                <Select value={f.data.staff_id} onChange={(e) => f.setData('staff_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {staff.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        )}
                        <Field label={t('lib.col.dueOn')} hint={t('lib.loans.dueHint')}>
                            <Input type="date" value={f.data.due_on} onChange={(e) => f.setData('due_on', e.target.value)} />
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('lib.loans.lendSave')}
                    </button>
                </form>
            </Card>
        </LibraryLayout>
    );
}
