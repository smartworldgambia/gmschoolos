import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatCard from '@/Components/SchoolOS/StatCard';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import AnnouncementList from '@/Components/Comm/AnnouncementList';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ children = [], recentPayments = [], guardian, unreadMessages = 0, announcements = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('dash.parent.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('dash.parent.title')} />
            <PageHeader
                title={t('dash.parent.welcome', { name: guardian?.full_name ?? user?.name ?? '' })}
                subtitle={t('dash.parent.subtitle')}
                actions={
                    <Link href={route('messages.index')} className="btn-outline">
                        {t('dash.quick.messages')}
                        {unreadMessages > 0 ? ` (${unreadMessages})` : ''}
                    </Link>
                }
            />

            <div className="space-y-6">
                {children.length === 0 ? <p className="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">{t('dash.parent.noChildren')}</p> : null}

                {children.map((child) => (
                    <section key={child.id} className="rounded-xl bg-white p-5 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-navy">{child.full_name}</h2>
                                <p className="text-sm text-slate-500">
                                    {child.student_number} · {child.class ?? t('dash.parent.noClass')}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Link href={route('messages.create', { student: child.id })} className="btn-outline !py-2 text-xs">
                                    {t('dash.parent.contactTeacher')}
                                </Link>
                                <Link href={child.profile_url} className="btn-outline !py-2 text-xs">
                                    {t('dash.parent.viewProfile')}
                                </Link>
                            </div>
                        </div>

                        <div className="mb-4 grid gap-3 sm:grid-cols-3">
                            <StatCard title={t('dash.stat.attendance')} value={child.attendance_rate != null ? `${child.attendance_rate}%` : '—'} tone="teal" />
                            <StatCard title={t('dash.parent.outstanding')} value={child.outstanding_label} tone="orange" />
                            <StatCard title={t('dash.parent.publishedResults')} value={child.grades?.length ?? 0} tone="blue" />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-slate-500">{t('dash.recentResults')}</h3>
                                <DataTable
                                    columns={[
                                        { key: 'subject', label: t('dash.col.subject') },
                                        { key: 'total', label: t('dash.col.score') },
                                        { key: 'letter_grade', label: t('dash.col.grade') },
                                    ]}
                                    rows={(child.grades ?? []).map((g, i) => ({ ...g, id: i }))}
                                    emptyMessage={t('dash.parent.noResults')}
                                />
                            </div>
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-slate-500">{t('dash.parent.invoices')}</h3>
                                <DataTable
                                    columns={[
                                        { key: 'invoice_number', label: t('dash.parent.invoice') },
                                        { key: 'balance_label', label: t('dash.parent.balance') },
                                        { key: 'status', label: t('acct.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'dash.invoiceStatus', r.status)} /> },
                                    ]}
                                    rows={child.invoices}
                                    emptyMessage={t('dash.parent.noInvoices')}
                                />
                            </div>
                        </div>
                    </section>
                ))}

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl bg-white p-5 shadow-sm">
                        <h2 className="mb-3 font-semibold text-navy">{t('dash.parent.recentPayments')}</h2>
                        <DataTable
                            columns={[
                                { key: 'student_name', label: t('dash.col.student') },
                                { key: 'amount_label', label: t('dash.col.amount') },
                                { key: 'method', label: t('dash.col.method') },
                                { key: 'paid_at', label: t('dash.col.date') },
                            ]}
                            rows={recentPayments.map((p, i) => ({ ...p, id: i }))}
                            emptyMessage={t('dash.parent.noPayments')}
                        />
                    </section>
                    <section>
                        <h2 className="mb-3 font-semibold text-navy">{t('dash.announcements')}</h2>
                        <AnnouncementList items={announcements} />
                    </section>
                </div>
            </div>
        </AppShell>
    );
}
