import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Select, Textarea } from '@/Components/SchoolOS/Form';
import BoardingLayout from '@/Components/SchoolOS/BoardingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { useForm } from '@inertiajs/react';

export default function Welfare(props) {
    const { records = [], students = [] } = props;
    const { t } = useLocale();
    const f = useForm({ student_id: '', kind: 'pastoral', note: '' });

    return (
        <BoardingLayout title={t('bd.wel.title')} subtitle={t('bd.wel.subtitle')} current="welfare" shell={props}>
            <p className="mb-4 text-sm text-slate-600">{t('bd.wel.note')}</p>
            <DataTable
                columns={[
                    { key: 'at', label: t('acad.common.date') },
                    { key: 'student', label: t('tr.student') },
                    { key: 'kind', label: t('inv.col.type'), render: (r) => enumLabel(t, 'bd.welKind', r.kind) },
                    { key: 'note', label: t('bd.wel.body'), cellClassName: 'max-w-md whitespace-normal' },
                    { key: 'by', label: t('inv.col.by') },
                ]}
                rows={records}
                emptyMessage={t('bd.wel.empty')}
            />
            <Card title={t('bd.wel.add')} className="mt-8 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); f.post(route('boarding.welfare.store'), { preserveScroll: true, onSuccess: () => f.reset('note') }); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('tr.student')}>
                            <Select value={f.data.student_id} onChange={(e) => f.setData('student_id', e.target.value)} required>
                                <option value="">{t('acad.common.choose')}</option>
                                {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </Select>
                        </Field>
                        <Field label={t('inv.col.type')}>
                            <Select value={f.data.kind} onChange={(e) => f.setData('kind', e.target.value)}>
                                {['health', 'pastoral', 'discipline', 'visitor', 'other'].map((k) => <option key={k} value={k}>{t(`bd.welKind.${k}`)}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <Field label={t('bd.wel.body')}><Textarea rows={4} maxLength={4000} value={f.data.note} onChange={(e) => f.setData('note', e.target.value)} required /></Field>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('bd.wel.save')}</button>
                </form>
            </Card>
        </BoardingLayout>
    );
}
