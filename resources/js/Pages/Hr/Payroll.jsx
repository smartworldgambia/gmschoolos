import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/** Who an "assigned" component applies to, with an optional per-person value. */
function AssignPanel({ component, staff, current }) {
    const { t } = useLocale();
    const [open, setOpen] = useState(false);
    const initial = Object.fromEntries((current ?? []).map((a) => [a.staff_id, a.value_override ?? '']));
    const [chosen, setChosen] = useState(initial);
    const f = useForm({ assignments: [] });
    f.transform(() => ({
        assignments: Object.entries(chosen).map(([id, v]) => ({ staff_id: Number(id), value_override: v === '' ? null : Number(v) })),
    }));

    if (!open) {
        return (
            <button type="button" className="btn-outline" onClick={() => setOpen(true)}>
                {t('hr.component.manageStaff')}
            </button>
        );
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                f.put(route('hr.components.assign', component.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
            }}
            className="w-full min-w-[18rem] max-w-md whitespace-normal rounded-lg border border-slate-200 bg-slate-50 p-3"
        >
            <ErrorSummary errors={f.errors} />
            <p className="mb-2 text-xs text-slate-500">{t('hr.component.overrideHint')}</p>
            <ul className="max-h-60 space-y-1 overflow-y-auto">
                {staff.map((s) => (
                    <li key={s.id} className="flex items-center justify-between gap-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={s.id in chosen}
                                onChange={(e) =>
                                    setChosen((c) => {
                                        const next = { ...c };
                                        if (e.target.checked) next[s.id] = '';
                                        else delete next[s.id];
                                        return next;
                                    })
                                }
                            />
                            {s.name}
                        </label>
                        {s.id in chosen ? (
                            <Input
                                type="number"
                                min="0"
                                step="any"
                                className="!w-24"
                                value={chosen[s.id]}
                                placeholder={String(component.value)}
                                onChange={(e) => setChosen((c) => ({ ...c, [s.id]: e.target.value }))}
                                aria-label={t('hr.component.override')}
                            />
                        ) : null}
                    </li>
                ))}
            </ul>
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="submit" className="btn-teal" disabled={f.processing}>
                    {t('acad.common.save')}
                </button>
                <button type="button" className="btn-outline" onClick={() => setOpen(false)}>
                    {t('acad.common.cancel')}
                </button>
            </div>
        </form>
    );
}

