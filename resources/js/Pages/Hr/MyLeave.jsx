import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { router, useForm } from '@inertiajs/react';

export default function MyLeave(props) {
    const { linked = false, leave = [], balances = [], types = [] } = props;
    const { t, locale } = useLocale();

    const f = useForm({ leave_type: 'annual', starts_on: '', ends_on: '', days: '', reason: '' });
    f.transform((d) => ({ ...d, days: d.days === '' ? null : Number(d.days) }));

    const columns = [
        { key: 'leave_type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.leaveType', r.leave_type) },
        { key: 'starts_on', label: t('hr.col.period'), render: (r) => `${fmtDay(r.starts_on, locale)} – ${fmtDay(r.ends_on, locale)}` },
        { key: 'days', label: t('hr.col.days') },
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
        { key: 'decision_note', label: t('hr.leave.decisionNote'), cellClassName: 'max-w-xs whitespace-normal' },
        {
            key: 'actions',
            label: '',
            render: (r) =>
                r.cancellable ? (
                    <button type="button" className="btn-outline" onClick={() => window.confirm(t('hr.leave.cancelConfirm')) && router.post(route('my.leave.cancel', r.id), {}, { preserveScroll: true })}>
                        {t('hr.leave.cancel')}
                    </button>
                ) : null,
        },
    ];

    return (
        <HrLayout title={t('hr.my.title')} subtitle={t('hr.my.subtitle')} current="myLeave" shell={props}>
            {!linked ? (
                <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{t('hr.my.notLinked')}</p>
            ) : (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-4">
                        {balances.map((b) => (
                            <div key={b.type} className="rounded-xl bg-white p-4 shadow-sm">
                                <p className="text-xs text-slate-500">{t(`hr.leaveType.${b.type}`)}</p>
                                <p className="mt-1 text-lg font-bold text-navy">{t('hr.balance.line', { approved: b.approved, pending: b.pending })}</p>
                                {b.cap !== null && b.cap !== undefined ? <p className="text-xs text-slate-500">{t('hr.balance.cap', { n: b.cap })}</p> : null}
                            </div>
                        ))}
                    </div>

                    <DataTable columns={columns} rows={leave} emptyMessage={t('hr.leave.empty')} />

                    <Card title={t('hr.my.apply')} className="mt-8 max-w-3xl">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                f.post(route('my.leave.store'), { preserveScroll: true, onSuccess: () => f.reset('starts_on', 'ends_on', 'days', 'reason') });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={f.errors} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('hr.col.type')}>
                                    <Select value={f.data.leave_type} onChange={(e) => f.setData('leave_type', e.target.value)}>
                                        {types.map((k) => (
                                            <option key={k} value={k}>
                                                {t(`hr.leaveType.${k}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.col.days')} hint={t('hr.leave.daysHint')}>
                                    <Input type="number" min="1" max="366" value={f.data.days} onChange={(e) => f.setData('days', e.target.value)} />
                                </Field>
                                <Field label={t('hr.leave.from')}>
                                    <Input type="date" min={todayIso()} value={f.data.starts_on} onChange={(e) => f.setData('starts_on', e.target.value)} required />
                                </Field>
                                <Field label={t('hr.leave.to')}>
                                    <Input type="date" min={f.data.starts_on || todayIso()} value={f.data.ends_on} onChange={(e) => f.setData('ends_on', e.target.value)} required />
                                </Field>
                            </div>
                            <Field label={t('acad.common.reason')}>
                                <Textarea rows={2} maxLength={1000} value={f.data.reason} onChange={(e) => f.setData('reason', e.target.value)} />
                            </Field>
                            <button type="submit" className="btn-teal" disabled={f.processing}>
                                {t('hr.my.submit')}
                            </button>
                        </form>
                    </Card>
                </>
            )}
        </HrLayout>
    );
}
