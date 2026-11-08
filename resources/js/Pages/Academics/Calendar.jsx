import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function TermRow({ term, canManage }) {
    const { t } = useLocale();
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({ name: term.name, starts_on: term.starts_on, ends_on: term.ends_on });

    if (!editing) {
        return (
            <li className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span>
                    <span className="font-medium text-navy">{term.name}</span>
                    <span className="ms-2 text-slate-500">
                        {term.starts_on} → {term.ends_on}
                    </span>
                    {term.is_current ? <StatusBadge status="current" label={t('calendar.current')} className="ms-2" /> : null}
                </span>
                {canManage ? (
                    <span className="inline-flex gap-3 whitespace-nowrap">
                        {!term.is_current ? (
                            <button type="button" className="text-teal hover:underline" onClick={() => router.post(route('academics.terms.current', term.id), {}, { preserveScroll: true })}>
                                {t('calendar.makeCurrent')}
                            </button>
                        ) : null}
                        <button type="button" className="text-teal hover:underline" onClick={() => setEditing(true)}>
                            {t('acad.common.edit')}
                        </button>
                    </span>
                ) : null}
            </li>
        );
    }

    return (
        <li className="py-3">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    put(route('academics.terms.update', term.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <ErrorSummary errors={errors} />
                <Field label={t('calendar.termName')}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                </Field>
                <Field label={t('calendar.starts')}>
                    <Input type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} required />
                </Field>
                <Field label={t('calendar.ends')}>
                    <Input type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} required />
                </Field>
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('acad.common.save')}
                </button>
                <button type="button" className="btn-outline" onClick={() => setEditing(false)}>
                    {t('acad.common.cancel')}
                </button>
            </form>
        </li>
    );
}

function NewYear() {
    const { t } = useLocale();
    const blank = { name: '', starts_on: '', ends_on: '' };
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        starts_on: '',
        ends_on: '',
        make_current: false,
        terms: [1, 2, 3].map((n) => ({ ...blank, name: `${t('calendar.termDefault')} ${n}` })),
    });
    const setTerm = (i, key, value) => setData('terms', data.terms.map((x, j) => (j === i ? { ...x, [key]: value } : x)));

    return (
        <Card title={t('calendar.newYear')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('academics.years.store'), { preserveScroll: true, onSuccess: () => reset() });
                }}
                className="space-y-4"
            >
                <ErrorSummary errors={errors} />
                <p className="text-xs text-slate-500">{t('calendar.datesHint')}</p>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label={t('calendar.yearName')}>
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="2026/27" required />
                    </Field>
                    <Field label={t('calendar.starts')}>
                        <Input type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} required />
                    </Field>
                    <Field label={t('calendar.ends')}>
                        <Input type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} required />
                    </Field>
                </div>
                <div className="space-y-3">
                    {data.terms.map((term, i) => (
                        <div key={i} className="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                            <Field label={t('calendar.termName')}>
                                <Input value={term.name} onChange={(e) => setTerm(i, 'name', e.target.value)} required />
                            </Field>
                            <Field label={t('calendar.starts')}>
                                <Input type="date" value={term.starts_on} onChange={(e) => setTerm(i, 'starts_on', e.target.value)} required />
                            </Field>
                            <Field label={t('calendar.ends')}>
                                <Input type="date" value={term.ends_on} onChange={(e) => setTerm(i, 'ends_on', e.target.value)} required />
                            </Field>
                            {data.terms.length > 1 ? (
                                <button type="button" className="pb-2 text-sm text-red-600 hover:underline" onClick={() => setData('terms', data.terms.filter((_, j) => j !== i))}>
                                    {t('acad.common.remove')}
                                </button>
                            ) : null}
                        </div>
                    ))}
                    {data.terms.length < 8 ? (
                        <button type="button" className="btn-outline" onClick={() => setData('terms', [...data.terms, { ...blank, name: `${t('calendar.termDefault')} ${data.terms.length + 1}` }])}>
                            {t('calendar.addTerm')}
                        </button>
                    ) : null}
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" className="rounded border-gray-300" checked={data.make_current} onChange={(e) => setData('make_current', e.target.checked)} />
                    {t('calendar.makeCurrentYear')}
                </label>
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('calendar.createYear')}
                </button>
            </form>
        </Card>
    );
}