export default function Payroll(props) {
    const { runs = [], components = [], staff = [], assignments = {}, can = {} } = props;
    const { t, locale } = useLocale();

    const run = useForm({ period_label: '', period_start: '', period_end: '', basic_salary: '', notes: '' });
    run.transform((d) => ({ ...d, basic_salary: d.basic_salary === '' ? null : Number(d.basic_salary), notes: d.notes || null }));

    const comp = useForm({ name: '', code: '', kind: 'allowance', calc: 'fixed', value: '', applies_to: 'all', sort_order: 0 });
    comp.transform((d) => ({ ...d, value: d.value === '' ? '' : Number(d.value), sort_order: Number(d.sort_order) || 0 }));

    const runColumns = [
        {
            key: 'period_label',
            label: t('hr.col.period'),
            render: (r) => (
                <Link href={route('hr.payroll.show', r.id)} className="font-medium text-teal-700 hover:underline">
                    {r.period_label}
                </Link>
            ),
        },
        { key: 'dates', label: t('hr.col.dates'), render: (r) => `${fmtDay(r.period_start, locale)} – ${fmtDay(r.period_end, locale)}` },
        { key: 'items_count', label: t('hr.col.staff') },
        { key: 'gross_total', label: t('hr.col.gross'), render: (r) => money(r.gross_total) },
        { key: 'net_total', label: t('hr.col.net'), render: (r) => money(r.net_total) },
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
    ];

    const compColumns = [
        { key: 'name', label: t('hr.col.name') },
        { key: 'code', label: t('acad.common.code') },
        { key: 'kind', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.compKind', r.kind) },
        { key: 'value', label: t('hr.component.value'), render: (r) => (r.calc === 'percent_of_basic' ? `${r.value}% ${t('hr.component.ofBasic')}` : money(r.value)) },
        { key: 'applies_to', label: t('hr.component.appliesTo'), render: (r) => (r.applies_to === 'assigned' ? t('hr.component.assignedCount', { n: r.assigned }) : t('hr.appliesTo.all')) },
        { key: 'is_active', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.is_active ? 'active' : 'inactive'} label={t(r.is_active ? 'hr.status.active' : 'hr.status.inactive')} /> },
        ...(can.manage
            ? [
                  {
                      key: 'actions',
                      label: '',
                      cellClassName: 'whitespace-normal',
                      render: (r) => (
                          <div className="flex flex-wrap items-start gap-2">
                              <button
                                  type="button"
                                  className="btn-outline"
                                  onClick={() =>
                                      router.put(
                                          route('hr.components.update', r.id),
                                          { name: r.name, code: r.code, kind: r.kind, calc: r.calc, value: r.value, applies_to: r.applies_to, sort_order: r.sort_order, is_active: !r.is_active },
                                          { preserveScroll: true },
                                      )
                                  }
                              >
                                  {r.is_active ? t('hr.component.deactivate') : t('hr.component.activate')}
                              </button>
                              {r.applies_to === 'assigned' ? <AssignPanel component={r} staff={staff} current={assignments[r.id]} /> : null}
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <HrLayout title={t('hr.payroll.title')} subtitle={t('hr.payroll.subtitle')} current="payroll" shell={props}>
            <p className="mb-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{t('hr.payroll.configNote')}</p>

            <DataTable columns={runColumns} rows={runs} emptyMessage={t('hr.payroll.empty')} />

            {can.manage ? (
                <Card title={t('hr.payroll.new')} className="mt-6 max-w-3xl">
                    <p className="mb-3 text-sm text-slate-600">{t('hr.payroll.newNote')}</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            run.post(route('hr.payroll.store'), { preserveScroll: true });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={run.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('hr.payroll.label')} hint={t('hr.payroll.labelHint')}>
                                <Input value={run.data.period_label} onChange={(e) => run.setData('period_label', e.target.value)} maxLength={20} required />
                            </Field>
                            <Field label={t('hr.payroll.fallbackBasic')} hint={t('hr.payroll.fallbackHint')}>
                                <Input type="number" min="0" step="1" value={run.data.basic_salary} onChange={(e) => run.setData('basic_salary', e.target.value)} />
                            </Field>
                            <Field label={t('hr.payroll.start')}>
                                <Input type="date" value={run.data.period_start} onChange={(e) => run.setData('period_start', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.payroll.end')}>
                                <Input type="date" value={run.data.period_end} onChange={(e) => run.setData('period_end', e.target.value)} required />
                            </Field>
                        </div>
                        <Field label={t('acad.common.notes')}>
                            <Textarea rows={2} maxLength={500} value={run.data.notes} onChange={(e) => run.setData('notes', e.target.value)} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={run.processing}>
                            {t('hr.payroll.create')}
                        </button>
                    </form>
                </Card>
            ) : null}

            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('hr.component.title')}</h2>
            <DataTable columns={compColumns} rows={components} emptyMessage={t('hr.component.empty')} />
            <p className="mt-2 text-xs text-slate-500">{t('hr.component.note')}</p>

            {can.manage ? (
                <Card title={t('hr.component.add')} className="mt-4 max-w-3xl">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            comp.post(route('hr.components.store'), { preserveScroll: true, onSuccess: () => comp.reset() });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={comp.errors} />
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label={t('hr.col.name')}>
                                <Input value={comp.data.name} onChange={(e) => comp.setData('name', e.target.value)} maxLength={120} required />
                            </Field>
                            <Field label={t('acad.common.code')} hint={t('hr.component.codeHint')}>
                                <Input value={comp.data.code} onChange={(e) => comp.setData('code', e.target.value)} maxLength={30} required />
                            </Field>
                            <Field label={t('hr.col.type')}>
                                <Select value={comp.data.kind} onChange={(e) => comp.setData('kind', e.target.value)}>
                                    <option value="allowance">{t('hr.compKind.allowance')}</option>
                                    <option value="deduction">{t('hr.compKind.deduction')}</option>
                                </Select>
                            </Field>
                            <Field label={t('hr.component.calc')}>
                                <Select value={comp.data.calc} onChange={(e) => comp.setData('calc', e.target.value)}>
                                    <option value="fixed">{t('hr.calc.fixed')}</option>
                                    <option value="percent_of_basic">{t('hr.calc.percent_of_basic')}</option>
                                </Select>
                            </Field>
                            <Field label={t('hr.component.value')}>
                                <Input type="number" min="0" max={comp.data.calc === 'percent_of_basic' ? 100 : undefined} step="any" value={comp.data.value} onChange={(e) => comp.setData('value', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.component.appliesTo')}>
                                <Select value={comp.data.applies_to} onChange={(e) => comp.setData('applies_to', e.target.value)}>
                                    <option value="all">{t('hr.appliesTo.all')}</option>
                                    <option value="assigned">{t('hr.appliesTo.assigned')}</option>
                                </Select>
                            </Field>
                        </div>
                        <button type="submit" className="btn-teal" disabled={comp.processing}>
                            {t('hr.component.save')}
                        </button>
                    </form>
                </Card>
            ) : null}
        </HrLayout>
    );
}
