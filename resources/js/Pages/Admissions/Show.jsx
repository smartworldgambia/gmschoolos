import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

function Item({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-navy">{value || '—'}</dd>
        </div>
    );
}

function Decision({ application, transitions, classes }) {
    const { t } = useLocale();
    const flashError = usePage().props.flash?.error;
    const { data, setData, patch, processing, errors } = useForm({
        status: transitions[0] ?? '',
        reason: '',
        school_class_id: application.preferred_class_id ?? '',
        confirm_duplicate: false,
    });

    const needsReason = data.status === 'rejected';
    const enrolling = data.status === 'enrolled';

    function submit(e) {
        e.preventDefault();
        if (['rejected', 'enrolled'].includes(data.status) && !confirm(t(`admissions.show.confirm.${data.status}`))) return;
        patch(route('admissions.status', application.id), { preserveScroll: true });
    }

    return (
        <Card title={t('admissions.show.decision')}>
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="sm:col-span-2 lg:col-span-4">
                    <ErrorSummary errors={errors} />
                </div>
                <Field label={t('admissions.show.moveTo')}>
                    <Select value={data.status} onChange={(e) => setData('status', e.target.value)}>
                        {transitions.map((s) => (
                            <option key={s} value={s}>
                                {enumLabel(t, 'admStatus', s)}
                            </option>
                        ))}
                    </Select>
                </Field>
                {enrolling ? (
                    <Field label={t('admissions.show.enrolInto')} hint={t('admissions.show.capacityNote')}>
                        <Select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)}>
                            <option value="">{t('students.form.noClass')}</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ) : null}
                <Field label={t('acad.common.reason')} hint={needsReason ? t('admissions.show.reasonRequired') : t('acad.common.optional')}>
                    <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} required={needsReason} />
                </Field>
                <div className="flex items-end">
                    <button type="submit" className="btn-navy" disabled={processing || !data.status}>
                        {t('admissions.show.apply')}
                    </button>
                </div>
                {enrolling && flashError === 'err.student.duplicate' ? (
                    <label className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-2 lg:col-span-4">
                        <input type="checkbox" checked={data.confirm_duplicate} onChange={(e) => setData('confirm_duplicate', e.target.checked)} className="mt-0.5 rounded border-gray-300" />
                        <span>{t('students.form.confirmDuplicate')}</span>
                    </label>
                ) : null}
            </form>
        </Card>
    );
}

export default function Show({ application, documents = [], transitions = [], classes = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={application.full_name} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={application.full_name} />
            <PageHeader
                title={application.full_name}
                subtitle={`${application.application_number} · ${enumLabel(t, 'admSource', application.source)}`}
                actions={
                    <div className="flex items-center gap-2">
                        <StatusBadge status={application.status} label={enumLabel(t, 'admStatus', application.status)} />
                        {application.status === 'waitlisted' && application.waitlist_position ? <span className="text-sm text-slate-500">#{application.waitlist_position}</span> : null}
                        <Link href={route('admissions.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </div>
                }
            />

            <div className="space-y-6">
                {application.student ? (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        {t('admissions.show.enrolledAs')}{' '}
                        <Link href={application.student.url} className="font-semibold underline">
                            {application.student.student_number}
                        </Link>
                    </div>
                ) : null}

                {can.manage && transitions.length > 0 ? <Decision application={application} transitions={transitions} classes={classes} /> : null}
                {application.decision_reason ? (
                    <Card title={t('admissions.show.decisionReason')}>
                        <p className="text-sm text-slate-700">{application.decision_reason}</p>
                    </Card>
                ) : null}

                <Card title={t('admissions.form.child')}>
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Item label={t('students.form.gender')} value={enumLabel(t, 'gender', application.gender)} />
                        <Item label={t('students.form.dob')} value={application.date_of_birth} />
                        <Item label={t('admissions.form.preferredClass')} value={application.preferred_class} />
                        <Item label={t('students.form.placeOfBirth')} value={application.place_of_birth} />
                        <Item label={t('students.form.nationality')} value={application.nationality} />
                        <Item label={t('students.form.religion')} value={application.religion} />
                        <Item label={t('students.form.previousSchool')} value={application.previous_school} />
                        <Item label={t('students.form.address')} value={application.address} />
                        <Item label={t('admissions.show.received')} value={application.created_at} />
                    </dl>
                </Card>

                <Card title={t('admissions.form.guardian')}>
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Item label={t('students.form.guardianName')} value={application.guardian.name} />
                        <Item label={t('students.form.guardianRelationship')} value={application.guardian.relationship} />
                        <Item label={t('students.form.guardianPhone')} value={application.guardian.phone} />
                        <Item label={t('students.form.guardianEmail')} value={application.guardian.email} />
                        <Item label={t('students.form.emergencyName')} value={application.emergency_contact.name} />
                        <Item label={t('students.form.emergencyPhone')} value={application.emergency_contact.phone} />
                    </dl>
                </Card>

                <Card title={t('admissions.form.documents')}>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {documents.length === 0 ? <li className="py-2 text-slate-500">{t('students.show.noDocuments')}</li> : null}
                        {documents.map((d) => (
                            <li key={d.id} className="flex items-center justify-between py-2">
                                <span>
                                    <span className="font-medium text-navy">{enumLabel(t, 'docType', d.type)}</span>
                                    <span className="ms-2 text-slate-500">{d.name}</span>
                                </span>
                                <a href={d.url} className="text-teal hover:underline">
                                    {t('acad.common.download')}
                                </a>
                            </li>
                        ))}
                    </ul>
                </Card>

                {application.notes ? (
                    <Card title={t('admissions.form.notes')}>
                        <p className="whitespace-pre-line text-sm text-slate-700">{application.notes}</p>
                    </Card>
                ) : null}
            </div>
        </AppShell>
    );
}
