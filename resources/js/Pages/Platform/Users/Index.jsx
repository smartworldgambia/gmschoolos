import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ users, roles = [], storeUrl, nav = [], user, school }) {
    const { t } = useLocale();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles[0] || 'platform_support',
    });

    const columns = [
        { key: 'name', label: t('platformStaff.col.name') },
        { key: 'email', label: t('platformStaff.col.email') },
        { key: 'roles', label: t('platformStaff.col.role'), render: (r) => (r.roles || []).join(', ') },
        { key: 'created_at', label: t('platformStaff.col.created') },
    ];

    return (
        <AppShell title={t('platformStaff.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformStaff.title')} />
            <PageHeader title={t('platformStaff.title')} subtitle={t('platformStaff.subtitle')} />

            <form
                className="mb-8 grid gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(storeUrl, { onSuccess: () => form.reset('name', 'email', 'password', 'password_confirmation') });
                }}
            >
                <h2 className="sm:col-span-2 text-base font-semibold text-navy">{t('platformStaff.add')}</h2>
                <input className="rounded-lg border px-3 py-2 text-sm" placeholder={t('platformStaff.col.name')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input type="email" className="rounded-lg border px-3 py-2 text-sm" placeholder={t('platformStaff.col.email')} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <input type="password" className="rounded-lg border px-3 py-2 text-sm" placeholder={t('platformStaff.password')} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                <input
                    type="password"
                    className="rounded-lg border px-3 py-2 text-sm"
                    placeholder={t('platformStaff.passwordConfirm')}
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                />
                <select className="rounded-lg border px-3 py-2 text-sm" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                    {roles.map((r) => (
                        <option key={r} value={r}>
                            {r}
                        </option>
                    ))}
                </select>
                <button type="submit" className="btn-navy text-sm" disabled={form.processing}>
                    {t('platformStaff.create')}
                </button>
            </form>

            <DataTable columns={columns} rows={users.data || []} emptyMessage={t('platformStaff.empty')} />
        </AppShell>
    );
}
