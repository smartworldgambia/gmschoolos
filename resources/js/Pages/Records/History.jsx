import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

const GRADES = [7, 8, 9, 10, 11, 12];

export default function History({ student, records = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        grade_number: 7,
        term_sequence: 1,
        academic_year_label: '',
        entries: [{ subject: '', mark: '', grade: '' }],
    });

    function setEntry(i, patch) {
        setData('entries', data.entries.map((e, j) => (j === i ? { ...e, ...patch } : e)));
    }

    function submit(e) {
        e.preventDefault();
        post(route('records.history.store', student.id), { preserveScroll: true, onSuccess: () => reset('entries', 'academic_year_label') });
    }

    return (
        <AppShell title={t('rec.historyTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('rec.historyTitle')} />
            <PageHeader
                title={t('rec.historyTitle')}
                subtitle={`${student.name} · ${student.student_number}`}
                actions={
                    <Link href={route('records.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <p className="mb-6 text-sm text-slate-600">{t('rec.historyHelp')}</p>

            <Card title={t('rec.historyAdd')} className="mb-6">
                <form onSubmit={submit} className="space-y-4">
                    <ErrorSummary errors={errors} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label={t('rec.historyGrade')}>
                            <Select value={data.grade_number} onChange={(e) => setData('grade_number', e.target.value)}>
                                {GRADES.map((g) => (
                                    <option key={g} value={g}>
                                        {t('rec.gradeN', { n: g })}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('rec.historyTerm')}>
                            <Select value={data.term_sequence} onChange={(e) => setData('term_sequence', e.target.value)}>
                                {[1, 2, 3].map((n) => (
                                    <option key={n} value={n}>
                                        {t('rec.termN', { n })}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('rec.historyYear')}>
                            <Input value={data.academic_year_label} onChange={(e) => setData('academic_year_label', e.target.value)} required maxLength={40} placeholder="2024/2025" dir="ltr" />
                        </Field>
                    </div>
                    <div className="space-y-2">
                        {data.entries.map((en, i) => (
                            <div key={i} className="grid grid-cols-[1fr_6rem_6rem_auto] items-center gap-2">
                                <Input placeholder={t('ext.col.subject')} value={en.subject} onChange={(e) => setEntry(i, { subject: e.target.value })} required maxLength={100} />
                                <Input type="number" step="any" placeholder={t('rec.mark')} value={en.mark} onChange={(e) => setEntry(i, { mark: e.target.value })} />
                                <Input dir="ltr" placeholder={t('ext.col.grade')} value={en.grade} onChange={(e) => setEntry(i, { grade: e.target.value })} maxLength={12} />
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setData('entries', data.entries.filter((_, j) => j !== i))}>
                                    {t('acad.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <button type="button" className="btn-outline" onClick={() => setData('entries', [...data.entries, { subject: '', mark: '', grade: '' }])}>
                            {t('rec.addSubjectRow')}
                        </button>
                        <button type="submit" className="btn-teal" disabled={processing}>
                            {t('acad.common.save')}
                        </button>
                    </div>
                </form>
            </Card>

            <div className="space-y-3">
                {records.length === 0 ? <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('rec.historyNone')}</p> : null}
                {records.map((r) => (
                    <Card
                        key={r.id}
                        title={`${t('rec.gradeN', { n: r.grade_number })} · ${t('rec.termN', { n: r.term_sequence })} · ${r.academic_year_label}`}
                        actions={
                            <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => router.delete(route('records.history.destroy', [student.id, r.id]), { preserveScroll: true })}>
                                {t('acad.common.remove')}
                            </button>
                        }
                    >
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-slate-100">
                                {(r.entries ?? []).map((e, i) => (
                                    <tr key={i}>
                                        <td className="py-1">{e.subject}</td>
                                        <td className="py-1 text-end">{e.mark ?? '—'}</td>
                                        <td className="py-1 text-end font-semibold">{e.grade ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Card>
                ))}
            </div>
        </AppShell>
    );
}
