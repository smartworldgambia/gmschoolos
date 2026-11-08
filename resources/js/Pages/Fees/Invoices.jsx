import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, ErrorSummary, Field, Input, Pager, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['issued', 'partial', 'paid', 'overdue', 'cancelled'];

export default function Invoices(props) {
    const { invoices, filters = {}, classes = [], years = [], terms = [], currentYearId, students = [], categories = [], optionalLines = [], can = {} } = props;
    const { t, locale } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');

    const filter = (patch) => router.get(route('fees.invoices.index'), { ...filters, ...patch }, { preserveState: true, replace: true });

    const gen = useForm({ target: 'student', student_id: '', school_class_id: '', academic_year_id: currentYearId ?? '', term_id: '', optional_ids: [] });
    gen.transform((d) => ({
        academic_year_id: d.academic_year_id,
        term_id: d.term_id || null,
        student_id: d.target === 'student' ? d.student_id || null : null,
        school_class_id: d.target === 'class' ? d.school_class_id || null : null,
        optional_ids: d.target === 'student' ? d.optional_ids : [],
    }));

    const yearTerms = terms.filter((x) => String(x.academic_year_id) === String(gen.data.academic_year_id));
    const offered = optionalLines.filter(
        (o) => String(o.academic_year_id) === String(gen.data.academic_year_id) && (!o.term_id || String(o.term_id) === String(gen.data.term_id)),
    );
    const toggleOptional = (id) =>
        gen.setData('optional_ids', gen.data.optional_ids.includes(id) ? gen.data.optional_ids.filter((x) => x !== id) : [...gen.data.optional_ids, id]);

    const manual = useForm({ student_id: '', due_on: '', notes: '', lines: [{ description: '', amount: '', fee_category_id: '' }] });
    manual.transform((d) => ({
        ...d,
        due_on: d.due_on || null,
        lines: d.lines.map((l) => ({ ...l, amount: l.amount === '' ? '' : Number(l.amount), fee_category_id: l.fee_category_id || null })),
    }));
    const setLine = (i, patch) => manual.setData('lines', manual.data.lines.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));

    const columns = [
        {
            key: 'invoice_number',
            label: t('fin.col.invoice'),
            render: (r) => (
                <Link href={route('fees.invoices.show', r.id)} className="font-medium text-teal-700 hover:underline">
                    {r.invoice_number}
                </Link>
            ),
        },
        { key: 'student', label: t('fin.col.student') },
        { key: 'class', label: t('fin.col.class') },
        { key: 'total_amount', label: t('fin.col.total'), render: (r) => money(r.total_amount) },
        { key: 'balance', label: t('fin.col.balance'), render: (r) => money(r.balance) },
        { key: 'due_on', label: t('fin.col.due'), render: (r) => fmtDay(r.due_on, locale) || '—' },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
    ];

    return (
        <FinanceLayout title={t('fin.invoices.title')} subtitle={t('fin.invoices.subtitle')} current="invoices" shell={props}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    filter({ q, page: undefined });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')} className="min-w-[14rem] flex-1">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('fin.invoices.searchPlaceholder')} />
                </Field>
                <Field label={t('fin.col.status')}>
                    <Select value={filters.status ?? ''} onChange={(e) => filter({ status: e.target.value, page: undefined })}>
                        <option value="">{t('acad.common.all')}</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {t(`fin.status.${s}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('fin.col.class')}>
                    <Select value={filters.class ?? ''} onChange={(e) => filter({ class: e.target.value, page: undefined })}>
                        <option value="">{t('acad.common.all')}</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <button type="submit" className="btn-navy">
                    {t('acad.common.search')}
                </button>
            </form>

            <DataTable columns={columns} rows={invoices.data} emptyMessage={t('fin.invoices.empty')} />
            <Pager links={invoices.links} />

            {can.manage ? (
                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <Card title={t('fin.generate.title')}>
                        <p className="mb-3 text-sm text-slate-600">{t('fin.generate.intro')}</p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                gen.post(route('fees.invoices.generate'), { preserveScroll: true });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={gen.errors} />
                            <Field label={t('fin.generate.target')}>
                                <Select value={gen.data.target} onChange={(e) => gen.setData('target', e.target.value)}>
                                    <option value="student">{t('fin.generate.oneStudent')}</option>
                                    <option value="class">{t('fin.generate.wholeClass')}</option>
                                </Select>
                            </Field>
                            {gen.data.target === 'student' ? (
                                <Field label={t('fin.col.student')}>
                                    <Select value={gen.data.student_id} onChange={(e) => gen.setData('student_id', e.target.value)} required>
                                        <option value="">{t('acad.common.choose')}</option>
                                        {students.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name} ({s.student_number})
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            ) : (
                                <Field label={t('fin.col.class')}>
                                    <Select value={gen.data.school_class_id} onChange={(e) => gen.setData('school_class_id', e.target.value)} required>
                                        <option value="">{t('acad.common.choose')}</option>
                                        {classes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('fin.generate.year')}>
                                    <Select value={gen.data.academic_year_id} onChange={(e) => gen.setData((d) => ({ ...d, academic_year_id: e.target.value, term_id: '', optional_ids: [] }))} required>
                                        <option value="">{t('acad.common.choose')}</option>
                                        {years.map((y) => (
                                            <option key={y.id} value={y.id}>
                                                {y.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('fin.generate.term')} hint={t('fin.generate.termHint')}>
                                    <Select value={gen.data.term_id} onChange={(e) => gen.setData((d) => ({ ...d, term_id: e.target.value, optional_ids: [] }))}>
                                        <option value="">{t('fin.generate.wholeYear')}</option>
                                        {yearTerms.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            {gen.data.target === 'student' && offered.length > 0 ? (
                                <fieldset className="rounded-lg border border-slate-200 p-3">
                                    <legend className="px-1 text-sm font-medium text-slate-700">{t('fin.generate.optional')}</legend>
                                    {offered.map((o) => (
                                        <label key={o.id} className="flex items-center gap-2 py-1 text-sm">
                                            <input type="checkbox" checked={gen.data.optional_ids.includes(o.id)} onChange={() => toggleOptional(o.id)} />
                                            <span>
                                                {o.name} — {money(o.amount)}
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                            ) : null}
                            <button type="submit" className="btn-teal" disabled={gen.processing}>
                                {t('fin.generate.submit')}
                            </button>
                        </form>
                    </Card>

                    <Card title={t('fin.manual.title')}>
                        <p className="mb-3 text-sm text-slate-600">{t('fin.manual.intro')}</p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                manual.post(route('fees.invoices.store'), { preserveScroll: true });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={manual.errors} />
                            <Field label={t('fin.col.student')}>
                                <Select value={manual.data.student_id} onChange={(e) => manual.setData('student_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {students.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.student_number})
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {manual.data.lines.map((l, i) => (
                                <div key={i} className="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-6">
                                    <Field label={t('fin.manual.description')} className="sm:col-span-3">
                                        <Input value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} maxLength={190} required />
                                    </Field>
                                    <Field label={t('fin.col.amount')} className="sm:col-span-2">
                                        <Input type="number" min="1" step="1" value={l.amount} onChange={(e) => setLine(i, { amount: e.target.value })} required />
                                    </Field>
                                    <div className="flex items-end sm:col-span-1">
                                        {manual.data.lines.length > 1 ? (
                                            <button type="button" className="btn-outline" onClick={() => manual.setData('lines', manual.data.lines.filter((_, idx) => idx !== i))}>
                                                {t('acad.common.remove')}
                                            </button>
                                        ) : null}
                                    </div>
                                    <Field label={t('fin.manual.category')} className="sm:col-span-6">
                                        <Select value={l.fee_category_id} onChange={(e) => setLine(i, { fee_category_id: e.target.value })}>
                                            <option value="">{t('acad.common.none')}</option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                </div>
                            ))}
                            {manual.data.lines.length < 30 ? (
                                <button type="button" className="btn-outline" onClick={() => manual.setData('lines', [...manual.data.lines, { description: '', amount: '', fee_category_id: '' }])}>
                                    {t('fin.manual.addLine')}
                                </button>
                            ) : null}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('fin.col.due')} hint={t('fin.manual.dueHint')}>
                                    <Input type="date" value={manual.data.due_on} onChange={(e) => manual.setData('due_on', e.target.value)} />
                                </Field>
                            </div>
                            <Field label={t('acad.common.notes')}>
                                <Textarea rows={2} maxLength={500} value={manual.data.notes} onChange={(e) => manual.setData('notes', e.target.value)} />
                            </Field>
                            <button type="submit" className="btn-teal" disabled={manual.processing}>
                                {t('fin.manual.submit')}
                            </button>
                        </form>
                    </Card>
                </div>
            ) : null}
        </FinanceLayout>
    );
}
