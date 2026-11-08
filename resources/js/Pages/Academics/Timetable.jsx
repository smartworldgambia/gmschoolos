import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import {
    CalendarIcon,
    ClockIcon,
    LessonChip,
    PrinterIcon,
    TimetableHeader,
    UsersIcon,
    addDays,
    formatColDate,
    formatWeekRange,
    isoWeekday,
    mondayKey,
    startOfWeek,
    weekChoices,
} from '@/Components/SchoolOS/TimetableChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import { DAYS, messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function weekdayColumns(slots) {
    const extra = DAYS.filter((d) => d > 5 && slots.some((s) => s.day_of_week === d));

    return [1, 2, 3, 4, 5, ...extra];
}

function timeRows(slots, periods = []) {
    const rows = [];
    const seen = new Set();
    const push = (starts, ends, isBreak = false, id = null) => {
        const key = `${starts}|${ends}`;
        if (seen.has(key)) {
            return;
        }
        seen.add(key);
        rows.push({ key: id ? `p-${id}` : key, starts, ends, isBreak, label: `${starts} – ${ends}` });
    };

    periods.forEach((p) => push(p.starts_at, p.ends_at, Boolean(p.is_break), p.id));
    slots.forEach((s) => push(s.starts_at, s.ends_at, false));
    rows.sort((a, b) => String(a.starts).localeCompare(String(b.starts)));

    return rows;
}

function removeSlot(t, id) {
    if (confirm(t('academics.confirmRemove'))) {
        router.delete(route('timetable.destroy', id), { preserveScroll: true });
    }
}

