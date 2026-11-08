import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { ADJUSTMENT_KINDS, money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';

const STATUSES = ['pending', 'approved', 'rejected', 'revoked'];

export default function Adjustments(props) {
    const { adjustments = [], filter = '', students = [], categories = [], years = [], terms = [], can = {} } = props;
    const { t, locale } = useLocale();

    const f = useForm({ student_id: '', kind: 'discount', basis: 'percent', value: '', fee_category_id: '', academic_year_id: '', term_id: '', reason: '' });
    f.transform((d) => ({
        ...d,
        value: d.value === '' ? '' : Number(d.value),
        fee_category_id: d.fee_category_id || null,
        academic_year_id: d.academic_year_id || null,
        term_id: d.term_id || null,
    }));
    const yearTerms = terms.filter((x) => String(x.academic_year_id) === String(f.data.academic_year_id));

    const value = (r) => (r.basis === 'percent' ? `${r.value}%` : money(r.value));

    const columns = [
        { key: 'created_at', label: t('fin.col.date'), render: (r) => fmtDay(r.created_at, locale) },
        { key: 'student', label: t('fin.col.student'), render: (r) => `${r.student ?? ''} (${r.student_number ?? ''})` },
        { key: 'kind', label: t('fin.col.kind'), render: (r) => enumLabel(t, 'fin.kind', r.kind) },
        { key: 'value', label: t('fin.adj.value'), render: value },
        { key: 'category', label: t('fin.manual.category'), render: (r) => r.category ?? t('fin.adj.allCategories') },
        { key: 'reason', label: t('acad.common.reason'), cellClassName: 'max-w-xs whitespace-normal', render: (r) => <span title={r.decision_note ?? ''}>{r.reason}</span> },
        { key: 'requester', label: t('fin.adj.requestedBy') },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex flex-wrap items-start gap-2 whitespace-normal">
                    {r.status === 'pending' && can.approve ? (
                        <>
                            <ReasonAction url={route('fees.adjustments.approve', r.id)} fieldName="note" required={false} label={t('acad.common.approve')} tone="teal" reasonLabel={t('fin.adj.noteOptional')} />
                            <ReasonAction url={route('fees.adjustments.reject', r.id)} fieldName="note" label={t('acad.common.reject')} tone="danger" />
                        </>
                    ) : null}
                    {r.status === 'approved' && can.approve ? <ReasonAction url={route('fees.adjustments.revoke', r.id)} fieldName="note" label={t('fin.adj.revoke')} tone="danger" /> : null}
                </div>
            ),
        },
    ];

    return (
        <FinanceLayout title={t('fin.adj.title')} subtitle={t('fin.adj.subtitle')} current="adjustments" shell={props}>
            <div className="mb-4 flex flex-wrap gap-2">
                {['', ...STATUSES].map((s) => (
                    <button
                        key={s || 'all'}
                        type="button"
                        onClick={() => router.get(route('fees.adjustments.index'), s ? { status: s } : {}, { preserveState: true, replace: true })}
                        className={filter === s ? 'btn-navy' : 'btn-outline'}
                    >
                        {s ? t(`fin.status.${s}`) : t('acad.common.all')}
                    </button>
                ))}
            </div>

            <DataTable columns={columns} rows={adjustments} emptyMessage={t('fin.adj.empty')} />
            <p className="mt-2 text-xs text-slate-500">{t('fin.adj.appliesNote')}</p>

            {can.manage ? (
                <Card title={t('fin.adj.request')} className="mt-8 max-w-3xl">
                    <p className="mb-3 text-sm text-slate-600">{t('fin.adj.makerChecker')}</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(route('fees.adjustments.store'), { preserveScroll: true, onSuccess: () => f.reset('value', 'reason', 'student_id') });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={f.errors} />
                        <Field label={t('fin.col.student')}>
                            <Select value={f.data.student_id} onChange={(e) => f.setData('student_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {students.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} ({s.student_number})
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label={t('fin.col.kind')}>
                                <Select value={f.data.kind} onChange={(e) => f.setData('kind', e.target.value)}>
                                    {ADJUSTMENT_KINDS.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`fin.kind.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('fin.adj.basis')}>
                                <Select value={f.data.basis} onChange={(e) => f.setData('basis', e.target.value)}>
                                    <option value="percent">{t('fin.adj.basis.percent')}</option>
                                    <option value="fixed">{t('fin.adj.basis.fixed')}</option>
                                </Select>
                            </Field>
                            <Field label={t('fin.adj.value')}>
                                <Input type="number" min="1" max={f.data.basis === 'percent' ? 100 : undefined} step="1" value={f.data.value} onChange={(e) => f.setData('value', e.target.value)} required />
                            </Field>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label={t('fin.manual.category')} hint={t('fin.adj.categoryHint')}>
                                <Select value={f.data.fee_category_id} onChange={(e) => f.setData('fee_category_id', e.target.value)}>
                                    <option value="">{t('fin.adj.allCategories')}</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('fin.generate.year')}>
                                <Select value={f.data.academic_year_id} onChange={(e) => f.setData((d) => ({ ...d, academic_year_id: e.target.value, term_id: '' }))}>
                                    <option value="">{t('fin.adj.anyYear')}</option>
                                    {years.map((y) => (
                                        <option key={y.id} value={y.id}>
                                            {y.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('fin.generate.term')}>
                                <Select value={f.data.term_id} onChange={(e) => f.setData('term_id', e.target.value)} disabled={!f.data.academic_year_id}>
                                    <option value="">{t('fin.adj.anyTerm')}</option>
                                    {yearTerms.map((x) => (
                                        <option key={x.id} value={x.id}>
                                            {x.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                        <Field label={t('acad.common.reason')}>
                            <Textarea rows={2} maxLength={1000} value={f.data.reason} onChange={(e) => f.setData('reason', e.target.value)} required />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={f.processing}>
                            {t('fin.adj.submit')}
                        </button>
                    </form>
                </Card>
            ) : null}
        </FinanceLayout>
    );
}
