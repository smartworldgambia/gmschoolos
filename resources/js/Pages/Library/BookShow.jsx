import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import LibraryLayout from '@/Components/SchoolOS/LibraryLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { Link, router, useForm } from '@inertiajs/react';

const SETTABLE = ['available', 'damaged', 'withdrawn', 'lost'];

export default function BookShow(props) {
    const { book, copies = [], history = [] } = props;
    const { t, locale } = useLocale();

    const edit = useForm({
        title: book.title ?? '',
        author: book.author ?? '',
        isbn: book.isbn ?? '',
        category: book.category ?? '',
        publisher: book.publisher ?? '',
        publication_year: book.publication_year ?? '',
        language: book.language ?? '',
        shelf_location: book.shelf_location ?? '',
    });
    edit.transform((d) => ({ ...d, publication_year: d.publication_year === '' ? null : Number(d.publication_year) }));
    const more = useForm({ count: 1, acquired_on: '' });
    more.transform((d) => ({ ...d, count: Number(d.count), acquired_on: d.acquired_on || null }));

    const copyColumns = [
        { key: 'accession_number', label: t('lib.col.accession') },
        { key: 'status', label: t('lib.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'lib.copy', r.status)} /> },
        { key: 'condition_note', label: t('lib.col.condition') },
        { key: 'acquired_on', label: t('lib.col.acquired'), render: (r) => (r.acquired_on ? fmtDay(r.acquired_on, locale) : '—') },
        {
            key: 'actions',
            label: '',
            cellClassName: 'whitespace-normal',
            render: (r) =>
                r.status === 'on_loan' ? (
                    <span className="text-xs text-slate-500">{t('lib.copy.returnFirst')}</span>
                ) : (
                    <Select
                        className="!w-40"
                        value={r.status}
                        aria-label={t('lib.copy.change')}
                        onChange={(e) => router.put(route('library.copies.status', r.id), { status: e.target.value }, { preserveScroll: true })}
                    >
                        {SETTABLE.map((s) => (
                            <option key={s} value={s}>
                                {t(`lib.copy.${s}`)}
                            </option>
                        ))}
                    </Select>
                ),
        },
    ];

    const historyColumns = [
        { key: 'accession', label: t('lib.col.accession') },
        { key: 'borrower', label: t('lib.col.borrower') },
        { key: 'loaned_on', label: t('lib.col.loanedOn'), render: (r) => fmtDay(r.loaned_on, locale) },
        { key: 'due_on', label: t('lib.col.dueOn'), render: (r) => fmtDay(r.due_on, locale) },
        { key: 'returned_on', label: t('lib.col.returnedOn'), render: (r) => (r.returned_on ? fmtDay(r.returned_on, locale) : '—') },
        { key: 'status', label: t('lib.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'lib.loan', r.status)} /> },
    ];

    return (
        <LibraryLayout
            title={book.title}
            subtitle={[book.author, book.isbn].filter(Boolean).join(' · ')}
            current="catalogue"
            shell={props}
            actions={
                <Link href={route('library.index')} className="btn-outline">
                    {t('acad.common.back')}
                </Link>
            }
        >
            <h2 className="mb-3 text-base font-semibold text-navy">
                {t('lib.copies.title')} ({book.copies_available} / {book.copies_total})
            </h2>
            <DataTable columns={copyColumns} rows={copies} emptyMessage={t('lib.copies.empty')} />

            <Card title={t('lib.copies.add')} className="mt-4 max-w-xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        more.post(route('library.copies.store', book.id), { preserveScroll: true, onSuccess: () => more.reset() });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={more.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('lib.form.copies')}>
                            <Input type="number" min="1" max="200" value={more.data.count} onChange={(e) => more.setData('count', e.target.value)} required />
                        </Field>
                        <Field label={t('lib.col.acquired')}>
                            <Input type="date" value={more.data.acquired_on} onChange={(e) => more.setData('acquired_on', e.target.value)} />
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={more.processing}>
                        {t('lib.copies.addSave')}
                    </button>
                </form>
            </Card>

            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('lib.history.title')}</h2>
            <DataTable columns={historyColumns} rows={history} emptyMessage={t('lib.history.empty')} />

            <Card title={t('lib.editTitle')} className="mt-8 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        edit.put(route('library.books.update', book.id), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={edit.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('lib.col.title')}>
                            <Input value={edit.data.title} onChange={(e) => edit.setData('title', e.target.value)} maxLength={200} required />
                        </Field>
                        <Field label={t('lib.col.author')}>
                            <Input value={edit.data.author} onChange={(e) => edit.setData('author', e.target.value)} />
                        </Field>
                        <Field label={t('lib.col.isbn')}>
                            <Input value={edit.data.isbn} onChange={(e) => edit.setData('isbn', e.target.value)} />
                        </Field>
                        <Field label={t('lib.col.category')}>
                            <Input value={edit.data.category} onChange={(e) => edit.setData('category', e.target.value)} />
                        </Field>
                        <Field label={t('lib.form.publisher')}>
                            <Input value={edit.data.publisher} onChange={(e) => edit.setData('publisher', e.target.value)} />
                        </Field>
                        <Field label={t('lib.form.year')}>
                            <Input type="number" value={edit.data.publication_year} onChange={(e) => edit.setData('publication_year', e.target.value)} />
                        </Field>
                        <Field label={t('lib.form.language')}>
                            <Input value={edit.data.language} onChange={(e) => edit.setData('language', e.target.value)} />
                        </Field>
                        <Field label={t('lib.col.shelf')}>
                            <Input value={edit.data.shelf_location} onChange={(e) => edit.setData('shelf_location', e.target.value)} />
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={edit.processing}>
                        {t('acad.common.save')}
                    </button>
                </form>
            </Card>
        </LibraryLayout>
    );
}
