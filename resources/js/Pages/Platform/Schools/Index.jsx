import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({
    schools,
    filters = {},
    types = [],
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;
    const form = useForm({
        name: '',
        slug: '',
        email: '',
        billing_email: '',
        region: '',
        school_type: types[0] ?? 'primary',
        host: '',
    });

    const columns = [
        {
            key: 'name',
            label: t('plat.col.school'),
            render: (row) => (
                <Link href={route('platform.schools.show', row.id)} className="font-medium text-teal hover:underline">
                    {row.name}
                </Link>
            ),
        },
        { key: 'slug', label: t('plat.schools.col.slug') },
        { key: 'school_type', label: t('platformSchools.col.type'), render: (row) => (row.school_type ? l.schoolType(row.school_type) : null) },
        {
            key: 'subscription_status',
            label: t('platformSchools.col.subscription'),
            render: (row) =>
                row.subscription_status ? (
                    <StatusBadge status={row.subscription_status} label={l.status(row.subscription_status)} />
                ) : null,
        },
        {
            key: 'public_url',
            label: t('plat.schools.col.publicUrl'),
            render: (row) =>
                row.public_url ? (
                    <a href={row.public_url} className="font-medium text-teal hover:underline" target="_blank" rel="noreferrer">
                        {String(row.public_url).replace(/^https?:\/\//, '')}
                    </a>
                ) : (
                    row.domain ?? '—'
                ),
        },
        {
            key: 'status',
            label: t('plat.col.status'),
            render: (row) => <StatusBadge status={row.status} />,
        },
        { key: 'created_at', label: t('plat.dash.col.created') },
    ];

    return (
        <AppShell title={t('plat.schools.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.schools.headTitle')} />

            <PageHeader title={t('plat.schools.title')} subtitle={t('plat.schools.subtitle')} />

            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="search"
                    defaultValue={filters.q ?? ''}
                    placeholder={t('plat.schools.search')}
                    className="ui-input max-w-md"
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            router.get(route('platform.schools.index'), { q: e.currentTarget.value }, { preserveState: true });
                        }
                    }}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <DataTable
                        columns={columns}
                        rows={schools?.data ?? []}
                        emptyMessage={t('plat.schools.empty')}
                    />
                </section>

                <section className="lg:col-span-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('platform.schools.store'), { preserveScroll: true });
                        }}
                        className="console-card"
                    >
                        <h2 className="text-base font-semibold text-navy">{t('plat.schools.provision')}</h2>
                        <div className="mt-4 space-y-3">
                            <input className="ui-input" placeholder={t('plat.schools.field.name')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <input className="ui-input" placeholder={t('plat.schools.field.slug')} value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                            <input className="ui-input" placeholder={t('plat.schools.field.email')} type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            <input className="ui-input" placeholder={t('plat.schools.field.billing_email')} type="email" value={form.data.billing_email} onChange={(e) => form.setData('billing_email', e.target.value)} />
                            <input className="ui-input" placeholder={t('plat.schools.field.region')} value={form.data.region} onChange={(e) => form.setData('region', e.target.value)} />
                            <input className="ui-input" placeholder={t('plat.schools.field.host')} value={form.data.host} onChange={(e) => form.setData('host', e.target.value)} />
                            <label className="block text-sm text-slate-600">
                                {t('platformSchools.form.schoolType')}
                                <select className="mt-1 ui-input" value={form.data.school_type} onChange={(e) => form.setData('school_type', e.target.value)}>
                                    {types.map((ty) => (
                                        <option key={ty} value={ty}>{l.schoolType(ty)}</option>
                                    ))}
                                </select>
                            </label>
                            <p className="text-xs text-slate-500">{t('platformSchools.form.trialNote')}</p>
                            <button type="submit" disabled={form.processing} className="btn-teal w-full whitespace-nowrap text-sm">
                                {t('plat.schools.create')}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </AppShell>
    );
}
