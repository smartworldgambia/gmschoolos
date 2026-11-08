import DataTable from '@/Components/SchoolOS/DataTable';
import { Pager, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['submitted', 'under_review', 'accepted', 'waitlisted', 'rejected', 'enrolled', 'withdrawn'];

export default function Index({ applications, counts = {}, filters = {}, can = {}, publicApplyUrl, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');
    const rows = applications?.data ?? [];

    function apply(next = {}) {
        router.get(route('admissions.index'), { q, status: filters.status || undefined, source: filters.source || undefined, ...next }, { preserveState: true });
    }

    const columns = [
        { key: 'application_number', label: t('admissions.col.number') },
        {
            key: 'full_name',
            label: t('students.col.name'),
            render: (r) => (
                <Link href={r.url} className="font-medium text-teal hover:underline">
                    {r.full_name}
                </Link>
            ),
        },
        { key: 'preferred_class', label: t('admissions.form.preferredClass'), render: (r) => r.preferred_class ?? '—' },
        { key: 'guardian_name', label: t('admissions.col.guardian'), render: (r) => r.guardian_name ?? '—' },
        { key: 'source', label: t('admissions.col.source'), render: (r) => enumLabel(t, 'admSource', r.source) },
        {
            key: 'status',
            label: t('students.col.status'),
            render: (r) => (
                <span className="inline-flex items-center gap-2">
                    <StatusBadge status={r.status} label={enumLabel(t, 'admStatus', r.status)} />
                    {r.status === 'waitlisted' && r.waitlist_position ? <span className="text-xs text-slate-500">#{r.waitlist_position}</span> : null}
                </span>
            ),
        },
        { key: 'created_at', label: t('acad.common.date') },
    ];

    return (
        <AppShell title={t('admissions.index.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('admissions.index.title')} />
            <PageHeader
                title={t('admissions.index.title')}
                subtitle={t('admissions.index.subtitle')}
                actions={
                    can.manage ? (
                        <Link href={route('admissions.create')} className="btn-teal">
                            {t('admissions.index.new')}
                        </Link>
                    ) : null
                }
            />

            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                {STATUSES.map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => apply({ status: filters.status === s ? undefined : s })}
                        className={`rounded-xl bg-white p-3 text-start shadow-sm ring-1 ${filters.status === s ? 'ring-teal' : 'ring-transparent hover:ring-slate-200'}`}
                    >
                        <span className="block text-xs text-slate-500">{enumLabel(t, 'admStatus', s)}</span>
                        <span className="block text-xl font-bold text-navy">{counts[s] ?? 0}</span>
                    </button>
                ))}
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    apply();
                }}
                className="mb-4 flex flex-wrap items-center gap-2"
            >
                <TextInput value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('admissions.index.search')} aria-label={t('admissions.index.search')} className="w-full max-w-xs" />
                <Select value={filters.source ?? ''} onChange={(e) => apply({ source: e.target.value || undefined })} aria-label={t('admissions.col.source')} className="!w-auto">
                    <option value="">{t('admissions.index.allSources')}</option>
                    <option value="online">{enumLabel(t, 'admSource', 'online')}</option>
                    <option value="walk_in">{enumLabel(t, 'admSource', 'walk_in')}</option>
                </Select>
                <button type="submit" className="btn-teal">
                    {t('acad.common.search')}
                </button>
            </form>

            {publicApplyUrl ? (
                <p className="mb-4 text-sm text-slate-500">
                    {t('admissions.index.publicLink')}{' '}
                    <a href={publicApplyUrl} className="font-mono text-teal underline" target="_blank" rel="noreferrer">
                        {publicApplyUrl}
                    </a>
                </p>
            ) : null}

            <div className="rounded-xl bg-white p-4 shadow-sm">
                <DataTable columns={columns} rows={rows} emptyMessage={t('admissions.index.empty')} />
                <Pager links={applications?.links} />
            </div>
        </AppShell>
    );
}
