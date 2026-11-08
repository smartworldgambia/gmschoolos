import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Stat } from '@/Components/SchoolOS/Form';
import LibraryLayout from '@/Components/SchoolOS/LibraryLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index(props) {
    const { books, q = '', stats = {} } = props;
    const { t } = useLocale();
    const [search, setSearch] = useState(q);

    const f = useForm({ title: '', author: '', isbn: '', category: '', publisher: '', publication_year: '', language: '', shelf_location: '', copies_total: 1 });
    f.transform((d) => ({ ...d, publication_year: d.publication_year === '' ? null : Number(d.publication_year), copies_total: Number(d.copies_total) }));

    const columns = [
        {
            key: 'title',
            label: t('lib.col.title'),
            render: (r) => (
                <Link href={route('library.books.show', r.id)} className="font-medium text-teal-700 hover:underline">
                    {r.title}
                </Link>
            ),
        },
        { key: 'author', label: t('lib.col.author') },
        { key: 'isbn', label: t('lib.col.isbn') },
        { key: 'category', label: t('lib.col.category') },
        { key: 'shelf_location', label: t('lib.col.shelf') },
        { key: 'copies', label: t('lib.col.copies'), render: (r) => `${r.copies_available} / ${r.copies_total}` },
    ];

    return (
        <LibraryLayout title={t('lib.title')} subtitle={t('lib.subtitle')} current="catalogue" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-5">
                <Stat label={t('lib.stat.titles')} value={stats.titles} />
                <Stat label={t('lib.stat.available')} value={stats.available} tone="green" />
                <Stat label={t('lib.stat.out')} value={stats.out} />
                <Stat label={t('lib.stat.overdue')} value={stats.overdue} tone={stats.overdue > 0 ? 'red' : 'navy'} />
                <Stat label={t('lib.stat.unpaidFines')} value={money(stats.unpaidFines)} />
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('library.index'), search ? { q: search } : {}, { preserveState: true, replace: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')}>
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('lib.searchPlaceholder')} />
                </Field>
                <button type="submit" className="btn-navy">
                    {t('acad.common.search')}
                </button>
            </form>

            <DataTable columns={columns} rows={books?.data ?? []} emptyMessage={t('lib.empty')} />
            <Pager links={books?.links} />

            <Card title={t('lib.addTitle')} className="mt-8 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(route('library.books.store'), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('lib.col.title')}>
                            <Input value={f.data.title} onChange={(e) => f.setData('title', e.target.value)} maxLength={200} required />
                        </Field>
                        <Field label={t('lib.col.author')}>
                            <Input value={f.data.author} onChange={(e) => f.setData('author', e.target.value)} maxLength={150} />
                        </Field>
                        <Field label={t('lib.col.isbn')} hint={t('lib.isbnHint')}>
                            <Input value={f.data.isbn} onChange={(e) => f.setData('isbn', e.target.value)} maxLength={40} />
                        </Field>
                        <Field label={t('lib.col.category')}>
                            <Input value={f.data.category} onChange={(e) => f.setData('category', e.target.value)} maxLength={80} />
                        </Field>
                        <Field label={t('lib.form.publisher')}>
                            <Input value={f.data.publisher} onChange={(e) => f.setData('publisher', e.target.value)} maxLength={150} />
                        </Field>
                        <Field label={t('lib.form.year')}>
                            <Input type="number" min="1400" value={f.data.publication_year} onChange={(e) => f.setData('publication_year', e.target.value)} />
                        </Field>
                        <Field label={t('lib.form.language')}>
                            <Input value={f.data.language} onChange={(e) => f.setData('language', e.target.value)} maxLength={40} />
                        </Field>
                        <Field label={t('lib.col.shelf')}>
                            <Input value={f.data.shelf_location} onChange={(e) => f.setData('shelf_location', e.target.value)} maxLength={60} />
                        </Field>
                        <Field label={t('lib.form.copies')} hint={t('lib.form.copiesHint')}>
                            <Input type="number" min="1" max="200" value={f.data.copies_total} onChange={(e) => f.setData('copies_total', e.target.value)} required />
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('lib.addTitleSave')}
                    </button>
                </form>
            </Card>
        </LibraryLayout>
    );
}
