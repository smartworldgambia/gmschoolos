import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Pager, Select, Textarea } from '@/Components/SchoolOS/Form';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { router, useForm } from '@inertiajs/react';

const FILTERS = ['pending', 'approved', 'rejected', 'cancelled'];

export default function Leave(props) {
    const { leave, filter = 'pending', staff = [], types = [], caps = {}, can = {} } = props;
    const { t, locale } = useLocale();

    const f = useForm({ staff_id: '', leave_type: 'annual', starts_on: '', ends_on: '', days: '', reason: '' });
    f.transform((d) => ({ ...d, days: d.days === '' ? null : Number(d.days) }));

    const columns = [
        { key: 'staff', label: t('hr.col.staff') },
        { key: 'leave_type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.leaveType', r.leave_type) },
        { key: 'starts_on', label: t('hr.col.period'), render: (r) => `${fmtDay(r.starts_on, locale)} – ${fmtDay(r.ends_on, locale)}` },
        { key: 'days', label: t('hr.col.days') },
        { key: 'reason', label: t('acad.common.reason'), cellClassName: 'max-w-xs whitespace-normal', render: (r) => <span title={r.decision_note ?? ''}>{r.reason ?? '—'}</span> },
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
        { key: 'reviewer', label: t('hr.leave.decidedBy') },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex flex-wrap items-start gap-2 whitespace-normal">
                    {r.status === 'pending' && can.manage ? (
                        <>
                            <ReasonAction url={route('hr.leave.review', r.id)} fieldName="note" fields={{ status: 'approved' }} required={false} label={t('acad.common.approve')} tone="teal" reasonLabel={t('hr.leave.noteOptional')} />
                            <ReasonAction url={route('hr.leave.review', r.id)} fieldName="note" fields={{ status: 'rejected' }} label={t('acad.common.reject')} tone="danger" />
                        </>
                    ) : null}
                    {(r.status === 'pending' || r.status === 'approved') && can.manage ? (
                        <button
                            type="button"
                            className="btn-outline"
                            onClick={() => window.confirm(t('hr.leave.cancelConfirm')) && router.post(route('hr.leave.cancel', r.id), {}, { preserveScroll: true })}
                        >
                            {t('hr.leave.cancel')}
                        </button>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <HrLayout title={t('hr.leave.title')} subtitle={t('hr.leave.subtitle')} current="leave" shell={props}>
            <div className="mb-4 flex flex-wrap gap-2">
                <button type="button" className={filter === 'all' ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('hr.leave.index'), { status: 'all' }, { preserveState: true, replace: true })}>
                    {t('acad.common.all')}
                </button>
                {FILTERS.map((s) => (
                    <button key={s} type="button" className={filter === s ? 'btn-navy' : 'btn-outline'} onClick={() => router.get(route('hr.leave.index'), { status: s }, { preserveState: true, replace: true })}>
                        {t(`hr.status.${s}`)}
                    </button>
                ))}
            </div>

            <DataTable columns={columns} rows={leave?.data ?? []} emptyMessage={t('hr.leave.empty')} />
            <Pager links={leave?.links} />
            <p className="mt-2 text-xs text-slate-500">{t('hr.leave.selfReviewNote')}</p>

            {can.manage ? (
                <Card title={t('hr.leave.record')} className="mt-8 max-w-3xl">
                    <p className="mb-3 text-sm text-slate-600">
                        {caps.annual !== null && caps.annual !== undefined ? t('hr.leave.capAnnual', { n: caps.annual }) : t('hr.leave.noCap')}
                        {caps.sick !== null && caps.sick !== undefined ? ` ${t('hr.leave.capSick', { n: caps.sick })}` : ''}
                    </p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(route('hr.leave.store'), { preserveScroll: true, onSuccess: () => f.reset('starts_on', 'ends_on', 'days', 'reason') });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={f.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('hr.col.staff')}>
                                <Select value={f.data.staff_id} onChange={(e) => f.setData('staff_id', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {staff.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.col.type')}>
                                <Select value={f.data.leave_type} onChange={(e) => f.setData('leave_type', e.target.value)}>
                                    {types.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`hr.leaveType.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.leave.from')}>
                                <Input type="date" value={f.data.starts_on} onChange={(e) => f.setData('starts_on', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.leave.to')}>
                                <Input type="date" value={f.data.ends_on} onChange={(e) => f.setData('ends_on', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.col.days')} hint={t('hr.leave.daysHint')}>
                                <Input type="number" min="1" max="366" value={f.data.days} onChange={(e) => f.setData('days', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={t('acad.common.reason')}>
                            <Textarea rows={2} maxLength={1000} value={f.data.reason} onChange={(e) => f.setData('reason', e.target.value)} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={f.processing}>
                            {t('hr.leave.submit')}
                        </button>
                    </form>
                </Card>
            ) : null}
        </HrLayout>
    );
}
