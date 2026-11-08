import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { router, useForm } from '@inertiajs/react';

export default function Setup(props) {
    const { positions = [], departments = [], settings = {}, canPayroll = false } = props;
    const { t } = useLocale();

    const pos = useForm({ title: '', description: '', department_id: '' });
    pos.transform((d) => ({ ...d, department_id: d.department_id || null, description: d.description || null }));

    const rules = useForm({
        leave_annual_days: settings.hr?.leave_annual_days ?? '',
        leave_sick_days: settings.hr?.leave_sick_days ?? '',
        leave_self_approval: Boolean(settings.hr?.self_approval),
        payroll_self_approval: Boolean(settings.payroll?.self_approval),
    });
    rules.transform((d) => ({
        ...d,
        leave_annual_days: d.leave_annual_days === '' ? null : Number(d.leave_annual_days),
        leave_sick_days: d.leave_sick_days === '' ? null : Number(d.leave_sick_days),
    }));

    const columns = [
        { key: 'title', label: t('hr.setup.positionTitle') },
        { key: 'department', label: t('hr.col.department') },
        { key: 'description', label: t('hr.setup.description'), cellClassName: 'max-w-xs whitespace-normal' },
        { key: 'is_active', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.is_active ? 'active' : 'inactive'} label={t(r.is_active ? 'hr.status.active' : 'hr.status.inactive')} /> },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <button
                    type="button"
                    className="btn-outline"
                    onClick={() => router.put(route('hr.setup.positions.update', r.id), { title: r.title, description: r.description, department_id: r.department_id, is_active: !r.is_active }, { preserveScroll: true })}
                >
                    {r.is_active ? t('hr.component.deactivate') : t('hr.component.activate')}
                </button>
            ),
        },
    ];

    return (
        <HrLayout title={t('hr.setup.title')} subtitle={t('hr.setup.subtitle')} current="setup" shell={props}>
            <Card title={t('hr.setup.rules')} className="max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        rules.put(route('hr.setup.settings'), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={rules.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('hr.setup.annualDays')} hint={t('hr.setup.capHint')}>
                            <Input type="number" min="0" max="366" value={rules.data.leave_annual_days} onChange={(e) => rules.setData('leave_annual_days', e.target.value)} />
                        </Field>
                        <Field label={t('hr.setup.sickDays')} hint={t('hr.setup.capHint')}>
                            <Input type="number" min="0" max="366" value={rules.data.leave_sick_days} onChange={(e) => rules.setData('leave_sick_days', e.target.value)} />
                        </Field>
                        <Field label={t('hr.setup.leaveSelf')} hint={t('hr.setup.leaveSelfHint')}>
                            <Select value={rules.data.leave_self_approval ? '1' : '0'} onChange={(e) => rules.setData('leave_self_approval', e.target.value === '1')}>
                                <option value="0">{t('hr.setup.notAllowed')}</option>
                                <option value="1">{t('hr.setup.allowed')}</option>
                            </Select>
                        </Field>
                        <Field label={t('hr.setup.payrollSelf')} hint={canPayroll ? t('hr.setup.payrollSelfHint') : t('hr.setup.payrollOnly')}>
                            <Select value={rules.data.payroll_self_approval ? '1' : '0'} onChange={(e) => rules.setData('payroll_self_approval', e.target.value === '1')} disabled={!canPayroll}>
                                <option value="0">{t('hr.setup.notAllowed')}</option>
                                <option value="1">{t('hr.setup.allowed')}</option>
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={rules.processing}>
                        {t('acad.common.save')}
                    </button>
                </form>
            </Card>

            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('hr.setup.positions')}</h2>
            <DataTable columns={columns} rows={positions} emptyMessage={t('hr.setup.noPositions')} />

            <Card title={t('hr.setup.addPosition')} className="mt-4 max-w-3xl">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        pos.post(route('hr.setup.positions.store'), { preserveScroll: true, onSuccess: () => pos.reset() });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={pos.errors} />
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Field label={t('hr.setup.positionTitle')}>
                            <Input value={pos.data.title} onChange={(e) => pos.setData('title', e.target.value)} maxLength={150} required />
                        </Field>
                        <Field label={t('hr.col.department')}>
                            <Select value={pos.data.department_id} onChange={(e) => pos.setData('department_id', e.target.value)}>
                                <option value="">{t('acad.common.none')}</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('hr.setup.description')}>
                            <Input value={pos.data.description} onChange={(e) => pos.setData('description', e.target.value)} maxLength={500} />
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={pos.processing}>
                        {t('hr.setup.savePosition')}
                    </button>
                </form>
            </Card>
        </HrLayout>
    );
}
