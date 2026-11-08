import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Stat } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function PayrollShow(props) {
    const { run, items = [], can = {} } = props;
    const { t, locale } = useLocale();
    const [open, setOpen] = useState(null);
    const pay = useForm({ payment_reference: '', paid_on: todayIso() });

    const lineName = (l) => (l.kind === 'basic' ? t('hr.payroll.basicSalary') : l.name);

    const columns = [
        { key: 'staff_number', label: t('hr.col.number') },
        { key: 'staff', label: t('hr.col.name') },
        { key: 'basic', label: t('hr.col.basic'), render: (r) => money(r.basic) },
        { key: 'allowances', label: t('hr.col.allowances'), render: (r) => money(r.allowances) },
        { key: 'deductions', label: t('hr.col.deductions'), render: (r) => money(r.deductions) },
        { key: 'net', label: t('hr.col.net'), render: (r) => <strong>{money(r.net)}</strong> },
        {
            key: 'actions',
            label: '',
            cellClassName: 'whitespace-normal',
            render: (r) => (
                <div className="flex flex-wrap items-start gap-2">
                    <button type="button" className="btn-outline" onClick={() => setOpen(open === r.id ? null : r.id)} aria-expanded={open === r.id}>
                        {open === r.id ? t('hr.payroll.hideLines') : t('hr.payroll.showLines')}
                    </button>
                    <a href={route('hr.payslips.show', r.id)} target="_blank" rel="noreferrer" className="btn-outline">
                        {t('hr.payslip.open')}
                    </a>
                </div>
            ),
        },
    ];

    const openItem = items.find((i) => i.id === open);

    return (
        <HrLayout
            title={`${t('hr.payroll.run')} ${run.period_label}`}
            subtitle={`${fmtDay(run.period_start, locale)} – ${fmtDay(run.period_end, locale)}`}
            current="payroll"
            shell={props}
            actions={
                <>
                    <Link href={route('hr.payroll.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                    <a href={route('hr.payroll.export', run.id)} className="btn-outline">
                        {t('hr.payroll.export')}
                    </a>
                </>
            }
        >
            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                <Stat label={t('hr.col.status')} value={enumLabel(t, 'hr.status', run.status)} />
                <Stat label={t('hr.col.staff')} value={items.length} />
                <Stat label={t('hr.col.gross')} value={money(run.gross_total)} />
                <Stat label={t('hr.col.net')} value={money(run.net_total)} tone="green" />
            </div>

            {run.status === 'draft' ? <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{t('hr.payroll.draftNote')}</p> : null}
            {run.notes ? <p className="mb-4 text-sm text-slate-600">{run.notes}</p> : null}

            <div className="mb-4 flex flex-wrap gap-2">
                {run.status === 'draft' && can.approve ? (
                    <button
                        type="button"
                        className="btn-teal"
                        onClick={() => window.confirm(t('hr.payroll.postConfirm')) && router.post(route('hr.payroll.post', run.id), {}, { preserveScroll: true })}
                    >
                        {t('hr.payroll.post')}
                    </button>
                ) : null}
                {run.status === 'draft' && can.manage ? (
                    <button
                        type="button"
                        className="btn-outline !text-red-700"
                        onClick={() => window.confirm(t('hr.payroll.discardConfirm')) && router.delete(route('hr.payroll.destroy', run.id))}
                    >
                        {t('hr.payroll.discard')}
                    </button>
                ) : null}
                {run.status === 'draft' && can.approve && run.created_by_me ? <p className="self-center text-xs text-slate-500">{t('hr.payroll.makerChecker')}</p> : null}
            </div>

            <DataTable columns={columns} rows={items} emptyMessage={t('hr.payroll.noItems')} />

            {openItem ? (
                <Card title={`${openItem.staff} — ${t('hr.payroll.lines')}`} className="mt-4 max-w-2xl">
                    <table className="w-full text-sm">
                        <tbody>
                            {openItem.lines.map((l, i) => (
                                <tr key={i} className="border-b border-slate-100">
                                    <td className="py-1.5">
                                        {lineName(l)}
                                        {l.calc === 'percent_of_basic' ? <span className="ms-1 text-xs text-slate-500">({l.value}% {t('hr.component.ofBasic')})</span> : null}
                                    </td>
                                    <td className="py-1.5 text-slate-500">{l.kind === 'basic' ? '' : enumLabel(t, 'hr.compKind', l.kind)}</td>
                                    <td className="py-1.5 text-end">{l.kind === 'deduction' ? '−' : ''}{money(l.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            ) : null}

            {run.status === 'posted' && can.manage ? (
                <Card title={t('hr.payroll.markPaid')} className="mt-6 max-w-xl">
                    <p className="mb-3 text-sm text-slate-600">{t('hr.payroll.markPaidNote')}</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            pay.post(route('hr.payroll.pay', run.id), { preserveScroll: true });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={pay.errors} />
                        <Field label={t('hr.payroll.reference')} hint={t('hr.payroll.referenceHint')}>
                            <Input value={pay.data.payment_reference} onChange={(e) => pay.setData('payment_reference', e.target.value)} maxLength={120} required />
                        </Field>
                        <Field label={t('hr.payroll.paidOn')}>
                            <Input type="date" max={todayIso()} value={pay.data.paid_on} onChange={(e) => pay.setData('paid_on', e.target.value)} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={pay.processing}>
                            {t('hr.payroll.markPaid')}
                        </button>
                    </form>
                </Card>
            ) : null}

            {run.status === 'paid' ? (
                <p className="mt-6 text-sm text-slate-600">
                    <StatusBadge status="paid" label={t('hr.status.paid')} /> {fmtDay(run.paid_at, locale)} · {t('hr.payroll.reference')}: {run.payment_reference}
                </p>
            ) : null}
        </HrLayout>
    );
}
