import { Input, Pager, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['draft', 'submitted', 'approved', 'issued', 'reprinted', 'revoked'];

export default function Index({ documents, types = [], type = '', status = '', q = '', can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [search, setSearch] = useState(q);

    function go(params) {
        router.get(route('records.index'), { type, status, q: search, ...params }, { preserveState: true, replace: true });
    }

    return (
        <AppShell title={t('rec.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('rec.title')} />
            <PageHeader
                title={t('rec.title')}
                subtitle={t('rec.subtitle')}
                actions={
                    <>
                        {can.draft ? (
                            <Link href={route('records.create')} className="btn-teal">
                                {t('rec.new')}
                            </Link>
                        ) : null}
                        <Link href={route('records.templates.index')} className="btn-outline">
                            {t('rec.templates')}
                        </Link>
                        {can.issue ? (
                            <Link href={route('records.settings.edit')} className="btn-outline">
                                {t('rec.settings')}
                            </Link>
                        ) : null}
                    </>
                }
            />

            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('rec.notOfficial')}</p>

            <form
                className="mb-4 flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    go({});
                }}
            >
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('rec.searchPlaceholder')} className="max-w-xs" />
                <Select value={type} onChange={(e) => go({ type: e.target.value })} className="max-w-[14rem]">
                    <option value="">{t('rec.allTypes')}</option>
                    {types.map((x) => (
                        <option key={x} value={x}>
                            {enumLabel(t, 'rec.type', x)}
                        </option>
                    ))}
                </Select>
                <Select value={status} onChange={(e) => go({ status: e.target.value })} className="max-w-[12rem]">
                    <option value="">{t('acad.common.all')}</option>
                    {STATUSES.map((x) => (
                        <option key={x} value={x}>
                            {enumLabel(t, 'rec.status', x)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="btn-outline">
                    {t('acad.common.search')}
                </button>
            </form>

            <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3 text-start">{t('rec.col.reference')}</th>
                            <th className="px-4 py-3 text-start">{t('rec.col.type')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.student')}</th>
                            <th className="px-4 py-3 text-start">{t('rec.col.issueDate')}</th>
                            <th className="px-4 py-3 text-start">{t('ext.col.status')}</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {documents.data.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                    {t('rec.empty')}
                                </td>
                            </tr>
                        ) : null}
                        {documents.data.map((d) => (
                            <tr key={d.id}>
                                <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                    {d.reference}
                                </td>
                                <td className="px-4 py-3">{enumLabel(t, 'rec.type', d.type)}</td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-navy">{d.student ?? '—'}</p>
                                    <p className="text-xs text-slate-500">{d.student_number}</p>
                                </td>
                                <td className="px-4 py-3">{d.issue_date ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={d.status} label={enumLabel(t, 'rec.status', d.status)} />
                                </td>
                                <td className="px-4 py-3 text-end">
                                    <Link href={route('records.show', d.id)} className="btn-outline">
                                        {t('ext.open')}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager links={documents.links} />
        </AppShell>
    );
}
