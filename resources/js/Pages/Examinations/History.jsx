import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

export default function History({ examination, changes = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const show = (total, letter) => (total === null || total === undefined ? '—' : `${total}${letter ? ` (${letter})` : ''}`);

    return (
        <AppShell title={t('results.history')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('results.history')} />
            <PageHeader
                title={t('results.history')}
                subtitle={examination.name}
                actions={
                    <Link href={examination.url} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />
            <p className="mb-4 text-sm text-slate-500">{t('results.history.hint')}</p>
            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('acad.common.date')}</th>
                            <th className="px-4 py-3 text-start">{t('students.col.name')}</th>
                            <th className="px-4 py-3 text-start">{t('results.subject')}</th>
                            <th className="px-4 py-3 text-start">{t('results.history.event')}</th>
                            <th className="px-4 py-3 text-start">{t('results.history.change')}</th>
                            <th className="px-4 py-3 text-start">{t('results.history.by')}</th>
                            <th className="px-4 py-3 text-start">{t('acad.common.reason')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {changes.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">
                                    {t('results.history.empty')}
                                </td>
                            </tr>
                        ) : null}
                        {changes.map((c) => (
                            <tr key={c.id} className="border-t border-slate-100">
                                <td className="px-4 py-2 whitespace-nowrap">{c.at}</td>
                                <td className="px-4 py-2">{c.student}</td>
                                <td className="px-4 py-2">{c.subject}</td>
                                <td className="px-4 py-2">{enumLabel(t, 'gradeEvent', c.event)}</td>
                                <td className="px-4 py-2 whitespace-nowrap">
                                    {show(c.old, c.old_letter)} → {show(c.new, c.new_letter)}
                                </td>
                                <td className="px-4 py-2">{c.by}</td>
                                <td className="px-4 py-2">{c.reason}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