function NewEvent({ eventTypes }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ type: eventTypes[0], title: '', starts_on: '', ends_on: '', notes: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('academics.events.store'), { preserveScroll: true, onSuccess: () => reset() });
            }}
            className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
        >
            <div className="sm:col-span-2 lg:col-span-5">
                <ErrorSummary errors={errors} />
            </div>
            <Field label={t('calendar.eventType')}>
                <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                    {eventTypes.map((x) => (
                        <option key={x} value={x}>
                            {enumLabel(t, 'eventType', x)}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label={t('calendar.eventTitle')}>
                <Input value={data.title} onChange={(e) => setData('title', e.target.value)} required />
            </Field>
            <Field label={t('calendar.starts')}>
                <Input type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} required />
            </Field>
            <Field label={t('calendar.endsOptional')}>
                <Input type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} />
            </Field>
            <button type="submit" className="btn-teal" disabled={processing}>
                {t('calendar.addEvent')}
            </button>
            <Field label={t('acad.common.notes')} className="sm:col-span-2 lg:col-span-5">
                <Textarea rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </Field>
        </form>
    );
}

export default function Calendar({ years = [], events = [], eventTypes = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('calendar.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('calendar.title')} />
            <PageHeader
                title={t('calendar.title')}
                subtitle={t('calendar.subtitle')}
                actions={
                    <Link href={route('academics.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <div className="space-y-6">
                {years.length === 0 ? <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('calendar.noYears')}</p> : null}
                {years.map((y) => (
                    <Card
                        key={y.id}
                        title={`${y.name}  (${y.starts_on} → ${y.ends_on})`}
                        actions={
                            <>
                                {y.is_current ? <StatusBadge status="current" label={t('calendar.currentYear')} /> : null}
                                {can.manage && !y.is_current ? (
                                    <button type="button" className="btn-outline" onClick={() => router.post(route('academics.years.current', y.id), {}, { preserveScroll: true })}>
                                        {t('calendar.makeCurrentYear')}
                                    </button>
                                ) : null}
                            </>
                        }
                    >
                        <ul className="divide-y divide-slate-100">
                            {y.terms.map((term) => (
                                <TermRow key={term.id} term={term} canManage={can.manage} />
                            ))}
                        </ul>
                    </Card>
                ))}

                {can.manage ? <NewYear /> : null}

                <Card title={t('calendar.events')}>
                    <ul className="divide-y divide-slate-100 text-sm">
                        {events.length === 0 ? <li className="py-3 text-slate-500">{t('calendar.noEvents')}</li> : null}
                        {events.map((e) => (
                            <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>
                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{enumLabel(t, 'eventType', e.type)}</span>
                                    <span className="ms-2 font-medium text-navy">{e.title}</span>
                                    <span className="ms-2 text-slate-500">
                                        {e.starts_on}
                                        {e.ends_on && e.ends_on !== e.starts_on ? ` → ${e.ends_on}` : ''}
                                    </span>
                                    {e.notes ? <span className="ms-2 text-xs text-slate-400">{e.notes}</span> : null}
                                </span>
                                {can.manage ? (
                                    <button type="button" className="text-red-600 hover:underline" onClick={() => confirm(t('academics.confirmRemove')) && router.delete(route('academics.events.destroy', e.id), { preserveScroll: true })}>
                                        {t('acad.common.remove')}
                                    </button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    {can.manage ? <NewEvent eventTypes={eventTypes} /> : null}
                </Card>
            </div>
        </AppShell>
    );
}
