import { Pager, Select } from '@/Components/SchoolOS/Form';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ students, filters = {}, classes = [], can = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');
    const [classId, setClassId] = useState(filters.class_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const rows = students?.data ?? [];

    function search(e) {
        e.preventDefault();
        router.get(route('students.index'), { q, class_id: classId || undefined, status: status || undefined }, { preserveState: true });
    }

    const columns = [
        { key: 'student_number', label: t('students.col.number'), render: (r) => r.student_number },
        {
            key: 'full_name',
            label: t('students.col.name'),
            render: (r) => (
                <Link href={route('students.show', r.id)} className="font-medium text-teal hover:underline">
                    {r.full_name}
                </Link>
            ),
        },
        { key: 'gender', label: t('students.col.gender'), render: (r) => enumLabel(t, 'gender', r.gender) || '—' },
        { key: 'class', label: t('students.col.class'), render: (r) => r.class ?? '—' },
        {
            key: 'status',
            label: t('students.col.status'),
            render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'studentStatus', r.status)} />,
        },
    ];

    return (
        <AppShell title={t('students.index.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('students.index.title')} />
            <PageHeader
                title={t('students.index.title')}
                subtitle={t('students.index.subtitle')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {can.create ? (
                            <Link href={route('students.create')} className="btn-teal">
                                {t('students.index.add')}
                            </Link>
                        ) : null}
                        {can.import ? (
                            <Link href={route('students.import.index')} className="btn-outline">
                                {t('students.index.import')}
                            </Link>
                        ) : null}
                        {can.create ? (
                            <Link href={route('admissions.index')} className="btn-outline">
                                {t('students.index.admissions')}
                            </Link>
                        ) : null}
                    </div>
                }
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap items-center gap-2">
                <TextInput
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t('students.index.searchPlaceholder')}
                    aria-label={t('students.index.searchPlaceholder')}
                    className="w-full max-w-xs"
                />
                {classes.length > 0 ? (
                    <Select value={classId} onChange={(e) => setClassId(e.target.value)} aria-label={t('students.col.class')} className="!w-auto">
                        <option value="">{t('students.filter.allClasses')}</option>
                        {classes.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                ) : null}
                <Select value={status} onChange={(e) => setStatus(e.target.value)} aria-label={t('students.col.status')} className="!w-auto">
                    <option value="">{t('students.filter.allStatuses')}</option>
                    {['active', 'withdrawn', 'transferred', 'graduated', 'suspended'].map((s) => (
                        <option key={s} value={s}>
                            {enumLabel(t, 'studentStatus', s)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="btn-teal">
                    {t('acad.common.search')}
                </button>
            </form>

            <div className="rounded-xl bg-white p-4 shadow-sm">
                <DataTable columns={columns} rows={rows} emptyMessage={t('students.index.empty')} />
                <Pager links={students?.links} />
            </div>
        </AppShell>
    );
}
