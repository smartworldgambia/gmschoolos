import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import SafeguardingLayout from '@/Components/SchoolOS/SafeguardingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Report(props) {
    const { students = [], categories = [], severities = [], canManage = false } = props;
    const { t } = useLocale();
    const f = useForm({ category: 'welfare', severity: 'medium', student_id: '', subject_name: '', summary: '', occurred_on: '' });
    f.transform((d) => ({ ...d, student_id: d.student_id || null, occurred_on: d.occurred_on || null }));

    return (
        <SafeguardingLayout title={t('sg.report.title')} subtitle={t('sg.report.subtitle')} current="report" canManage={canManage} shell={props}>
            <Card className="max-w-3xl">
                <p className="mb-4 text-sm text-slate-600">{t('sg.report.note')}</p>
                <form onSubmit={(e) => { e.preventDefault(); f.post(route('safeguarding.store')); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('inv.col.type')}>
                            <Select value={f.data.category} onChange={(e) => f.setData('category', e.target.value)}>
                                {categories.map((k) => <option key={k} value={k}>{t(`sg.category.${k}`)}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('sg.col.severity')}>
                            <Select value={f.data.severity} onChange={(e) => f.setData('severity', e.target.value)}>
                                {severities.map((k) => <option key={k} value={k}>{t(`sg.severity.${k}`)}</option>)}
                            </Select>
                        </Field>
                        {students.length ? (
                            <Field label={t('tr.student')} hint={t('sg.orName')}>
                                <Select value={f.data.student_id} onChange={(e) => f.setData('student_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </Field>
                        ) : null}
                        <Field label={t('sg.subjectName')}><Input value={f.data.subject_name} onChange={(e) => f.setData('subject_name', e.target.value)} /></Field>
                        <Field label={t('sg.occurred')}><Input type="date" value={f.data.occurred_on} onChange={(e) => f.setData('occurred_on', e.target.value)} /></Field>
                    </div>
                    <Field label={t('sg.summary')}><Textarea rows={6} maxLength={5000} value={f.data.summary} onChange={(e) => f.setData('summary', e.target.value)} required /></Field>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('sg.report.submit')}</button>
                </form>
            </Card>
        </SafeguardingLayout>
    );
}
