import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

const STATUSES = ['active', 'inactive', 'exited'];

export default function StaffShow(props) {
    const {
        staff,
        contracts = [],
        documents = [],
        performance = null,
        leave = [],
        balances = [],
        departments = [],
        positions = [],
        logins = [],
        employmentTypes = [],
        contractTypes = [],
        docTypes = [],
        can = {},
    } = props;
    const { t, locale } = useLocale();

    const edit = useForm({
        first_name: staff.first_name ?? '',
        last_name: staff.last_name ?? '',
        email: staff.email ?? '',
        phone: staff.phone ?? '',
        role_title: staff.role_title ?? '',
        department_id: staff.department_id ?? '',
        job_position_id: staff.job_position_id ?? '',
        employment_type: staff.employment_type ?? '',
        hired_on: staff.hired_on ?? '',
        exited_on: staff.exited_on ?? '',
        emergency_contact_name: staff.emergency_contact_name ?? '',
        emergency_contact_phone: staff.emergency_contact_phone ?? '',
        user_id: staff.user_id ?? '',
        bank_name: '',
        bank_account: '',
        status: staff.status,
    });
    edit.transform((d) => ({
        ...d,
        department_id: d.department_id || null,
        job_position_id: d.job_position_id || null,
        employment_type: d.employment_type || null,
        hired_on: d.hired_on || null,
        exited_on: d.exited_on || null,
        user_id: d.user_id || null,
    }));

    const contract = useForm({ contract_type: 'permanent', job_position_id: '', starts_on: '', ends_on: '', basic_salary: '', notes: '' });
    contract.transform((d) => ({ ...d, job_position_id: d.job_position_id || null, ends_on: d.ends_on || null, basic_salary: d.basic_salary === '' ? '' : Number(d.basic_salary) }));

    const doc = useForm({ type: 'contract', title: '', expires_on: '', file: null });
    doc.transform((d) => ({ ...d, expires_on: d.expires_on || null }));

    const perf = useForm({ period_label: '', rating: 3, strengths: '', improvements: '', goals: '' });

    const contractColumns = [
        { key: 'contract_type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.contractType', r.contract_type) },
        { key: 'position', label: t('hr.col.position') },
        { key: 'starts_on', label: t('hr.col.startsOn'), render: (r) => fmtDay(r.starts_on, locale) },
        { key: 'ends_on', label: t('hr.col.endsOn'), render: (r) => (r.ends_on ? fmtDay(r.ends_on, locale) : '—') },
        ...(can.pay ? [{ key: 'basic_salary', label: t('hr.col.basic'), render: (r) => money(r.basic_salary) }] : []),
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
        {
            key: 'actions',
            label: '',
            render: (r) =>
                r.status === 'active' && can.pay ? (
                    <div className="flex flex-wrap gap-2 whitespace-normal">
                        <button
                            type="button"
                            className="btn-outline"
                            onClick={() => window.confirm(t('hr.contract.endConfirm')) && router.post(route('hr.contracts.end', r.id), { outcome: 'ended' }, { preserveScroll: true })}
                        >
                            {t('hr.contract.end')}
                        </button>
                        <button
                            type="button"
                            className="btn-outline !text-red-700"
                            onClick={() => window.confirm(t('hr.contract.terminateConfirm')) && router.post(route('hr.contracts.end', r.id), { outcome: 'terminated' }, { preserveScroll: true })}
                        >
                            {t('hr.contract.terminate')}
                        </button>
                    </div>
                ) : null,
        },
    ];

    return (
        <HrLayout
            title={staff.name}
            subtitle={`${staff.staff_number} · ${staff.position ?? staff.role_title ?? ''}`}
            current="staff"
            shell={props}
            actions={
                <Link href={route('hr.staff.index')} className="btn-outline">
                    {t('acad.common.back')}
                </Link>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Card title={t('hr.staff.leaveBalances')} className="lg:col-span-1">
                    <ul className="space-y-2 text-sm">
                        {balances.map((b) => (
                            <li key={b.type} className="flex items-center justify-between gap-3">
                                <span>{t(`hr.leaveType.${b.type}`)}</span>
                                <span className="text-slate-600">
                                    {t('hr.balance.line', { approved: b.approved, pending: b.pending })}
                                    {b.cap !== null && b.cap !== undefined ? ` / ${b.cap}` : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 text-xs text-slate-500">{t('hr.balance.note')}</p>
                    <h3 className="mb-2 mt-5 text-sm font-semibold text-navy">{t('hr.staff.recentLeave')}</h3>
                    <ul className="space-y-1 text-sm">
                        {leave.length === 0 ? <li className="text-slate-500">{t('hr.leave.empty')}</li> : null}
                        {leave.map((l) => (
                            <li key={l.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    {enumLabel(t, 'hr.leaveType', l.leave_type)} · {fmtDay(l.starts_on, locale)} → {fmtDay(l.ends_on, locale)}
                                </span>
                                <StatusBadge status={l.status} label={enumLabel(t, 'hr.status', l.status)} />
                            </li>
                        ))}
                    </ul>
                </Card>

                {can.manage ? (
                    <Card title={t('hr.staff.profile')} className="lg:col-span-2">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                edit.put(route('hr.staff.update', staff.id), { preserveScroll: true, onSuccess: () => edit.reset('bank_name', 'bank_account') });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={edit.errors} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('hr.form.firstName')}>
                                    <Input value={edit.data.first_name} onChange={(e) => edit.setData('first_name', e.target.value)} required />
                                </Field>
                                <Field label={t('hr.form.lastName')}>
                                    <Input value={edit.data.last_name} onChange={(e) => edit.setData('last_name', e.target.value)} required />
                                </Field>
                                <Field label={t('hr.form.email')}>
                                    <Input type="email" value={edit.data.email} onChange={(e) => edit.setData('email', e.target.value)} />
                                </Field>
                                <Field label={t('hr.col.phone')}>
                                    <Input value={edit.data.phone} onChange={(e) => edit.setData('phone', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.department')}>
                                    <Select value={edit.data.department_id} onChange={(e) => edit.setData('department_id', e.target.value)}>
                                        <option value="">{t('acad.common.none')}</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.col.position')}>
                                    <Select value={edit.data.job_position_id} onChange={(e) => edit.setData('job_position_id', e.target.value)}>
                                        <option value="">{t('acad.common.none')}</option>
                                        {positions.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.title}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.form.employmentType')}>
                                    <Select value={edit.data.employment_type} onChange={(e) => edit.setData('employment_type', e.target.value)}>
                                        <option value="">{t('acad.common.none')}</option>
                                        {employmentTypes.map((k) => (
                                            <option key={k} value={k}>
                                                {t(`hr.employment.${k}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.col.status')}>
                                    <Select value={edit.data.status} onChange={(e) => edit.setData('status', e.target.value)}>
                                        {STATUSES.map((s) => (
                                            <option key={s} value={s}>
                                                {t(`hr.status.${s}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.form.hiredOn')}>
                                    <Input type="date" value={edit.data.hired_on} onChange={(e) => edit.setData('hired_on', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.exitedOn')}>
                                    <Input type="date" value={edit.data.exited_on} onChange={(e) => edit.setData('exited_on', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.emergencyName')}>
                                    <Input value={edit.data.emergency_contact_name} onChange={(e) => edit.setData('emergency_contact_name', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.emergencyPhone')}>
                                    <Input value={edit.data.emergency_contact_phone} onChange={(e) => edit.setData('emergency_contact_phone', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.login')} hint={t('hr.form.loginHint')}>
                                    <Select value={edit.data.user_id} onChange={(e) => edit.setData('user_id', e.target.value)}>
                                        <option value="">{t('hr.form.noLogin')}</option>
                                        {logins.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name} ({u.email})
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.form.roleTitle')}>
                                    <Input value={edit.data.role_title} onChange={(e) => edit.setData('role_title', e.target.value)} />
                                </Field>
                                <Field label={t('hr.form.bankName')} hint={staff.bank_name ? `${t('hr.form.current')}: ${staff.bank_name}` : undefined}>
                                    <Input value={edit.data.bank_name} onChange={(e) => edit.setData('bank_name', e.target.value)} autoComplete="off" />
                                </Field>
                                <Field label={t('hr.form.bankAccount')} hint={staff.bank_account_masked ? `${t('hr.form.current')}: ${staff.bank_account_masked}` : t('hr.form.bankHint')}>
                                    <Input value={edit.data.bank_account} onChange={(e) => edit.setData('bank_account', e.target.value)} autoComplete="off" />
                                </Field>
                            </div>
                            <button type="submit" className="btn-teal" disabled={edit.processing}>
                                {t('acad.common.save')}
                            </button>
                        </form>
                    </Card>
                ) : null}
            </div>

            <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('hr.contract.title')}</h2>
            <DataTable columns={contractColumns} rows={contracts} emptyMessage={t('hr.contract.empty')} />
            {can.pay ? (
                <Card title={t('hr.contract.add')} className="mt-4 max-w-3xl">
                    <p className="mb-3 text-sm text-slate-600">{t('hr.contract.addNote')}</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            contract.post(route('hr.contracts.store', staff.id), { preserveScroll: true, onSuccess: () => contract.reset() });
                        }}
                        className="space-y-3"
                    >
                        <ErrorSummary errors={contract.errors} />
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label={t('hr.col.type')}>
                                <Select value={contract.data.contract_type} onChange={(e) => contract.setData('contract_type', e.target.value)}>
                                    {contractTypes.map((k) => (
                                        <option key={k} value={k}>
                                            {t(`hr.contractType.${k}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.col.position')}>
                                <Select value={contract.data.job_position_id} onChange={(e) => contract.setData('job_position_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {positions.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('hr.col.basic')}>
                                <Input type="number" min="0" step="1" value={contract.data.basic_salary} onChange={(e) => contract.setData('basic_salary', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.col.startsOn')}>
                                <Input type="date" value={contract.data.starts_on} onChange={(e) => contract.setData('starts_on', e.target.value)} required />
                            </Field>
                            <Field label={t('hr.col.endsOn')} hint={t('hr.contract.endHint')}>
                                <Input type="date" value={contract.data.ends_on} onChange={(e) => contract.setData('ends_on', e.target.value)} />
                            </Field>
                        </div>
                        <Field label={t('acad.common.notes')}>
                            <Textarea rows={2} maxLength={500} value={contract.data.notes} onChange={(e) => contract.setData('notes', e.target.value)} />
                        </Field>
                        <button type="submit" className="btn-teal" disabled={contract.processing}>
                            {t('hr.contract.save')}
                        </button>
                    </form>
                </Card>
            ) : null}

            {can.manage ? (
                <>
                    <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('hr.doc.title')}</h2>
                    <DataTable
                        columns={[
                            { key: 'type', label: t('hr.col.type'), render: (r) => enumLabel(t, 'hr.docType', r.type) },
                            { key: 'title', label: t('hr.col.document') },
                            { key: 'expires_on', label: t('hr.col.expires'), render: (r) => (r.expires_on ? fmtDay(r.expires_on, locale) : '—') },
                            { key: 'created_at', label: t('acad.common.date'), render: (r) => fmtDay(r.created_at, locale) },
                            {
                                key: 'actions',
                                label: '',
                                render: (r) => (
                                    <div className="flex flex-wrap gap-2 whitespace-normal">
                                        <a href={route('hr.documents.download', r.id)} className="btn-outline">
                                            {t('acad.common.download')}
                                        </a>
                                        <button
                                            type="button"
                                            className="btn-outline !text-red-700"
                                            onClick={() => window.confirm(t('hr.doc.removeConfirm')) && router.delete(route('hr.documents.destroy', r.id), { preserveScroll: true })}
                                        >
                                            {t('acad.common.remove')}
                                        </button>
                                    </div>
                                ),
                            },
                        ]}
                        rows={documents}
                        emptyMessage={t('hr.doc.empty')}
                    />
                    <Card title={t('hr.doc.add')} className="mt-4 max-w-3xl">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                doc.post(route('hr.documents.store', staff.id), { forceFormData: true, preserveScroll: true, onSuccess: () => doc.reset() });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={doc.errors} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('hr.col.type')}>
                                    <Select value={doc.data.type} onChange={(e) => doc.setData('type', e.target.value)}>
                                        {docTypes.map((k) => (
                                            <option key={k} value={k}>
                                                {t(`hr.docType.${k}`)}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('hr.col.document')}>
                                    <Input value={doc.data.title} onChange={(e) => doc.setData('title', e.target.value)} maxLength={190} required />
                                </Field>
                                <Field label={t('hr.col.expires')} hint={t('acad.common.optional')}>
                                    <Input type="date" value={doc.data.expires_on} onChange={(e) => doc.setData('expires_on', e.target.value)} />
                                </Field>
                                <Field label={t('hr.doc.file')} hint={t('hr.doc.fileHint')}>
                                    <Input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onChange={(e) => doc.setData('file', e.target.files?.[0] ?? null)} required />
                                </Field>
                            </div>
                            <button type="submit" className="btn-teal" disabled={doc.processing}>
                                {t('hr.doc.upload')}
                            </button>
                        </form>
                    </Card>
                </>
            ) : null}

            {can.performance && performance ? (
                <>
                    <h2 className="mb-3 mt-8 text-base font-semibold text-navy">{t('hr.perf.title')}</h2>
                    <p className="mb-2 text-xs text-slate-500">{t('hr.perf.confidential')}</p>
                    <DataTable
                        columns={[
                            { key: 'period_label', label: t('hr.col.period') },
                            { key: 'rating', label: t('hr.perf.rating'), render: (r) => `${r.rating} / 5` },
                            { key: 'strengths', label: t('hr.perf.strengths'), cellClassName: 'max-w-xs whitespace-normal' },
                            { key: 'improvements', label: t('hr.perf.improvements'), cellClassName: 'max-w-xs whitespace-normal' },
                            { key: 'goals', label: t('hr.perf.goals'), cellClassName: 'max-w-xs whitespace-normal' },
                            { key: 'reviewer', label: t('hr.perf.reviewer') },
                            { key: 'created_at', label: t('acad.common.date'), render: (r) => fmtDay(r.created_at, locale) },
                        ]}
                        rows={performance}
                        emptyMessage={t('hr.perf.empty')}
                    />
                    <Card title={t('hr.perf.add')} className="mt-4 max-w-3xl">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                perf.post(route('hr.performance.store', staff.id), { preserveScroll: true, onSuccess: () => perf.reset() });
                            }}
                            className="space-y-3"
                        >
                            <ErrorSummary errors={perf.errors} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label={t('hr.col.period')}>
                                    <Input value={perf.data.period_label} onChange={(e) => perf.setData('period_label', e.target.value)} maxLength={40} required />
                                </Field>
                                <Field label={t('hr.perf.rating')}>
                                    <Select value={perf.data.rating} onChange={(e) => perf.setData('rating', Number(e.target.value))}>
                                        {[1, 2, 3, 4, 5].map((n) => (
                                            <option key={n} value={n}>
                                                {n} / 5
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                            <Field label={t('hr.perf.strengths')}>
                                <Textarea rows={2} maxLength={2000} value={perf.data.strengths} onChange={(e) => perf.setData('strengths', e.target.value)} />
                            </Field>
                            <Field label={t('hr.perf.improvements')}>
                                <Textarea rows={2} maxLength={2000} value={perf.data.improvements} onChange={(e) => perf.setData('improvements', e.target.value)} />
                            </Field>
                            <Field label={t('hr.perf.goals')}>
                                <Textarea rows={2} maxLength={2000} value={perf.data.goals} onChange={(e) => perf.setData('goals', e.target.value)} />
                            </Field>
                            <button type="submit" className="btn-teal" disabled={perf.processing}>
                                {t('hr.perf.save')}
                            </button>
                        </form>
                    </Card>
                </>
            ) : null}
        </HrLayout>
    );
}
