import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import AnnouncementList from '@/Components/Comm/AnnouncementList';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats = {}, timetable = [], classes = [], subjects = [], pendingItems = [], announcements = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    const timetableColumns = [
        { key: 'time', label: t('dash.col.time'), render: (r) => `${r.starts_at} – ${r.ends_at}` },
        { key: 'class', label: t('dash.col.class') },
        { key: 'subject', label: t('dash.col.subject') },
        { key: 'room', label: t('dash.col.room') },
    ];

    return (
        <AppShell title={t('dash.teacher.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('dash.teacher.title')} />
            <PageHeader title={t('dash.teacher.title')} subtitle={t('dash.teacher.subtitle')} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard title={t('dash.stat.classes')} value={stats.classes} tone="teal" />
                <StatCard title={t('dash.stat.students')} value={stats.students} tone="blue" />
                <StatCard title={t('dash.stat.courses')} value={stats.courses} tone="navy" />
                <StatCard title={t('dash.stat.pendingMarking')} value={stats.pending_marking} tone="orange" />
                <StatCard title={t('dash.stat.unreadMessages')} value={stats.unread_messages} tone="purple" />
            </div>

            <div className="mt-6 flex flex-wrap gap-2">
                <Link href={route('attendance.index')} className="btn-outline">
                    {t('dash.quick.attendance')}
                </Link>
                <Link href={route('lms.courses.index')} className="btn-outline">
                    {t('dash.quick.courses')}
                </Link>
                <Link href={route('messages.index')} className="btn-outline">
                    {t('dash.quick.messages')}
                </Link>
                <Link href={route('announcements.create')} className="btn-outline">
                    {t('dash.quick.announce')}
                </Link>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dash.todaySchedule')}</h2>
                    <DataTable columns={timetableColumns} rows={timetable.map((r, i) => ({ ...r, id: i }))} emptyMessage={t('dash.noClassesToday')} />
                </section>

                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dash.myClasses')}</h2>
                    <div className="rounded-xl bg-white shadow-sm">
                        {classes.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-slate-500">{t('dash.teacher.noClasses')}</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {classes.map((c) => (
                                    <li key={c.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                        <p className="truncate text-sm font-medium text-navy">{c.name}</p>
                                        <span className="shrink-0 text-xs text-slate-500">{t('dash.studentsCount', { n: c.students_count })}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dash.mySubjects')}</h2>
                    <div className="rounded-xl bg-white shadow-sm">
                        {subjects.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-slate-500">{t('dash.teacher.noSubjects')}</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {subjects.map((s, i) => (
                                    <li key={i} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                        <span className="font-medium text-navy">{s.subject}</span>
                                        <span className="text-slate-500">{s.class}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section className="space-y-6">
                    <div>
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dash.upcomingAssignments')}</h2>
                        <div className="rounded-xl bg-white shadow-sm">
                            {pendingItems.length === 0 ? (
                                <p className="px-4 py-8 text-center text-sm text-slate-500">{t('dash.nothingPending')}</p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {pendingItems.map((item, index) => (
                                        <li key={index} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                            <span className="truncate font-medium text-navy">{item.title}</span>
                                            <span className="shrink-0 text-xs text-slate-500">{item.due_at}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                    <div>
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dash.announcements')}</h2>
                        <AnnouncementList items={announcements} />
                    </div>
                </section>
            </div>
        </AppShell>
    );
}
