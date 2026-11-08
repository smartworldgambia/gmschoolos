import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import EventsLayout from '@/Components/SchoolOS/EventsLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import { Link, router, useForm } from '@inertiajs/react';

function EmptyIllustration() {
    return (
        <svg viewBox="0 0 240 180" className="mx-auto h-40 w-auto sm:h-48" aria-hidden="true">
            <circle cx="120" cy="86" r="68" fill="#EAF3FF" />
            <path d="M196 46l2.4 5.6 5.6 2.4-5.6 2.4-2.4 5.6-2.4-5.6-5.6-2.4 5.6-2.4 2.4-5.6z" fill="#2878D0" opacity="0.75" />
            <circle cx="42" cy="48" r="4" fill="#08B7A5" opacity="0.45" />
            <rect x="70" y="44" width="100" height="88" rx="14" fill="#FFFFFF" stroke="#D7E6F5" strokeWidth="2.2" />
            <rect x="70" y="44" width="100" height="24" rx="14" fill="#08B7A5" />
            <rect x="70" y="56" width="100" height="12" fill="#08B7A5" />
            <circle cx="92" cy="56" r="5" fill="#FFFFFF" />
            <circle cx="148" cy="56" r="5" fill="#FFFFFF" />
            <rect x="88" y="82" width="16" height="14" rx="3" fill="#E3EAF2" />
            <rect x="112" y="82" width="16" height="14" rx="3" fill="#08B7A5" opacity="0.85" />
            <rect x="136" y="82" width="16" height="14" rx="3" fill="#E3EAF2" />
            <rect x="88" y="102" width="16" height="14" rx="3" fill="#E3EAF2" />
            <rect x="112" y="102" width="16" height="14" rx="3" fill="#C9D6E5" />
            <rect x="136" y="102" width="16" height="14" rx="3" fill="#E3EAF2" />
            <ellipse cx="120" cy="154" rx="52" ry="8" fill="#E3EAF2" />
        </svg>
    );
}

export default function Index(props) {
    const { events = [], from, to, venues = [], classes = [], categories = [], audiences = [], canManage = false } = props;
    const { t, locale } = useLocale();
    const f = useForm({ title: '', description: '', category: 'other', starts_at: '', ends_at: '', event_venue_id: '', audience: 'all', school_class_id: '', capacity: '', registration_required: false, registration_closes_at: '' });
    f.transform((d) => ({ ...d, event_venue_id: d.event_venue_id || null, school_class_id: d.school_class_id || null, capacity: d.capacity === '' ? null : Number(d.capacity), registration_closes_at: d.registration_closes_at || null }));

    return (
        <EventsLayout title={t('ev.title')} subtitle={t('ev.subtitle')} current="calendar" canManage={canManage} shell={props}>
            <form className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end" onSubmit={(e) => { e.preventDefault(); router.get(route('events.index'), { from, to }, { preserveState: true }); }}>
                <Field label={t('ev.from')} className="sm:w-auto">
                    <Input
                        type="date"
                        className="!w-auto min-w-[12rem]"
                        value={from}
                        onChange={(e) => router.get(route('events.index'), { from: e.target.value, to }, { preserveState: true, replace: true })}
                    />
                </Field>
                <Field label={t('ev.to')} className="sm:w-auto">
                    <Input
                        type="date"
                        className="!w-auto min-w-[12rem]"
                        value={to}
                        onChange={(e) => router.get(route('events.index'), { from, to: e.target.value }, { preserveState: true, replace: true })}
                    />
                </Field>
            </form>

            {events.length === 0 ? (
                <section className="overflow-hidden rounded-[1.6rem] border border-surface-muted bg-white shadow-soft">
                    <div className="flex flex-col items-center px-6 py-16 text-center sm:px-12 sm:py-24">
                        <EmptyIllustration />
                        <p className="mt-6 text-lg font-bold text-navy sm:text-xl">{t('ev.empty')}</p>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{t('ev.emptyHelp')}</p>
                    </div>
                </section>
            ) : (
                <DataTable
                    columns={[
                        { key: 'title', label: t('proc.col.title'), render: (r) => <Link href={route('events.show', r.id)} className="font-medium text-teal-700 hover:underline">{r.title}</Link> },
                        { key: 'category', label: t('inv.col.type'), render: (r) => enumLabel(t, 'ev.category', r.category) },
                        { key: 'starts_at', label: t('ev.starts'), render: (r) => fmtWhen(r.starts_at, locale) || '—' },
                        { key: 'venue', label: t('ev.venue') },
                        { key: 'status', label: t('inv.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'ev.status', r.status)} /> },
                    ]}
                    rows={events}
                    emptyMessage={t('ev.empty')}
                />
            )}

            {canManage ? (
                <Card title={t('ev.add')} className="mt-8 max-w-3xl">
                    <form onSubmit={(e) => { e.preventDefault(); f.post(route('events.store')); }} className="space-y-3">
                        <ErrorSummary errors={f.errors} />
                        <Field label={t('proc.col.title')}><Input value={f.data.title} onChange={(e) => f.setData('title', e.target.value)} required /></Field>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label={t('inv.col.type')}>
                                <Select value={f.data.category} onChange={(e) => f.setData('category', e.target.value)}>
                                    {categories.map((k) => <option key={k} value={k}>{t(`ev.category.${k}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('ev.audience')}>
                                <Select value={f.data.audience} onChange={(e) => f.setData('audience', e.target.value)}>
                                    {audiences.map((k) => <option key={k} value={k}>{t(`ev.audience.${k}`)}</option>)}
                                </Select>
                            </Field>
                            <Field label={t('ev.starts')}><Input type="datetime-local" value={f.data.starts_at} onChange={(e) => f.setData('starts_at', e.target.value)} required /></Field>
                            <Field label={t('ev.ends')}><Input type="datetime-local" value={f.data.ends_at} onChange={(e) => f.setData('ends_at', e.target.value)} required /></Field>
                            <Field label={t('ev.venue')}>
                                <Select value={f.data.event_venue_id} onChange={(e) => f.setData('event_venue_id', e.target.value)}>
                                    <option value="">{t('acad.common.none')}</option>
                                    {venues.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                                </Select>
                            </Field>
                            {f.data.audience === 'class' ? (
                                <Field label={t('ev.class')}>
                                    <Select value={f.data.school_class_id} onChange={(e) => f.setData('school_class_id', e.target.value)} required>
                                        <option value="">{t('acad.common.choose')}</option>
                                        {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </Select>
                                </Field>
                            ) : null}
                        </div>
                        <Field label={t('ev.desc')}><Textarea rows={2} value={f.data.description} onChange={(e) => f.setData('description', e.target.value)} /></Field>
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 text-teal focus:ring-teal"
                                checked={f.data.registration_required}
                                onChange={(e) => f.setData('registration_required', e.target.checked)}
                            />
                            {t('ev.regRequired')}
                        </label>
                        <button type="submit" className="btn-teal" disabled={f.processing}>{t('ev.save')}</button>
                    </form>
                </Card>
            ) : null}
        </EventsLayout>
    );
}
