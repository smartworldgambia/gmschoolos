import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Select, Textarea } from '@/Components/SchoolOS/Form';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import SafeguardingLayout from '@/Components/SchoolOS/SafeguardingLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Link, useForm } from '@inertiajs/react';

export default function Show(props) {
    const { case: c, officers = [] } = props;
    const { t } = useLocale();
    const note = useForm({ body: '' });
    const assign = useForm({ assigned_to: c.assigned_to ?? '' });

    return (
        <SafeguardingLayout title={c.reference} subtitle={enumLabel(t, 'sg.category', c.category)} current="cases" canManage shell={props} actions={<Link href={route('safeguarding.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <p className="mb-4 text-sm text-slate-600">{t('sg.accessLogged')}</p>
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <StatusBadge status={c.severity === 'critical' ? 'critical' : 'pending'} label={enumLabel(t, 'sg.severity', c.severity)} />
                <StatusBadge status={c.status} label={enumLabel(t, 'sg.status', c.status)} />
                <span>{c.student || c.subject_name}</span>
                <span className="text-slate-500">{c.reporter}</span>
            </div>
            <Card title={t('sg.summary')} className="mb-6 max-w-3xl">
                <p className="whitespace-pre-wrap text-sm text-navy">{c.summary}</p>
            </Card>
            {c.status !== 'closed' ? (
                <div className="mb-6 grid gap-6 lg:grid-cols-2">
                    <Card title={t('sg.assign')}>
                        <form onSubmit={(e) => { e.preventDefault(); assign.post(route('safeguarding.assign', c.id), { preserveScroll: true }); }} className="space-y-3">
                            <ErrorSummary errors={assign.errors} />
                            <Field label={t('sg.col.assignee')}>
                                <Select value={assign.data.assigned_to} onChange={(e) => assign.setData('assigned_to', e.target.value)} required>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {officers.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
                                </Select>
                            </Field>
                            <button type="submit" className="btn-teal" disabled={assign.processing}>{t('sg.assign')}</button>
                        </form>
                    </Card>
                    <Card title={t('sg.addNote')}>
                        <form onSubmit={(e) => { e.preventDefault(); note.post(route('safeguarding.notes', c.id), { preserveScroll: true, onSuccess: () => note.reset() }); }} className="space-y-3">
                            <ErrorSummary errors={note.errors} />
                            <Field label={t('sg.noteBody')}><Textarea rows={4} maxLength={5000} value={note.data.body} onChange={(e) => note.setData('body', e.target.value)} required /></Field>
                            <button type="submit" className="btn-teal" disabled={note.processing}>{t('sg.saveNote')}</button>
                        </form>
                    </Card>
                </div>
            ) : null}
            <h2 className="mb-3 text-base font-semibold text-navy">{t('sg.notes')}</h2>
            <DataTable columns={[{ key: 'at', label: t('acad.common.date') }, { key: 'author', label: t('inv.col.by') }, { key: 'body', label: t('sg.noteBody'), cellClassName: 'max-w-md whitespace-pre-wrap' }]} rows={c.notes} emptyMessage={t('sg.noNotes')} />
            {c.status !== 'closed' ? (
                <Card title={t('sg.close')} className="mt-8 max-w-xl">
                    <p className="mb-3 text-sm text-slate-600">{t('sg.closeNote')}</p>
                    <ReasonAction url={route('safeguarding.close', c.id)} fieldName="closure_note" label={t('sg.close')} tone="danger" reasonLabel={t('sg.closure')} />
                </Card>
            ) : (
                <p className="mt-6 text-sm text-slate-600">{t('sg.closedOn')}: {c.closed_at}<br />{c.closure_note}</p>
            )}
        </SafeguardingLayout>
    );
}
