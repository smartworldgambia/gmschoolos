import { Card, ErrorSummary, Field, Input, Select, Stat } from '@/Components/SchoolOS/Form';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Item({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-navy">{value || '—'}</dd>
        </div>
    );
}

function Documents({ student, documents, canUpload, documentTypes }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ type: 'birth_certificate', file: null });

    function upload(e) {
        e.preventDefault();
        post(route('students.documents.store', student.id), { forceFormData: true, onSuccess: () => reset('file') });
    }

    return (
        <Card title={t('students.show.documents')}>
            <ul className="divide-y divide-slate-100 text-sm">
                {documents.length === 0 ? <li className="py-3 text-slate-500">{t('students.show.noDocuments')}</li> : null}
                {documents.map((d) => (
                    <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>
                            <span className="font-medium text-navy">{enumLabel(t, 'docType', d.type)}</span>
                            <span className="ms-2 text-slate-500">{d.name}</span>
                        </span>
                        <span className="flex items-center gap-3">
                            <a href={d.url} className="text-teal hover:underline">
                                {t('acad.common.download')}
                            </a>
                            {canUpload ? (
                                <button
                                    type="button"
                                    className="text-red-600 hover:underline"
                                    onClick={() => confirm(t('students.show.confirmRemoveDocument')) && router.delete(route('students.documents.destroy', [student.id, d.id]), { preserveScroll: true })}
                                >
                                    {t('acad.common.remove')}
                                </button>
                            ) : null}
                        </span>
                    </li>
                ))}
            </ul>
            {canUpload ? (
                <form onSubmit={upload} className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                    <Field label={t('students.show.documentType')} error={errors.type}>
                        <Select value={data.type} onChange={(e) => setData('type', e.target.value)} className="!w-auto">
                            {documentTypes.map((x) => (
                                <option key={x} value={x}>
                                    {enumLabel(t, 'docType', x)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('students.show.documentFile')} error={errors.file} hint={t('students.show.documentHint')}>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setData('file', e.target.files[0])} className="block w-full min-w-0 text-sm" />
                    </Field>
                    <button type="submit" className="btn-teal" disabled={processing || !data.file}>
                        {t('students.show.upload')}
                    </button>
                </form>
            ) : null}
        </Card>
    );
}

function MoveForm({ student, classes }) {
    const { t } = useLocale();
    const actions = student.status === 'active' ? ['change_class', 'withdraw', 'transfer_out', 'graduate'] : ['reinstate'];
    const { data, setData, post, processing, errors, reset } = useForm({ action: actions[0], school_class_id: '', reason: '', effective_on: '' });
    const needsClass = ['change_class', 'reinstate'].includes(data.action);
    const needsReason = ['withdraw', 'transfer_out'].includes(data.action);

    function submit(e) {
        e.preventDefault();
        if (needsReason || data.action === 'graduate') {
            if (!confirm(t('students.show.confirmMove'))) return;
        }
        post(route('students.move', student.id), { preserveScroll: true, onSuccess: () => reset('reason') });
    }

    return (
        <Card title={t('students.show.move')}>
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ErrorSummary errors={errors} />
                <Field label={t('students.show.moveAction')}>
                    <Select value={data.action} onChange={(e) => setData('action', e.target.value)}>
                        {actions.map((a) => (
                            <option key={a} value={a}>
                                {enumLabel(t, 'moveAction', a)}
                            </option>
                        ))}
                    </Select>
                </Field>
                {needsClass ? (
                    <Field label={t('students.show.moveClass')}>
                        <Select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} required={data.action === 'change_class'}>
                            <option value="">{t('acad.common.choose')}</option>
                            {classes.filter((c) => c.id !== student.class_id).map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ) : null}
                <Field label={t('acad.common.reason')} hint={needsReason ? t('students.show.reasonRequired') : undefined}>
                    <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} required={needsReason} />
                </Field>
                {needsReason || data.action === 'graduate' ? (
                    <Field label={t('students.show.effectiveOn')}>
                        <Input type="date" value={data.effective_on} onChange={(e) => setData('effective_on', e.target.value)} />
                    </Field>
                ) : null}
                <div className="flex items-end">
                    <button type="submit" className="btn-navy" disabled={processing}>
                        {t('students.show.applyMove')}
                    </button>
                </div>
            </form>
        </Card>
    );
}

