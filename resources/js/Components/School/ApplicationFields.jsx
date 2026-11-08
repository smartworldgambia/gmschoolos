import { Card, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { GENDERS, enumLabel } from '@/lib/academic';

export const emptyApplication = {
    first_name: '',
    last_name: '',
    gender: '',
    date_of_birth: '',
    place_of_birth: '',
    nationality: '',
    religion: '',
    previous_school: '',
    address: '',
    preferred_class_id: '',
    guardian_name: '',
    guardian_relationship: '',
    guardian_phone: '',
    guardian_email: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    notes: '',
    documents: [{ type: 'birth_certificate', file: null }],
};

/** Shared by the walk-in desk form and the public online application. */
export default function ApplicationFields({ data, setData, errors, classes = [], documentTypes = [] }) {
    const { t } = useLocale();
    const set = (key) => (e) => setData(key, e.target.value);
    const setDoc = (index, key, value) =>
        setData(
            'documents',
            data.documents.map((d, i) => (i === index ? { ...d, [key]: value } : d)),
        );

    return (
        <div className="space-y-6">
            <Card title={t('admissions.form.child')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label={t('students.form.firstName')} error={errors.first_name}>
                        <Input value={data.first_name} onChange={set('first_name')} required autoComplete="off" />
                    </Field>
                    <Field label={t('students.form.lastName')} error={errors.last_name}>
                        <Input value={data.last_name} onChange={set('last_name')} required autoComplete="off" />
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
                    <Field label={t('admissions.form.preferredClass')} error={errors.preferred_class_id}>
                        <Select value={data.preferred_class_id} onChange={set('preferred_class_id')}>
                            <option value="">{t('admissions.form.noPreference')}</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('students.form.previousSchool')} error={errors.previous_school}>
                        <Input value={data.previous_school} onChange={set('previous_school')} />
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
                    <Field label={t('students.form.address')} error={errors.address} className="sm:col-span-2 lg:col-span-3">
                        <Input value={data.address} onChange={set('address')} />
                    </Field>
                </div>
            </Card>

            <Card title={t('admissions.form.guardian')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('students.form.guardianName')} error={errors.guardian_name}>
                        <Input value={data.guardian_name} onChange={set('guardian_name')} required />
                    </Field>
                    <Field label={t('students.form.guardianRelationship')} error={errors.guardian_relationship}>
                        <Input value={data.guardian_relationship} onChange={set('guardian_relationship')} />
                    </Field>
                    <Field label={t('students.form.guardianPhone')} error={errors.guardian_phone}>
                        <Input value={data.guardian_phone} onChange={set('guardian_phone')} required inputMode="tel" />
                    </Field>
                    <Field label={t('students.form.guardianEmail')} error={errors.guardian_email}>
                        <Input type="email" value={data.guardian_email} onChange={set('guardian_email')} />
                    </Field>
                    <Field label={t('students.form.emergencyName')} error={errors.emergency_contact_name}>
                        <Input value={data.emergency_contact_name} onChange={set('emergency_contact_name')} />
                    </Field>
                    <Field label={t('students.form.emergencyPhone')} error={errors.emergency_contact_phone}>
                        <Input value={data.emergency_contact_phone} onChange={set('emergency_contact_phone')} inputMode="tel" />
                    </Field>
                </div>
            </Card>

            <Card title={t('admissions.form.documents')}>
                <p className="mb-3 text-xs text-slate-500">{t('admissions.form.documentsHint')}</p>
                <div className="space-y-3">
                    {data.documents.map((doc, i) => (
                        <div key={i} className="flex flex-wrap items-end gap-3">
                            <Field label={t('students.show.documentType')} error={errors[`documents.${i}.type`]}>
                                <Select value={doc.type} onChange={(e) => setDoc(i, 'type', e.target.value)} className="!w-auto">
                                    {documentTypes.map((x) => (
                                        <option key={x} value={x}>
                                            {enumLabel(t, 'docType', x)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('students.show.documentFile')} error={errors[`documents.${i}.file`]}>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setDoc(i, 'file', e.target.files[0])} className="block w-full min-w-0 text-sm" />
                            </Field>
                            {data.documents.length > 1 ? (
                                <button type="button" className="text-sm text-red-600 hover:underline" onClick={() => setData('documents', data.documents.filter((_, j) => j !== i))}>
                                    {t('acad.common.remove')}
                                </button>
                            ) : null}
                        </div>
                    ))}
                </div>
                {data.documents.length < 5 ? (
                    <button type="button" className="btn-outline mt-3" onClick={() => setData('documents', [...data.documents, { type: 'other', file: null }])}>
                        {t('admissions.form.addDocument')}
                    </button>
                ) : null}
            </Card>

            <Card title={t('admissions.form.notes')}>
                <Field error={errors.notes}>
                    <Textarea value={data.notes} onChange={set('notes')} />
                </Field>
            </Card>
        </div>
    );
}
