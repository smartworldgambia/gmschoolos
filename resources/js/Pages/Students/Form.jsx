import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { GENDERS, enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Form({ student = null, classes = [], canSensitive = false, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const editing = Boolean(student);
    const flashError = usePage().props.flash?.error;
    const duplicate = flashError === 'err.student.duplicate';

    const { data, setData, post, processing, errors } = useForm({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        gender: student?.gender ?? '',
        date_of_birth: student?.date_of_birth ?? '',
        place_of_birth: student?.place_of_birth ?? '',
        nationality: student?.nationality ?? '',
        religion: student?.religion ?? '',
        school_class_id: student?.school_class_id ?? '',
        admitted_on: student?.admitted_on ?? '',
        previous_school: student?.previous_school ?? '',
        address: student?.address ?? '',
        emergency_contact_name: student?.emergency_contact_name ?? '',
        emergency_contact_phone: student?.emergency_contact_phone ?? '',
        emergency_contact_relationship: student?.emergency_contact_relationship ?? '',
        medical_notes: student?.medical_notes ?? '',
        welfare_notes: student?.welfare_notes ?? '',
        guardian: { name: '', relationship: '', phone: '', email: '' },
        confirm_duplicate: false,
        photo: null,
        ...(editing ? { _method: 'put' } : {}),
    });

    function submit(e) {
        e.preventDefault();
        post(editing ? route('students.update', student.id) : route('students.store'), { forceFormData: true });
    }

    const set = (key) => (e) => setData(key, e.target.value);
    const setGuardian = (key) => (e) => setData('guardian', { ...data.guardian, [key]: e.target.value });

    return (
        <AppShell title={t(editing ? 'students.form.editTitle' : 'students.form.createTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t(editing ? 'students.form.editTitle' : 'students.form.createTitle')} />
            <PageHeader
                title={t(editing ? 'students.form.editTitle' : 'students.form.createTitle')}
                subtitle={editing ? `${t('students.col.number')}: ${student.student_number}` : t('students.form.numberNote')}
                actions={
                    <Link href={editing ? route('students.show', student.id) : route('students.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <form onSubmit={submit} className="space-y-6">
                <ErrorSummary errors={errors} />

                <Card title={t('students.form.identity')}>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label={t('students.form.firstName')} error={errors.first_name}>
                            <Input value={data.first_name} onChange={set('first_name')} required />
                        </Field>
                        <Field label={t('students.form.lastName')} error={errors.last_name}>
                            <Input value={data.last_name} onChange={set('last_name')} required />
                        </Field>
                        <Field label={t('students.form.gender')} error={errors.gender}>
                            <Select value={data.gender} onChange={set('gender')} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {GENDERS.map((g) => (
                                    <option key={g} value={g}>
                                        {enumLabel(t, 'gender', g)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('students.form.dob')} error={errors.date_of_birth}>
                            <Input type="date" value={data.date_of_birth} onChange={set('date_of_birth')} required />
                        </Field>
                        <Field label={t('students.form.placeOfBirth')} error={errors.place_of_birth}>
                            <Input value={data.place_of_birth} onChange={set('place_of_birth')} />
                        </Field>
                        <Field label={t('students.form.nationality')} error={errors.nationality}>
                            <Input value={data.nationality} onChange={set('nationality')} />
                        </Field>
                        <Field label={t('students.form.religion')} error={errors.religion}>
                            <Input value={data.religion} onChange={set('religion')} />
                        </Field>
                        <Field label={t('students.form.photo')} error={errors.photo} hint={t('students.form.photoHint')}>
                            <input type="file" accept="image/png,image/jpeg" onChange={(e) => setData('photo', e.target.files[0])} className="block w-full text-sm" />
                        </Field>
                    </div>
                </Card>

                <Card title={t('students.form.enrolment')}>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {!editing ? (
                            <Field label={t('students.form.class')} error={errors.school_class_id} hint={t('students.form.classHint')}>
                                <Select value={data.school_class_id} onChange={set('school_class_id')}>
                                    <option value="">{t('students.form.noClass')}</option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        ) : null}
                        <Field label={t('students.form.admittedOn')} error={errors.admitted_on}>
                            <Input type="date" value={data.admitted_on} onChange={set('admitted_on')} />
                        </Field>
                        <Field label={t('students.form.previousSchool')} error={errors.previous_school}>
                            <Input value={data.previous_school} onChange={set('previous_school')} />
                        </Field>
                        <Field label={t('students.form.address')} error={errors.address} className="sm:col-span-2 lg:col-span-3">
                            <Input value={data.address} onChange={set('address')} />
                        </Field>
                    </div>
                </Card>

                {!editing ? (
                    <Card title={t('students.form.guardian')}>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field label={t('students.form.guardianName')} error={errors['guardian.name']}>
                                <Input value={data.guardian.name} onChange={setGuardian('name')} />
                            </Field>
                            <Field label={t('students.form.guardianRelationship')} error={errors['guardian.relationship']}>
                                <Input value={data.guardian.relationship} onChange={setGuardian('relationship')} />
                            </Field>
                            <Field label={t('students.form.guardianPhone')} error={errors['guardian.phone']}>
                                <Input value={data.guardian.phone} onChange={setGuardian('phone')} />
                            </Field>
                            <Field label={t('students.form.guardianEmail')} error={errors['guardian.email']}>
                                <Input type="email" value={data.guardian.email} onChange={setGuardian('email')} />
                            </Field>
                        </div>
                        <p className="mt-3 text-xs text-slate-500">{t('students.form.guardianHint')}</p>
                    </Card>
                ) : null}

                <Card title={t('students.form.emergency')}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label={t('students.form.emergencyName')} error={errors.emergency_contact_name}>
                            <Input value={data.emergency_contact_name} onChange={set('emergency_contact_name')} />
                        </Field>
                        <Field label={t('students.form.emergencyPhone')} error={errors.emergency_contact_phone}>
                            <Input value={data.emergency_contact_phone} onChange={set('emergency_contact_phone')} />
                        </Field>
                        <Field label={t('students.form.emergencyRelationship')} error={errors.emergency_contact_relationship}>
                            <Input value={data.emergency_contact_relationship} onChange={set('emergency_contact_relationship')} />
                        </Field>
                    </div>
                </Card>

                {canSensitive ? (
                    <Card title={t('students.form.sensitive')}>
                        <p className="mb-3 text-xs text-amber-700">{t('students.form.sensitiveHint')}</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('students.form.medical')} error={errors.medical_notes}>
                                <Textarea value={data.medical_notes} onChange={set('medical_notes')} />
                            </Field>
                            <Field label={t('students.form.welfare')} error={errors.welfare_notes}>
                                <Textarea value={data.welfare_notes} onChange={set('welfare_notes')} />
                            </Field>
                        </div>
                    </Card>
                ) : null}

                {duplicate ? (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <label className="flex items-start gap-2">
                            <input type="checkbox" checked={data.confirm_duplicate} onChange={(e) => setData('confirm_duplicate', e.target.checked)} className="mt-0.5 rounded border-gray-300" />
                            <span>{t('students.form.confirmDuplicate')}</span>
                        </label>
                    </div>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t(editing ? 'students.form.saveChanges' : 'students.form.save')}
                    </button>
                    <Link href={editing ? route('students.show', student.id) : route('students.index')} className="btn-outline">
                        {t('acad.common.cancel')}
                    </Link>
                </div>
            </form>
        </AppShell>
    );
}