export default function Show({
    student,
    sensitive = null,
    guardians = [],
    documents = [],
    movements = [],
    grades = [],
    history = [],
    attendance = {},
    can = {},
    classes = [],
    reportCardUrl,
    attendanceUrl,
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const [tab, setTab] = useState('overview');

    const tabs = [
        ['overview', t('students.show.tab.overview')],
        ['academic', t('students.show.tab.academic')],
        ['attendance', t('students.show.tab.attendance')],
        ['guardians', t('students.show.tab.guardians')],
        ...(documents.length > 0 || can.documents ? [['documents', t('students.show.tab.documents')]] : []),
        ['history', t('students.show.tab.history')],
    ];

    const gradeColumns = [
        { key: 'subject', label: t('results.col.subject') },
        { key: 'examination', label: t('results.col.examination') },
        { key: 'ca_score', label: t('results.col.ca'), render: (r) => r.ca_score ?? '—' },
        { key: 'exam_score', label: t('results.col.exam'), render: (r) => r.exam_score ?? '—' },
        {
            key: 'total',
            label: t('results.col.total'),
            render: (r) => (
                <span>
                    {r.total ?? '—'}
                    {r.revised ? <span className="ms-1 text-xs text-amber-600">{t('results.revised')}</span> : null}
                </span>
            ),
        },
        { key: 'letter_grade', label: t('results.col.grade'), render: (r) => r.letter_grade ?? '—' },
    ];

    return (
        <AppShell title={student.full_name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={student.full_name} />
            <PageHeader
                title={student.full_name}
                subtitle={`${student.student_number}${student.class ? ` · ${student.class}` : ''}`}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={student.status} label={enumLabel(t, 'studentStatus', student.status)} />
                        {can.update ? (
                            <Link href={route('students.edit', student.id)} className="btn-outline">
                                {t('acad.common.edit')}
                            </Link>
                        ) : null}
                        <Link href={reportCardUrl} className="btn-teal">
                            {t('students.show.reportCard')}
                        </Link>
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap gap-1 border-b border-slate-200" role="tablist">
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={`whitespace-nowrap px-4 py-2 text-sm font-medium ${tab === key ? 'border-b-2 border-teal text-teal' : 'text-slate-500 hover:text-navy'}`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'overview' ? (
                <div className="space-y-6">
                    <Card>
                        <div className="flex flex-wrap gap-6">
                            {student.has_photo ? (
                                <img src={student.photo_url} alt={student.full_name} className="h-28 w-28 rounded-xl object-cover" />
                            ) : null}
                            <dl className="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <Item label={t('students.col.number')} value={student.student_number} />
                                <Item label={t('students.form.gender')} value={enumLabel(t, 'gender', student.gender)} />
                                <Item label={t('students.form.dob')} value={student.date_of_birth} />
                                <Item label={t('students.form.placeOfBirth')} value={student.place_of_birth} />
                                <Item label={t('students.form.nationality')} value={student.nationality} />
                                <Item label={t('students.form.religion')} value={student.religion} />
                                <Item label={t('students.col.class')} value={student.class} />
                                <Item label={t('students.show.level')} value={student.level} />
                                <Item label={t('students.form.admittedOn')} value={student.admitted_on} />
                                <Item label={t('students.form.previousSchool')} value={student.previous_school} />
                                <Item label={t('students.form.address')} value={student.address} />
                                <Item label={t('students.form.emergency')} value={[student.emergency_contact?.name, student.emergency_contact?.relationship, student.emergency_contact?.phone].filter(Boolean).join(' · ')} />
                                {student.withdrawn_on ? <Item label={t('students.show.withdrawnOn')} value={student.withdrawn_on} /> : null}
                                {student.withdrawal_reason ? <Item label={t('acad.common.reason')} value={student.withdrawal_reason} /> : null}
                            </dl>
                        </div>
                    </Card>
                    {sensitive ? (
                        <Card title={t('students.form.sensitive')}>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Item label={t('students.form.medical')} value={sensitive.medical_notes} />
                                <Item label={t('students.form.welfare')} value={sensitive.welfare_notes} />
                            </dl>
                        </Card>
                    ) : null}
                    {can.move ? <MoveForm student={student} classes={classes} /> : null}
                </div>
            ) : null}

            {tab === 'academic' ? (
                <div className="space-y-6">
                    <div className="rounded-xl bg-white p-4 shadow-sm">
                        <DataTable columns={gradeColumns} rows={grades.map((g, i) => ({ ...g, id: i }))} emptyMessage={t('results.empty')} />
                    </div>
                    {history.length > 0 ? (
                        <Card title={t('students.show.performance')}>
                            <DataTable
                                columns={[
                                    { key: 'examination', label: t('results.col.examination') },
                                    { key: 'term', label: t('results.col.term'), render: (r) => r.term ?? '—' },
                                    { key: 'subjects', label: t('results.col.subjects') },
                                    { key: 'average', label: t('results.col.average') },
                                    { key: 'letter', label: t('results.col.grade') },
                                ]}
                                rows={history.map((h, i) => ({ ...h, id: i }))}
                            />
                        </Card>
                    ) : null}
                </div>
            ) : null}

            {tab === 'attendance' ? (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-4">
                        <Stat label={t('attendance.stat.sessions')} value={attendance.total ?? 0} />
                        <Stat label={t('attendance.stat.attended')} value={attendance.attended ?? 0} tone="green" />
                        <Stat label={t('attendance.stat.absent')} value={attendance.absent ?? 0} tone="red" />
                        <Stat label={t('attendance.stat.rate')} value={`${attendance.rate ?? 0}%`} />
                    </div>
                    {attendanceUrl ? (
                        <Link href={attendanceUrl} className="btn-outline">
                            {t('students.show.fullAttendance')}
                        </Link>
                    ) : null}
                </div>
            ) : null}

            {tab === 'guardians' ? (
                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <DataTable
                        columns={[
                            { key: 'full_name', label: t('students.col.name') },
                            { key: 'relationship', label: t('students.form.guardianRelationship'), render: (r) => r.relationship ?? '—' },
                            { key: 'phone', label: t('students.form.guardianPhone'), render: (r) => r.phone ?? '—' },
                            { key: 'email', label: t('students.form.guardianEmail'), render: (r) => r.email ?? '—' },
                            { key: 'has_account', label: t('students.show.portalAccount'), render: (r) => (r.has_account ? t('acad.common.yes') : t('acad.common.no')) },
                        ]}
                        rows={guardians.map((g, i) => ({ ...g, id: i }))}
                        emptyMessage={t('students.show.noGuardians')}
                    />
                </div>
            ) : null}

            {tab === 'documents' ? <Documents student={student} documents={documents} canUpload={can.documents} documentTypes={['birth_certificate', 'previous_report', 'transfer_letter', 'medical', 'photo', 'other']} /> : null}

            {tab === 'history' ? (
                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <DataTable
                        columns={[
                            { key: 'date', label: t('acad.common.date'), render: (r) => r.date ?? '—' },
                            { key: 'event', label: t('students.show.event'), render: (r) => enumLabel(t, 'movement', r.event) },
                            { key: 'from', label: t('students.show.from'), render: (r) => r.from ?? '—' },
                            { key: 'to', label: t('students.show.to'), render: (r) => r.to ?? '—' },
                            { key: 'reason', label: t('acad.common.reason'), render: (r) => r.reason ?? '—', cellClassName: 'whitespace-normal' },
                        ]}
                        rows={movements}
                        emptyMessage={t('students.show.noHistory')}
                    />
                </div>
            ) : null}
        </AppShell>
    );
}
