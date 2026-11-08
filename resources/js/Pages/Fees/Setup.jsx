import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { SCHOOL_METHODS, money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const activeBadge = (t, on) => <StatusBadge status={on ? 'active' : 'inactive'} label={on ? t('fin.setup.active') : t('fin.setup.inactive')} />;

function Rules({ settings }) {
    const { t } = useLocale();
    const f = useForm({ ...settings, receipt_footer: settings.receipt_footer ?? '' });
    const num = (k) => ({ type: 'number', value: f.data[k], onChange: (e) => f.setData(k, e.target.value === '' ? '' : Number(e.target.value)) });
    const flag = (k, label, hint) => (
        <label className="flex items-start gap-2 text-sm">
            <input type="checkbox" className="mt-1" checked={!!f.data[k]} onChange={(e) => f.setData(k, e.target.checked)} />
            <span>
                <span className="font-medium text-slate-800">{label}</span>
                {hint ? <span className="block text-xs text-slate-500">{hint}</span> : null}
            </span>
        </label>
    );

    return (
        <Card title={t('fin.rules.title')}>
            <p className="mb-3 text-sm text-slate-600">{t('fin.rules.intro')}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    f.put(route('fees.setup.settings'), { preserveScroll: true });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('fin.rules.prefix')} hint={t('fin.rules.prefixHint')}>
                        <Input maxLength={10} value={f.data.invoice_prefix} onChange={(e) => f.setData('invoice_prefix', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.rules.dueDays')} hint={t('fin.rules.dueDaysHint')}>
                        <Input min="0" max="365" {...num('due_days')} required />
                    </Field>
                    <Field label={t('fin.rules.reminderDays')} hint={t('fin.rules.reminderDaysHint')}>
                        <Input min="1" max="90" {...num('reminder_days')} required />
                    </Field>
                    <Field label={t('fin.rules.reminderMax')} hint={t('fin.rules.reminderMaxHint')}>
                        <Input min="0" max="12" {...num('reminder_max')} required />
                    </Field>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    {flag('allow_installments', t('fin.rules.installments'), t('fin.rules.installmentsHint'))}
                    <Field label={t('fin.rules.installmentsMax')}>
                        <Input min="2" max="12" {...num('installments_max')} required />
                    </Field>
                    {flag('parent_submissions', t('fin.rules.submissions'), t('fin.rules.submissionsHint'))}
                    {flag('self_approval', t('fin.rules.selfApproval'), t('fin.rules.selfApprovalHint'))}
                </div>
                <Field label={t('fin.rules.footer')} hint={t('fin.rules.footerHint')}>
                    <Input maxLength={200} value={f.data.receipt_footer} onChange={(e) => f.setData('receipt_footer', e.target.value)} />
                </Field>
                <button type="submit" className="btn-teal" disabled={f.processing}>
                    {t('acad.common.save')}
                </button>
            </form>
        </Card>
    );
}

function Categories({ categories }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(null);
    const f = useForm({ name: '', code: '', description: '' });
    const reset = () => {
        setEditing(null);
        f.reset();
        f.clearErrors();
    };
    const toggle = (c) => router.put(route('fees.setup.categories.update', c.id), { name: c.name, code: c.code ?? '', description: c.description ?? '', is_active: !c.is_active }, { preserveScroll: true });

    return (
        <Card title={t('fin.cat.title')}>
            <DataTable
                columns={[
                    { key: 'name', label: t('fin.cat.name') },
                    { key: 'code', label: t('acad.common.code') },
                    { key: 'structures_count', label: t('fin.cat.lines') },
                    { key: 'is_active', label: t('fin.col.status'), render: (r) => activeBadge(t, r.is_active) },
                    {
                        key: 'actions',
                        label: '',
                        render: (r) => (
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    className="btn-outline"
                                    onClick={() => {
                                        setEditing(r);
                                        f.setData({ name: r.name, code: r.code ?? '', description: r.description ?? '' });
                                    }}
                                >
                                    {t('acad.common.edit')}
                                </button>
                                <button type="button" className="btn-outline" onClick={() => toggle(r)}>
                                    {r.is_active ? t('fin.setup.deactivate') : t('fin.setup.activate')}
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={categories}
                emptyMessage={t('fin.cat.empty')}
            />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    const opts = { preserveScroll: true, onSuccess: reset };
                    if (editing) {
                        f.transform((d) => ({ ...d, is_active: editing.is_active })).put(route('fees.setup.categories.update', editing.id), opts);
                    } else {
                        f.transform((d) => d).post(route('fees.setup.categories.store'), opts);
                    }
                }}
                className="mt-4 space-y-3 border-t border-slate-100 pt-4"
            >
                <h3 className="text-sm font-semibold text-navy">{editing ? t('fin.cat.edit') : t('fin.cat.add')}</h3>
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-3">
                    <Field label={t('fin.cat.name')}>
                        <Input maxLength={120} value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('acad.common.code')}>
                        <Input maxLength={30} value={f.data.code} onChange={(e) => f.setData('code', e.target.value)} />
                    </Field>
                    <Field label={t('fin.cat.description')}>
                        <Input maxLength={250} value={f.data.description} onChange={(e) => f.setData('description', e.target.value)} />
                    </Field>
                </div>
                <div className="flex gap-2">
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('acad.common.save')}
                    </button>
                    {editing ? (
                        <button type="button" className="btn-outline" onClick={reset}>
                            {t('acad.common.cancel')}
                        </button>
                    ) : null}
                </div>
            </form>
        </Card>
    );
}

function Schedule({ structures, categories, years, terms, classes, yearId }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(null);
    const blank = { fee_category_id: '', academic_year_id: yearId ?? '', term_id: '', school_class_id: '', name: '', amount: '', is_optional: false };
    const f = useForm(blank);
    f.transform((d) => ({ ...d, term_id: d.term_id || null, school_class_id: d.school_class_id || null, name: d.name || null, amount: d.amount === '' ? '' : Number(d.amount) }));
    const reset = () => {
        setEditing(null);
        f.setData({ ...blank, academic_year_id: yearId ?? '' });
        f.clearErrors();
    };
    const yearTerms = terms.filter((x) => String(x.academic_year_id) === String(f.data.academic_year_id));

    const columns = [
        { key: 'category', label: t('fin.cat.name'), render: (r) => r.name || r.category },
        { key: 'term', label: t('fin.generate.term'), render: (r) => r.term ?? t('fin.generate.wholeYear') },
        { key: 'class', label: t('fin.col.class'), render: (r) => r.class ?? t('fin.sched.allClasses') },
        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
        { key: 'is_optional', label: t('fin.sched.type'), render: (r) => (r.is_optional ? t('fin.sched.optional') : t('fin.sched.compulsory')) },
        { key: 'is_active', label: t('fin.col.status'), render: (r) => activeBadge(t, r.is_active) },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex gap-2">
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={() => {
                            setEditing(r);
                            f.setData({ fee_category_id: r.category_id, academic_year_id: yearId, term_id: r.term_id ?? '', school_class_id: r.school_class_id ?? '', name: r.name ?? '', amount: r.amount, is_optional: r.is_optional });
                        }}
                    >
                        {t('acad.common.edit')}
                    </button>
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={() => {
                            if (window.confirm(t('fin.sched.confirmRemove'))) {
                                router.delete(route('fees.setup.structures.destroy', r.id), { preserveScroll: true });
                            }
                        }}
                    >
                        {t('acad.common.remove')}
                    </button>
                </div>
            ),
        },
    ];

    return (
        <Card
            title={t('fin.sched.title')}
            actions={
                <Select value={yearId ?? ''} onChange={(e) => router.get(route('fees.setup.index'), { year: e.target.value }, { preserveState: true, replace: true })} aria-label={t('fin.generate.year')}>
                    {years.map((y) => (
                        <option key={y.id} value={y.id}>
                            {y.name}
                        </option>
                    ))}
                </Select>
            }
        >
            <p className="mb-3 text-sm text-slate-600">{t('fin.sched.intro')}</p>
            <DataTable columns={columns} rows={structures} emptyMessage={t('fin.sched.empty')} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    const opts = { preserveScroll: true, onSuccess: reset };
                    if (editing) {
                        f.transform((d) => ({ ...d, term_id: d.term_id || null, school_class_id: d.school_class_id || null, name: d.name || null, amount: Number(d.amount), is_active: editing.is_active })).put(route('fees.setup.structures.update', editing.id), opts);
                    } else {
                        f.post(route('fees.setup.structures.store'), opts);
                    }
                }}
                className="mt-4 space-y-3 border-t border-slate-100 pt-4"
            >
                <h3 className="text-sm font-semibold text-navy">{editing ? t('fin.sched.edit') : t('fin.sched.add')}</h3>
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('fin.cat.name')}>
                        <Select value={f.data.fee_category_id} onChange={(e) => f.setData('fee_category_id', e.target.value)} required>
                            <option value="">{t('acad.common.choose')}</option>
                            {categories.filter((c) => c.is_active || c.id === f.data.fee_category_id).map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.generate.year')}>
                        <Select value={f.data.academic_year_id} onChange={(e) => f.setData((d) => ({ ...d, academic_year_id: e.target.value, term_id: '' }))} required>
                            {years.map((y) => (
                                <option key={y.id} value={y.id}>
                                    {y.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.generate.term')}>
                        <Select value={f.data.term_id} onChange={(e) => f.setData('term_id', e.target.value)}>
                            <option value="">{t('fin.generate.wholeYear')}</option>
                            {yearTerms.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.col.class')}>
                        <Select value={f.data.school_class_id} onChange={(e) => f.setData('school_class_id', e.target.value)}>
                            <option value="">{t('fin.sched.allClasses')}</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.col.amount')}>
                        <Input type="number" min="1" step="1" value={f.data.amount} onChange={(e) => f.setData('amount', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.sched.label')} hint={t('fin.sched.labelHint')}>
                        <Input maxLength={150} value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                        <input type="checkbox" checked={f.data.is_optional} onChange={(e) => f.setData('is_optional', e.target.checked)} />
                        {t('fin.sched.optionalLine')}
                    </label>
                </div>
                <div className="flex gap-2">
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('acad.common.save')}
                    </button>
                    {editing ? (
                        <button type="button" className="btn-outline" onClick={reset}>
                            {t('acad.common.cancel')}
                        </button>
                    ) : null}
                </div>
            </form>
        </Card>
    );
}

function Methods({ methods }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(null);
    const blank = { method: 'wave', label: '', instructions: '', account_name: '', account_number: '', bank_name: '', sort_order: 0 };
    const f = useForm(blank);
    const reset = () => {
        setEditing(null);
        f.setData(blank);
        f.clearErrors();
    };
    const payload = (m, on) => ({ method: m.method, label: m.label, instructions: m.instructions ?? '', account_name: m.account_name ?? '', account_number: m.account_number ?? '', bank_name: m.bank_name ?? '', sort_order: m.sort_order ?? 0, is_active: on });

    return (
        <Card title={t('fin.methods.title')}>
            <p className="mb-3 text-sm text-slate-600">{t('fin.methods.intro')}</p>
            <DataTable
                columns={[
                    { key: 'label', label: t('fin.methods.label') },
                    { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
                    { key: 'account_number', label: t('fin.methods.account'), render: (r) => (r.account_number ? <span dir="ltr">{r.account_number}</span> : null) },
                    { key: 'is_active', label: t('fin.col.status'), render: (r) => activeBadge(t, r.is_active) },
                    {
                        key: 'actions',
                        label: '',
                        render: (r) => (
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    className="btn-outline"
                                    onClick={() => {
                                        setEditing(r);
                                        f.setData({ method: r.method, label: r.label, instructions: r.instructions ?? '', account_name: r.account_name ?? '', account_number: r.account_number ?? '', bank_name: r.bank_name ?? '', sort_order: r.sort_order ?? 0 });
                                    }}
                                >
                                    {t('acad.common.edit')}
                                </button>
                                <button type="button" className="btn-outline" onClick={() => router.put(route('fees.setup.methods.update', r.id), payload(r, !r.is_active), { preserveScroll: true })}>
                                    {r.is_active ? t('fin.setup.deactivate') : t('fin.setup.activate')}
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={methods}
                emptyMessage={t('fin.methods.empty')}
            />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    const opts = { preserveScroll: true, onSuccess: reset };
                    if (editing) {
                        f.transform((d) => ({ ...d, sort_order: Number(d.sort_order) || 0, is_active: editing.is_active })).put(route('fees.setup.methods.update', editing.id), opts);
                    } else {
                        f.transform((d) => ({ ...d, sort_order: Number(d.sort_order) || 0 })).post(route('fees.setup.methods.store'), opts);
                    }
                }}
                className="mt-4 space-y-3 border-t border-slate-100 pt-4"
            >
                <h3 className="text-sm font-semibold text-navy">{editing ? t('fin.methods.edit') : t('fin.methods.add')}</h3>
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label={t('fin.col.method')}>
                        <Select value={f.data.method} onChange={(e) => f.setData('method', e.target.value)}>
                            {SCHOOL_METHODS.map((m) => (
                                <option key={m} value={m}>
                                    {t(`fin.method.${m}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.methods.label')}>
                        <Input maxLength={120} value={f.data.label} onChange={(e) => f.setData('label', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.methods.accountName')}>
                        <Input maxLength={150} value={f.data.account_name} onChange={(e) => f.setData('account_name', e.target.value)} />
                    </Field>
                    <Field label={t('fin.methods.account')} hint={t('fin.methods.accountHint')}>
                        <Input maxLength={80} dir="ltr" value={f.data.account_number} onChange={(e) => f.setData('account_number', e.target.value)} />
                    </Field>
                    <Field label={t('fin.methods.bank')}>
                        <Input maxLength={120} value={f.data.bank_name} onChange={(e) => f.setData('bank_name', e.target.value)} />
                    </Field>
                    <Field label={t('fin.methods.order')}>
                        <Input type="number" min="0" max="1000" value={f.data.sort_order} onChange={(e) => f.setData('sort_order', e.target.value)} />
                    </Field>
                </div>
                <Field label={t('fin.methods.instructions')}>
                    <Textarea rows={2} maxLength={1000} value={f.data.instructions} onChange={(e) => f.setData('instructions', e.target.value)} />
                </Field>
                <p className="text-xs text-slate-500">{t('fin.methods.noSecrets')}</p>
                <div className="flex gap-2">
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('acad.common.save')}
                    </button>
                    {editing ? (
                        <button type="button" className="btn-outline" onClick={reset}>
                            {t('acad.common.cancel')}
                        </button>
                    ) : null}
                </div>
            </form>
        </Card>
    );
}

export default function Setup(props) {
    const { categories = [], structures = [], years = [], terms = [], classes = [], yearId, methods = [], settings = {} } = props;
    const { t } = useLocale();

    return (
        <FinanceLayout title={t('fin.setup.title')} subtitle={t('fin.setup.subtitle')} current="setup" shell={props}>
            <div className="space-y-6">
                <Rules settings={settings} />
                <Categories categories={categories} />
                <Schedule structures={structures} categories={categories} years={years} terms={terms} classes={classes} yearId={yearId} />
                <Methods methods={methods} />
            </div>
        </FinanceLayout>
    );
}
