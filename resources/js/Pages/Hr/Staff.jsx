import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['active', 'inactive', 'exited'];

export default function Staff(props) {
    const { staff, filters = {}, departments = [], positions = [], logins = [], employmentTypes = [], can = {} } = props;
    const { t } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');

    const filter = (patch) => router.get(route('hr.staff.index'), { ...filters, ...patch }, { preserveState: true, replace: true });

    const f = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        role_title: '',
        department_id: '',
        job_position_id: '',
        employment_type: 'permanent',
        hired_on: '',
        user_id: '',
    });
    f.transform((d) => ({ ...d, department_id: d.department_id || null, job_position_id: d.job_position_id || null, hired_on: d.hired_on || null, user_id: d.user_id || null }));

    const columns = [
        { key: 'staff_number', label: t('hr.col.number') },
        {
            key: 'name',
            label: t('hr.col.name'),
            render: (r) => (
                <Link href={route('hr.staff.show', r.id)} className="font-medium text-teal-700 hover:underline">
                    {r.name}
                </Link>
            ),
        },
        { key: 'department', label: t('hr.col.department') },
        { key: 'position', label: t('hr.col.position') },
        { key: 'phone', label: t('hr.col.phone') },
        { key: 'has_login', label: t('hr.col.login'), render: (r) => (r.has_login ? t('acad.common.yes') : t('acad.common.no')) },
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
    ];

    return (
        <HrLayout title={t('hr.staff.title')} subtitle={t('hr.staff.subtitle')} current="staff" shell={props}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    filter({ q, page: undefined });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')}>
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('hr.staff.searchPlaceholder')} />
                </Field>
                <Field label={t('hr.col.department')}>
                    <Select value={filters.department ?? ''} onChange={(e) => filter({ department: e.target.value || undefined, page: undefined })}>
                        <option value="">{t('acad.common.all')}</option>
                        {departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('hr.col.status')}>
                    <Select value={filters.status ?? 'active'} onChange={(e) => filter({ status: e.target.value, page: undefined })}>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {t(`hr.status.${s}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <button type="submit" className="btn-navy">
                    {t('acad.common.search')}
                </button>
            </form>

            <DataTable columns={columns} rows={staff?.data ?? []} emptyMessage={t('hr.staff.empty')} />
            <Pager links={staff?.links} />

            {can.manage ? (
                <Card title={t('hr.staff.add')} className="mt-8 max-w-3xl">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(route('hr.staff.store'), { preserveScroll: true });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={f.errors} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('hr.form.firstName')}>
                                <Input value={f.data.first_name} onChange={(e) => f.setData('first_name', e.target.value)} maxLength={100} required />
                            </Field>
                            <Field label={t('hr.form.lastName')}>
                                <Input value={f.data.last_name} onChange={(e) => f.setData('last_name', e.target.value)} maxLength={100} required />
                            </Field>
                            <Field label={t('hr.form.email')}>
                                <Input type="email" value={f.data.email} onChange={(e) => f.setData('email', e.target.value)} />
                            </Field>
                            <Field label={t('hr.col.phone')}>
                                <Input value={f.data.phone} onChange={(e) => f.setData('phone', e.target.value)} maxLength={40} />
                            </Field>
                            <Field label={t('hr.form.department')}>
                                <Select value={f.data.department_id} onChange={(e) => f.setData('department_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {departments.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.col.position')}>
                                <Select value={f.data.job_position_id} onChange={(e) => f.setData('job_position_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {positions.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.form.employmentType')}>
                                <Select value={f.data.employment_type} onChange={(e) => f.setData('employment_type', e.target.value)}>
                                    {employmentTypes.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`hr.employment.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.form.hiredOn')}>
                                <Input type="date" value={f.data.hired_on} onChange={(e) => f.setData('hired_on', e.target.value)} />
                            </Field>
                            <Field label={t('hr.form.roleTitle')} hint={t('hr.form.roleTitleHint')}>
                                <Input value={f.data.role_title} onChange={(e) => f.setData('role_title', e.target.value)} maxLength={150} />
                            </Field>
                            <Field label={t('hr.form.login')} hint={t('hr.form.loginHint')}>
                                <Select value={f.data.user_id} onChange={(e) => f.setData('user_id', e.target.value)}>
                                    <option value="">{t('hr.form.noLogin')}</option>
                                    {logins.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} ({u.email})
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                        <button type="submit" className="btn-teal" disabled={f.processing}>
                            {t('hr.staff.create')}
                        </button>
                    </form>
                </Card>
            ) : null}
        </HrLayout>
    );
}