function WeeklyBoard({ slots, periods, can, monday, locale, todayIso }) {
    const { t } = useLocale();
    const days = weekdayColumns(slots);
    const rows = timeRows(slots, periods);

    if (!rows.length) {
        return null;
    }

    return (
        <div className="tt-week tt-print-root">
            <table className="tt-week-table">
                <thead>
                    <tr>
                        <th scope="col">{t('timetable.time')}</th>
                        {days.map((d) => {
                            const date = addDays(monday, d - 1);
                            const today = d === todayIso;

                            return (
                                <th key={d} scope="col" className={today ? 'tt-today' : ''}>
                                    <span className="block">{t(`dayShort.${d}`)}</span>
                                    <span className="mt-0.5 block text-[11px] font-semibold normal-case tracking-normal text-slate-500">{formatColDate(date, locale)}</span>
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.key} className={row.isBreak ? 'tt-break' : ''}>
                            <th scope="row">
                                <span dir="ltr" className="inline-flex items-center gap-2 text-sm font-semibold text-navy">
                                    <ClockIcon className="h-4 w-4 text-slate-400" />
                                    {row.label}
                                </span>
                                {row.isBreak ? <span className="mt-1 block text-[11px] font-medium uppercase tracking-wide text-amber-700">{t('timetable.break')}</span> : null}
                            </th>
                            {days.map((d) => {
                                const cell = slots.filter((s) => s.day_of_week === d && s.starts_at === row.starts);
                                const today = d === todayIso;

                                return (
                                    <td key={d} className={today ? 'tt-today' : ''}>
                                        {cell.length ? (
                                            <div className="space-y-2">
                                                {cell.map((s) => (
                                                    <LessonChip key={s.id} slot={s} t={t} onRemove={can.manage ? () => removeSlot(t, s.id) : undefined} />
                                                ))}
                                            </div>
                                        ) : null}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ListBoard({ slots, can, monday, locale }) {
    const { t } = useLocale();
    const days = weekdayColumns(slots);

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {days.map((d) => {
                const list = slots.filter((s) => s.day_of_week === d).sort((a, b) => String(a.starts_at).localeCompare(String(b.starts_at)));
                const date = addDays(monday, d - 1);

                return (
                    <section key={d} className="rounded-2xl border border-surface-muted bg-white p-4 shadow-soft">
                        <h3 className="text-sm font-bold uppercase tracking-wide text-navy">{t(`day.${d}`)}</h3>
                        <p className="mb-3 text-xs text-slate-500">{formatColDate(date, locale)}</p>
                        {list.length === 0 ? <p className="text-sm text-slate-400">—</p> : null}
                        <ul className="space-y-2">
                            {list.map((s) => (
                                <li key={s.id}>
                                    <p dir="ltr" className="mb-1 text-xs font-semibold text-slate-500">
                                        {s.starts_at} – {s.ends_at}
                                    </p>
                                    <LessonChip slot={s} t={t} onRemove={can.manage ? () => removeSlot(t, s.id) : undefined} />
                                    {s.teacher ? <p className="mt-1 text-[11px] text-slate-500">{s.teacher}</p> : null}
                                </li>
                            ))}
                        </ul>
                    </section>
                );
            })}
        </div>
    );
}

function AddSlot({ classes, subjects, teachers, rooms, periods, filters }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        school_class_id: filters.class_id || '',
        subject_id: '',
        teacher_id: '',
        room_id: '',
        period_id: '',
        day_of_week: 1,
        starts_at: '08:00',
        ends_at: '08:45',
    });
    const usePeriod = Boolean(data.period_id);
    const lessonPeriods = periods.filter((p) => !p.is_break);

    return (
        <Card title={t('timetable.addLesson')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('timetable.store'), { preserveScroll: true, onSuccess: () => reset('subject_id', 'teacher_id', 'room_id') });
                }}
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div className="sm:col-span-2 lg:col-span-4">
                    <ErrorSummary errors={errors} />
                </div>
                <Field label={t('students.col.class')}>
                    <Select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} required>
                        <option value="">{t('acad.common.choose')}</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('results.col.subject')}>
                    <Select value={data.subject_id} onChange={(e) => setData('subject_id', e.target.value)} required>
                        <option value="">{t('acad.common.choose')}</option>
                        {subjects.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('allocation.teacher')}>
                    <Select value={data.teacher_id} onChange={(e) => setData('teacher_id', e.target.value)}>
                        <option value="">{t('acad.common.none')}</option>
                        {teachers.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('timetable.room')}>
                    <Select value={data.room_id} onChange={(e) => setData('room_id', e.target.value)}>
                        <option value="">{t('acad.common.none')}</option>
                        {rooms.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('timetable.day')}>
                    <Select value={data.day_of_week} onChange={(e) => setData('day_of_week', e.target.value)}>
                        {DAYS.map((d) => (
                            <option key={d} value={d}>
                                {t(`day.${d}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('timetable.period')}>
                    <Select value={data.period_id} onChange={(e) => setData('period_id', e.target.value)}>
                        <option value="">{t('timetable.customTimes')}</option>
                        {lessonPeriods.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name} ({p.starts_at}–{p.ends_at})
                            </option>
                        ))}
                    </Select>
                </Field>
                {!usePeriod ? (
                    <>
                        <Field label={t('calendar.starts')}>
                            <Input type="time" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                        </Field>
                        <Field label={t('calendar.ends')}>
                            <Input type="time" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} required />
                        </Field>
                    </>
                ) : null}
                <div className="flex items-end">
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('timetable.add')}
                    </button>
                </div>
            </form>
        </Card>
    );
}

function Periods({ periods }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', starts_at: '', ends_at: '', is_break: false });

    return (
        <Card title={t('timetable.periods')}>
            <ul className="divide-y divide-slate-100 text-sm">
                {periods.length === 0 ? <li className="py-2 text-slate-500">{t('timetable.noPeriods')}</li> : null}
                {periods.map((p) => (
                    <li key={p.id} className="flex items-center justify-between py-1.5">
                        <span>
                            <span className="font-medium text-navy">{p.name}</span>
                            <span className="ms-2 text-slate-500">
                                {p.starts_at}–{p.ends_at}
                            </span>
                            {p.is_break ? <span className="ms-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{t('timetable.break')}</span> : null}
                        </span>
                        <button type="button" className="text-red-600 hover:underline" onClick={() => confirm(t('academics.confirmRemove')) && router.delete(route('timetable.periods.destroy', p.id), { preserveScroll: true })}>
                            {t('acad.common.remove')}
                        </button>
                    </li>
                ))}
            </ul>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('timetable.periods.store'), { preserveScroll: true, onSuccess: () => reset() });
                }}
                className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
            >
                <ErrorSummary errors={errors} />
                <Field label={t('timetable.periodName')}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required className="!w-36" />
                </Field>
                <Field label={t('calendar.starts')}>
                    <Input type="time" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                </Field>
                <Field label={t('calendar.ends')}>
                    <Input type="time" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} required />
                </Field>
                <label className="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" className="rounded border-gray-300" checked={data.is_break} onChange={(e) => setData('is_break', e.target.checked)} />
                    {t('timetable.isBreak')}
                </label>
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('acad.common.add')}
                </button>
            </form>
        </Card>
    );
}

function Availability({ teachers, unavailability }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ teacher_id: '', day_of_week: 1, starts_at: '', ends_at: '', reason: '' });

    return (
        <Card title={t('timetable.availability')}>
            <p className="mb-3 text-xs text-slate-500">{t('timetable.availabilityHint')}</p>
            <ul className="divide-y divide-slate-100 text-sm">
                {unavailability.length === 0 ? <li className="py-2 text-slate-500">{t('timetable.noUnavailability')}</li> : null}
                {unavailability.map((u) => (
                    <li key={u.id} className="flex items-center justify-between py-1.5">
                        <span>
                            <span className="font-medium text-navy">{u.teacher}</span>
                            <span className="ms-2 text-slate-500">
                                {t(`day.${u.day_of_week}`)} {u.starts_at}–{u.ends_at}
                            </span>
                            {u.reason ? <span className="ms-2 text-xs text-slate-400">{u.reason}</span> : null}
                        </span>
                        <button type="button" className="text-red-600 hover:underline" onClick={() => router.delete(route('timetable.unavailability.destroy', u.id), { preserveScroll: true })}>
                            {t('acad.common.remove')}
                        </button>
                    </li>
                ))}
            </ul>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('timetable.unavailability.store'), { preserveScroll: true, onSuccess: () => reset('starts_at', 'ends_at', 'reason') });
                }}
                className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4"
            >
                <ErrorSummary errors={errors} />
                <Field label={t('allocation.teacher')}>
                    <Select value={data.teacher_id} onChange={(e) => setData('teacher_id', e.target.value)} required>
                        <option value="">{t('acad.common.choose')}</option>
                        {teachers.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('timetable.day')}>
                    <Select value={data.day_of_week} onChange={(e) => setData('day_of_week', e.target.value)}>
                        {DAYS.map((d) => (
                            <option key={d} value={d}>
                                {t(`day.${d}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('calendar.starts')}>
                    <Input type="time" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} required />
                </Field>
                <Field label={t('calendar.ends')}>
                    <Input type="time" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} required />
                </Field>
                <Field label={t('acad.common.reason')}>
                    <Input value={data.reason} onChange={(e) => setData('reason', e.target.value)} />
                </Field>
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('acad.common.add')}
                </button>
            </form>
        </Card>
    );
}

function Rooms() {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', capacity: '', building: '' });

    return (
        <Card title={t('timetable.rooms')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('rooms.store'), { preserveScroll: true, onSuccess: () => reset() });
                }}
                className="flex flex-wrap items-end gap-3"
            >
                <ErrorSummary errors={errors} />
                <Field label={t('timetable.roomName')}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                </Field>
                <Field label={t('acad.common.code')}>
                    <Input value={data.code} onChange={(e) => setData('code', e.target.value)} className="!w-28" />
                </Field>
                <Field label={t('academics.class.capacity')}>
                    <Input type="number" min="1" value={data.capacity} onChange={(e) => setData('capacity', e.target.value)} className="!w-28" />
                </Field>
                <button type="submit" className="btn-teal" disabled={processing}>
                    {t('acad.common.add')}
                </button>
            </form>
        </Card>
    );
}

export default function Timetable({
    slots = [],
    classes = [],
    subjects = [],
    teachers = [],
    rooms = [],
    periods = [],
    unavailability = [],
    issues = [],
    draftCount = 0,
    filters = {},
    can = {},
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t, locale } = useLocale();
    const [monday, setMonday] = useState(() => startOfWeek(new Date()));
    const [view, setView] = useState('weekly');
    const weeks = useMemo(() => weekChoices(new Date()), []);
    const todayIso = isoWeekday(new Date());
    const currentMonday = startOfWeek(new Date());
    const showingThisWeek = mondayKey(monday) === mondayKey(currentMonday);
    const classLabel = classes.find((c) => String(c.id) === String(filters.class_id))?.name || slots.find((s) => s.class)?.class || '';

    const slotLabel = (id) => {
        const s = slots.find((x) => x.id === id);

        return s ? `${s.class ?? ''} ${t(`day.${s.day_of_week}`)} ${s.starts_at}` : `#${id}`;
    };

    function filter(next) {
        router.get(route('timetable.index'), { ...filters, ...next }, { preserveState: true, preserveScroll: true });
    }

    const headerActions = can.manage ? (
        <>
            <button type="button" className="btn-outline" onClick={() => router.post(route('timetable.check'), {}, { preserveScroll: true })}>
                {t('timetable.check')}
            </button>
            <button type="button" className="btn-teal" onClick={() => confirm(t('timetable.confirmPublish')) && router.post(route('timetable.publish'), {}, { preserveScroll: true })}>
                {t('timetable.publish')}
                {draftCount > 0 ? ` (${draftCount})` : ''}
            </button>
        </>
    ) : null;

    return (
        <AppShell title={t('timetable.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('timetable.title')} />
            <TimetableHeader title={t('timetable.title')} subtitle={can.manage ? t('timetable.subtitleManage') : t('timetable.subtitleHero')} actions={headerActions} />

            <div className="tt-no-print mb-5 flex flex-col gap-3 rounded-2xl border border-surface-muted bg-white p-3 shadow-soft sm:p-4 lg:flex-row lg:flex-wrap lg:items-end">
                <label className="flex min-w-[14rem] flex-1 flex-col gap-1.5">
                    <span className="ps-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('timetable.academicWeek')}</span>
                    <span className="flex items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-2.5 focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                        <CalendarIcon className="h-4 w-4 shrink-0 text-slate-400" />
                        <select
                            className="w-full min-w-0 border-0 bg-transparent p-0 text-sm font-medium text-navy focus:border-transparent focus:outline-none focus:ring-0"
                            value={mondayKey(monday)}
                            onChange={(e) => {
                                const next = weeks.find((w) => mondayKey(w) === e.target.value);
                                if (next) {
                                    setMonday(next);
                                }
                            }}
                        >
                            {weeks.map((w) => (
                                <option key={mondayKey(w)} value={mondayKey(w)}>
                                    {formatWeekRange(w, locale)}
                                </option>
                            ))}
                        </select>
                    </span>
                </label>

                {classes.length > 0 ? (
                    <label className="flex min-w-[12rem] flex-1 flex-col gap-1.5">
                        <span className="ps-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('timetable.classGrade')}</span>
                        <span className="flex items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-2.5 focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                            <UsersIcon className="h-4 w-4 shrink-0 text-slate-400" />
                            <select
                                className="w-full min-w-0 border-0 bg-transparent p-0 text-sm font-medium text-navy focus:border-transparent focus:outline-none focus:ring-0"
                                value={filters.class_id ?? ''}
                                onChange={(e) => filter({ class_id: e.target.value || undefined })}
                            >
                                {can.manage ? <option value="">{t('students.filter.allClasses')}</option> : null}
                                {classes.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </span>
                    </label>
                ) : classLabel ? (
                    <div className="flex min-w-[12rem] flex-1 flex-col gap-1.5">
                        <span className="ps-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('timetable.classGrade')}</span>
                        <div className="flex items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-2.5">
                            <UsersIcon className="h-4 w-4 shrink-0 text-slate-400" />
                            <span className="text-sm font-medium text-navy">{classLabel}</span>
                        </div>
                    </div>
                ) : null}

                {can.manage ? (
                    <label className="flex min-w-[12rem] flex-col gap-1.5">
                        <span className="ps-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('allocation.teacher')}</span>
                        <span className="flex items-center gap-2 rounded-xl border border-surface-muted bg-white px-3 py-2.5 focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                            <select
                                className="w-full min-w-[10rem] border-0 bg-transparent p-0 text-sm font-medium text-navy focus:border-transparent focus:outline-none focus:ring-0"
                                value={filters.teacher_id ?? ''}
                                onChange={(e) => filter({ teacher_id: e.target.value || undefined })}
                            >
                                <option value="">{t('timetable.allTeachers')}</option>
                                {teachers.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name}
                                    </option>
                                ))}
                            </select>
                        </span>
                    </label>
                ) : null}

                <div className="flex flex-wrap gap-2 lg:ms-auto">
                    <button type="button" className="btn-outline gap-2" onClick={() => setMonday(currentMonday)}>
                        <CalendarIcon className="h-4 w-4" />
                        {t('timetable.today')}
                    </button>
                    <button type="button" className="btn-teal gap-2" onClick={() => window.print()}>
                        <PrinterIcon />
                        {t('timetable.print')}
                    </button>
                </div>
            </div>

            <nav className="tt-no-print lms-dash-tabs" aria-label={t('timetable.views')}>
                <button type="button" className={`lms-dash-tab inline-flex items-center gap-2 ${view === 'weekly' ? 'lms-dash-tab-active' : 'lms-dash-tab-idle'}`} aria-current={view === 'weekly' ? 'page' : undefined} onClick={() => setView('weekly')}>
                    <CalendarIcon className="h-4 w-4" />
                    {t('timetable.weeklyView')}
                </button>
                <button type="button" className={`lms-dash-tab inline-flex items-center gap-2 ${view === 'list' ? 'lms-dash-tab-active' : 'lms-dash-tab-idle'}`} aria-current={view === 'list' ? 'page' : undefined} onClick={() => setView('list')}>
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.8} d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                    </svg>
                    {t('timetable.listView')}
                </button>
            </nav>

            {issues.length > 0 ? (
                <div className="tt-no-print mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
                    <p className="mb-2 font-semibold">{t('timetable.conflictsTitle', { n: issues.length })}</p>
                    <ul className="list-disc space-y-1 ps-5">
                        {issues.map((i, k) => (
                            <li key={k}>
                                {messageText(t, i.code)}
                                {i.slots?.length ? <span className="text-red-700"> — {i.slots.map(slotLabel).join(' / ')}</span> : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {slots.length === 0 ? (
                <section className="mb-6 rounded-[1.6rem] border border-surface-muted bg-white px-6 py-16 text-center shadow-soft">
                    <p className="text-lg font-bold text-navy">{t('timetable.empty')}</p>
                    <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">{t('timetable.emptyHelp')}</p>
                </section>
            ) : view === 'list' ? (
                <div className="mb-6">
                    <ListBoard slots={slots} can={can} monday={monday} locale={locale} />
                </div>
            ) : (
                <div className="mb-6">
                    <WeeklyBoard slots={slots} periods={periods} can={can} monday={monday} locale={locale} todayIso={showingThisWeek ? todayIso : 0} />
                </div>
            )}

            {can.manage ? (
                <div className="tt-no-print space-y-6">
                    <AddSlot classes={classes} subjects={subjects} teachers={teachers} rooms={rooms} periods={periods} filters={filters} />
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Periods periods={periods} />
                        <Availability teachers={teachers} unavailability={unavailability} />
                    </div>
                    <Rooms />
                </div>
            ) : null}
        </AppShell>
    );
}
