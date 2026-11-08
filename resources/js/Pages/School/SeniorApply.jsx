import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Head, Link, useForm } from '@inertiajs/react';

const empty = {
    first_name: '',
    last_name: '',
    gender: '',
    date_of_birth: '',
    nationality: '',
    previous_school: '',
    gabece_index_number: '',
    gabece_year: '',
    guardian_name: '',
    guardian_phone: '',
    guardian_email: '',
    address: '',
    programme_choice: '',
    notes: '',
    grades: {},
    documents: [{ type: 'gabece_slip', file: null }],
    consent: false,
    company_website: '', // honeypot: real visitors never see or fill this
};

/** Public Senior Secondary application. It only creates a reviewable application: no account, no student record, no offer. */
export default function SeniorApply({ school, siteUrl, legal = {}, round = null, rounds = [], documentTypes = [], submitUrl, applied = null }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, transform, reset } = useForm(empty);
    transform((d) => ({ ...d, documents: d.documents.filter((x) => x.file) }));

    function submit(e) {
        e.preventDefault();
        post(submitUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    }

    const text = (name, props = {}) => <Input value={data[name]} onChange={(e) => setData(name, e.target.value)} {...props} />;
    const err = (name) => errors[name];

    return (
        <div className="min-h-screen bg-surface">
            <Head title={`${t('ssa.title')} — ${school.name}`} />
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-4">
                    <div className="min-w-0">
                        <p className="truncate text-lg font-bold text-navy">{school.name}</p>
                        <p className="text-sm text-slate-500">{t('ssa.title')}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <LanguageSwitcher compact />
                        <Link href={siteUrl} className="btn-outline">
                            {t('apply.backToSite')}
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-4xl px-4 py-8">
                {applied ? (
                    <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900" role="status">
                        <p className="font-semibold">{t('ssa.received')}</p>
                        <p className="mt-1 text-sm">{t('ssa.receivedBody')}</p>
                        {applied !== 'SSA-RECEIVED' ? (
                            <p className="mt-2 text-sm">
                                {t('apply.reference')}: <span className="font-mono font-semibold">{applied}</span>
                            </p>
                        ) : null}
                    </div>
                ) : null}

                {round === null ? (
                    <Card title={t('ssa.openRounds')}>
                        {rounds.length === 0 ? <p className="text-sm text-slate-600">{t('ssa.noOpenRounds')}</p> : null}
                        <ul className="divide-y divide-slate-100">
                            {rounds.map((r) => (
                                <li key={r.url} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <div>
                                        <p className="font-medium text-navy">{r.name}</p>
                                        {r.closes_on ? (
                                            <p className="text-xs text-slate-500">
                                                {t('ssa.closesOn')}: {r.closes_on}
                                            </p>
                                        ) : null}
                                    </div>
                                    <a href={r.url} className="btn-teal">
                                        {t('ssa.apply')}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </Card>
                ) : !round.open ? (
                    <Card title={round.name}>
                        <p className="text-sm text-slate-700">{t('ssa.closed')}</p>
                    </Card>
                ) : (
                    <>
                        <h1 className="text-2xl font-bold text-navy">{round.name}</h1>
                        {round.closes_on ? (
                            <p className="mt-1 text-sm text-slate-500">
                                {t('ssa.closesOn')}: {round.closes_on}
                            </p>
                        ) : null}
                        {round.instructions ? <p className="mt-3 whitespace-pre-line rounded-lg bg-white p-4 text-sm text-slate-700 shadow-sm">{round.instructions}</p> : null}
                        <p className="my-4 text-sm text-slate-600">{t('ssa.intro')}</p>

                        <form onSubmit={submit} className="space-y-6">
                            <ErrorSummary errors={errors} />

                            <Card title={t('ssa.section.applicant')}>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label={t('ssa.firstName')} error={err('first_name')}>
                                        {text('first_name', { required: true, maxLength: 100 })}
                                    </Field>
                                    <Field label={t('ssa.lastName')} error={err('last_name')}>
                                        {text('last_name', { required: true, maxLength: 100 })}
                                    </Field>
                                    <Field label={t('ss.field.gender')} error={err('gender')}>
                                        <Select value={data.gender} onChange={(e) => setData('gender', e.target.value)} required>
                                            <option value="">{t('acad.common.choose')}</option>
                                            {['male', 'female', 'other'].map((g) => (
                                                <option key={g} value={g}>
                                                    {enumLabel(t, 'ss.gender', g)}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Field label={t('ss.field.dob')} error={err('date_of_birth')}>
                                        {text('date_of_birth', { type: 'date', required: true })}
                                    </Field>
                                    <Field label={t('ss.field.nationality')} error={err('nationality')}>
                                        {text('nationality', { maxLength: 100 })}
                                    </Field>
                                    <Field label={t('ss.field.previousSchool')} error={err('previous_school')}>
                                        {text('previous_school', { required: true, maxLength: 200 })}
                                    </Field>
                                    <Field label={t('ss.field.gabeceIndex')} error={err('gabece_index_number')} hint={t('ssa.indexHint')}>
                                        {text('gabece_index_number', { dir: 'ltr', maxLength: 60 })}
                                    </Field>
                                    <Field label={t('ss.field.gabeceYear')} error={err('gabece_year')}>
                                        {text('gabece_year', { type: 'number', min: 2000, max: 2100 })}
                                    </Field>
                                    {round.programmes.length > 0 ? (
                                        <Field label={t('ss.field.programme')} error={err('programme_choice')}>
                                            <Select value={data.programme_choice} onChange={(e) => setData('programme_choice', e.target.value)}>
                                                <option value="">{t('acad.common.choose')}</option>
                                                {round.programmes.map((p) => (
                                                    <option key={p} value={p}>
                                                        {p}
                                                    </option>
                                                ))}
                                            </Select>
                                        </Field>
                                    ) : null}
                                </div>
                            </Card>

                            <Card title={t('ssa.section.grades')}>
                                <p className="mb-3 text-sm text-slate-600">{t('ssa.gradesHelp')}</p>
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {round.subjects.map((s) => (
                                        <Field key={s.code} label={s.name}>
                                            <Select value={data.grades[s.code] ?? ''} onChange={(e) => setData('grades', { ...data.grades, [s.code]: e.target.value })}>
                                                <option value="">—</option>
                                                {round.grades.map((g) => (
                                                    <option key={g} value={g}>
                                                        {g}
                                                    </option>
                                                ))}
                                            </Select>
                                        </Field>
                                    ))}
                                </div>
                            </Card>

                            <Card title={t('ssa.section.guardian')}>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label={t('ss.field.guardian')} error={err('guardian_name')}>
                                        {text('guardian_name', { required: true, maxLength: 150 })}
                                    </Field>
                                    <Field label={t('ss.field.guardianPhone')} error={err('guardian_phone')}>
                                        {text('guardian_phone', { required: true, dir: 'ltr', maxLength: 50 })}
                                    </Field>
                                    <Field label={t('ss.field.guardianEmail')} error={err('guardian_email')}>
                                        {text('guardian_email', { type: 'email', dir: 'ltr', maxLength: 150 })}
                                    </Field>
                                    <Field label={t('ss.field.address')} error={err('address')}>
                                        {text('address', { maxLength: 255 })}
                                    </Field>
                                    <Field label={t('acad.common.notes')} className="sm:col-span-2" error={err('notes')}>
                                        <Textarea rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} maxLength={1000} />
                                    </Field>
                                </div>
                            </Card>

                            <Card
                                title={t('ssa.section.documents')}
                                actions={
                                    data.documents.length < 4 ? (
                                        <button type="button" className="btn-outline" onClick={() => setData('documents', [...data.documents, { type: 'other', file: null }])}>
                                            {t('acad.common.add')}
                                        </button>
                                    ) : null
                                }
                            >
                                <p className="mb-3 text-sm text-slate-600">{t('ssa.documentsHelp')}</p>
                                <div className="space-y-3">
                                    {data.documents.map((d, i) => (
                                        <div key={i} className="grid gap-2 sm:grid-cols-[14rem_1fr]">
                                            <Select value={d.type} onChange={(e) => setData('documents', data.documents.map((x, j) => (j === i ? { ...x, type: e.target.value } : x)))}>
                                                {documentTypes.map((x) => (
                                                    <option key={x} value={x}>
                                                        {enumLabel(t, 'ss.docType', x)}
                                                    </option>
                                                ))}
                                            </Select>
                                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="block w-full text-sm" onChange={(e) => setData('documents', data.documents.map((x, j) => (j === i ? { ...x, file: e.target.files[0] } : x)))} />
                                        </div>
                                    ))}
                                </div>
                            </Card>

                            {/* honeypot */}
                            <div className="absolute -start-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                                <label>
                                    Website
                                    <input type="text" tabIndex={-1} autoComplete="off" value={data.company_website} onChange={(e) => setData('company_website', e.target.value)} />
                                </label>
                            </div>

                            <label className="flex items-start gap-2 rounded-xl bg-white p-4 text-sm text-slate-700 shadow-sm">
                                <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 rounded border-gray-300" />
                                <span>
                                    {t('apply.consent')}{' '}
                                    {legal.privacy ? (
                                        <a href={legal.privacy} className="text-teal underline" target="_blank" rel="noreferrer">
                                            {t('apply.privacy')}
                                        </a>
                                    ) : null}
                                </span>
                            </label>
                            {errors.consent ? <p className="text-sm text-red-600">{t('apply.consentRequired')}</p> : null}

                            <button type="submit" className="btn-teal" disabled={processing}>
                                {t('ssa.submit')}
                            </button>
                            <p className="text-xs text-slate-500">{t('ssa.disclaimer')}</p>
                        </form>
                    </>
                )}
            </main>
        </div>
    );
}
