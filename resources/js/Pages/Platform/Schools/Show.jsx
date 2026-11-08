import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';

export default function Show({
    school: tenant,
    domains = [],
    subscription,
    quota = {},
    quotaLimits = {},
    usageSnapshots = [],
    types = [],
    payments = [],
    updateUrls = {},
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;
    const schoolForm = useForm({
        name: tenant.name ?? '',
        status: tenant.status ?? 'active',
        email: tenant.email ?? '',
        billing_email: tenant.billing_email ?? '',
        region: tenant.region ?? '',
    });

    const typeForm = useForm({
        school_type: subscription?.school_type ?? types[0] ?? 'primary',
    });

    const payForm = useForm({
        method: 'cash',
        amount: '',
        transaction_reference: '',
        paid_at: '',
        notes: '',
    });

    const quotaForm = useForm({
        max_students: quotaLimits.max_students ?? '',
        max_staff: quotaLimits.max_staff ?? '',
        max_storage_mb: quotaLimits.max_storage_mb ?? '',
        max_exports_per_day: quotaLimits.max_exports_per_day ?? '',
        max_api_requests_per_hour: quotaLimits.max_api_requests_per_hour ?? '',
        is_premium_isolated: Boolean(quotaLimits.is_premium_isolated),
    });

    const domainForm = useForm({
        host: '',
        is_primary: false,
        is_custom: true,
    });

    const usageColumns = [
        { key: 'captured_on', label: t('plat.col.date') },
        { key: 'students_count', label: t('plat.col.students') },
        { key: 'staff_count', label: t('plat.schools.col.staff') },
        { key: 'request_count', label: t('plat.col.requests') },
        { key: 'export_count', label: t('plat.col.exports') },
        { key: 'import_count', label: t('plat.col.imports') },
    ];

    return (
        <AppShell title={tenant.name} nav={nav} user={user} school={school}>
            <Head title={tenant.name} />

            <PageHeader
                title={tenant.name}
                subtitle={`${tenant.slug} · ${tenant.region ?? 'GM'}`}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {tenant.public_url ? (
                            <a href={tenant.public_url} className="btn-outline whitespace-nowrap text-sm" target="_blank" rel="noreferrer">
                                {t('plat.schools.viewSite')}
                            </a>
                        ) : null}
                        {tenant.suspended_at ? (
                            <button
                                type="button"
                                className="btn-teal whitespace-nowrap text-sm"
                                onClick={() => router.post(updateUrls.unsuspend)}
                            >
                                {t('plat.schools.unsuspend')}
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="btn-navy whitespace-nowrap bg-orange-500 text-sm hover:bg-orange-600"
                                onClick={() => router.post(updateUrls.suspend)}
                            >
                                {t('plat.schools.suspend')}
                            </button>
                        )}
                    </div>
                }
            />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <StatusBadge status={tenant.suspended_at ? 'suspended' : tenant.status} />
                <span className="text-sm text-slate-500">
                    {t('plat.schools.quotaStudents', { used: quota.students?.used ?? 0 })}
                    {quota.students?.max != null ? ` / ${quota.students.max}` : ''}
                    {quota.students?.pct != null ? ` (${quota.students.pct}%)` : ''}
                </span>
            </div>
            {tenant.public_url ? (
                <p className="mb-6 text-sm">
                    <span className="text-slate-500">{t('plat.schools.col.publicUrl')}: </span>
                    <a
                        href={tenant.public_url}
                        className="break-all font-medium text-teal hover:underline"
                        target="_blank"
                        rel="noreferrer"
                        dir="ltr"
                    >
                        {tenant.public_url}
                    </a>
                </p>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        schoolForm.patch(updateUrls.school, { preserveScroll: true });
                    }}
                    className="console-card"
                >
                    <h2 className="font-semibold text-navy">{t('plat.schools.profile')}</h2>
                    <div className="mt-3 space-y-3">
                        {['name', 'email', 'billing_email', 'region'].map((field) => (
                            <input
                                key={field}
                                className="ui-input"
                                placeholder={t(`plat.schools.field.${field}`)}
                                value={schoolForm.data[field]}
                                onChange={(e) => schoolForm.setData(field, e.target.value)}
                            />
                        ))}
                        <select
                            className="ui-input"
                            value={schoolForm.data.status}
                            onChange={(e) => schoolForm.setData('status', e.target.value)}
                        >
                            <option value="active">{t('plat.schools.status.active')}</option>
                            <option value="pending">{t('plat.schools.status.pending')}</option>
                            <option value="inactive">{t('plat.schools.status.inactive')}</option>
                        </select>
                        <button type="submit" className="btn-navy whitespace-nowrap text-sm">
                            {t('plat.schools.save')}
                        </button>
                    </div>
                </form>

                <div className="console-card">
                    <h2 className="font-semibold text-navy">{t('platformSchool.sub.title')}</h2>
                    {!subscription ? (
                        <p className="mt-3 text-sm text-slate-500">{t('platformSchool.sub.none')}</p>
                    ) : (
                        <>
                            <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt className="text-slate-500">{t('platformSchool.sub.status')}</dt>
                                    <dd className="mt-1">
                                        <StatusBadge status={subscription.status} label={l.status(subscription.status)} />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">{t('platformSchool.sub.payment')}</dt>
                                    <dd className="mt-1">
                                        <StatusBadge
                                            status={subscription.payment_status}
                                            label={l.payStatus(subscription.payment_status)}
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">{t('platformSchool.sub.fee')}</dt>
                                    <dd className="mt-1 font-semibold text-navy">
                                        {subscription.annual_amount_label ?? t('billing.notSet')}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">{t('platformSchool.sub.ends')}</dt>
                                    <dd className="mt-1 font-semibold text-navy">{l.date(subscription.ends_at)}</dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">{t('platformSchool.sub.daysLeft')}</dt>
                                    <dd className="mt-1 font-semibold text-navy">{subscription.days_remaining}</dd>
                                </div>
                            </dl>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    typeForm.patch(updateUrls.schoolType, { preserveScroll: true });
                                }}
                                className="mt-5 border-t border-slate-100 pt-4"
                            >
                                <label className="block text-sm text-slate-600">
                                    {t('platformSchool.sub.changeType')}
                                    <select
                                        className="mt-1 ui-input"
                                        value={typeForm.data.school_type}
                                        onChange={(e) => typeForm.setData('school_type', e.target.value)}
                                    >
                                        {types.map((ty) => (
                                            <option key={ty} value={ty}>
                                                {l.schoolType(ty)}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <p className="mt-1 text-xs text-slate-500">{t('platformSchool.sub.changeTypeHelp')}</p>
                                {typeForm.errors.school_type ? (
                                    <p className="mt-1 text-xs text-red-600">{typeForm.errors.school_type}</p>
                                ) : null}
                                <button
                                    type="submit"
                                    disabled={typeForm.processing}
                                    className="btn-navy mt-3 whitespace-nowrap text-sm"
                                >
                                    {t('platformSchool.sub.saveType')}
                                </button>
                            </form>
                        </>
                    )}
                </div>

                {subscription ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            payForm.post(updateUrls.payment, {
                                preserveScroll: true,
                                onSuccess: () => payForm.reset('amount', 'transaction_reference', 'paid_at', 'notes'),
                            });
                        }}
                        className="console-card"
                    >
                        <h2 className="font-semibold text-navy">{t('platformSchool.pay.title')}</h2>
                        <p className="mt-2 text-xs leading-5 text-slate-500">{t('platformSchool.pay.help')}</p>
                        <div className="mt-3 space-y-3">
                            <label className="block text-sm text-slate-600">
                                {t('platformSchool.pay.method')}
                                <select
                                    className="mt-1 ui-input"
                                    value={payForm.data.method}
                                    onChange={(e) => payForm.setData('method', e.target.value)}
                                >
                                    {['cash', 'bank_transfer', 'wave'].map((m) => (
                                        <option key={m} value={m}>
                                            {l.method(m)}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block text-sm text-slate-600">
                                {t('platformSchool.pay.amount')}
                                <input
                                    type="number"
                                    min="1"
                                    className="mt-1 ui-input"
                                    value={payForm.data.amount}
                                    onChange={(e) => payForm.setData('amount', e.target.value)}
                                />
                            </label>
                            <div className="grid grid-cols-2 gap-3">
                                <label className="block text-sm text-slate-600">
                                    {t('platformSchool.pay.reference')}
                                    <input
                                        className="mt-1 ui-input"
                                        value={payForm.data.transaction_reference}
                                        onChange={(e) => payForm.setData('transaction_reference', e.target.value)}
                                    />
                                </label>
                                <label className="block text-sm text-slate-600">
                                    {t('platformSchool.pay.paidAt')}
                                    <input
                                        type="date"
                                        className="mt-1 ui-input"
                                        value={payForm.data.paid_at}
                                        onChange={(e) => payForm.setData('paid_at', e.target.value)}
                                    />
                                </label>
                            </div>
                            <label className="block text-sm text-slate-600">
                                {t('platformSchool.pay.notes')}
                                <input
                                    className="mt-1 ui-input"
                                    value={payForm.data.notes}
                                    onChange={(e) => payForm.setData('notes', e.target.value)}
                                />
                            </label>
                            {Object.values(payForm.errors).length > 0 ? (
                                <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                                    {Object.values(payForm.errors)[0]}
                                </p>
                            ) : null}
                            <button type="submit" disabled={payForm.processing} className="btn-teal whitespace-nowrap text-sm">
                                {t('platformSchool.pay.submit')}
                            </button>
                        </div>
                    </form>
                ) : null}

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        quotaForm.patch(updateUrls.quota, { preserveScroll: true });
                    }}
                    className="console-card"
                >
                    <h2 className="font-semibold text-navy">{t('plat.schools.quotas')}</h2>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        {[
                            'max_students',
                            'max_staff',
                            'max_storage_mb',
                            'max_exports_per_day',
                            'max_api_requests_per_hour',
                        ].map((field) => (
                            <input
                                key={field}
                                type="number"
                                className="ui-input"
                                placeholder={t(`plat.schools.quota.${field}`)}
                                value={quotaForm.data[field]}
                                onChange={(e) => quotaForm.setData(field, e.target.value)}
                            />
                        ))}
                        <label className="flex items-center gap-2 text-sm text-slate-600 sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={quotaForm.data.is_premium_isolated}
                                onChange={(e) => quotaForm.setData('is_premium_isolated', e.target.checked)}
                            />
                            {t('plat.schools.premiumIsolated')}
                        </label>
                    </div>
                    <button type="submit" className="btn-navy mt-4 whitespace-nowrap text-sm">
                        {t('plat.schools.saveQuotas')}
                    </button>
                </form>

                <div className="console-card">
                    <h2 className="font-semibold text-navy">{t('plat.schools.domains')}</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        {domains.map((d) => (
                            <li key={d.id} className="flex items-center justify-between gap-2">
                                <span className="font-medium text-navy">{d.host}</span>
                                <span className="text-slate-500">
                                    {d.is_primary ? t('plat.schools.primary') : t('plat.schools.alias')}
                                    {d.is_custom ? ` · ${t('plat.schools.custom')}` : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            domainForm.post(updateUrls.domain, {
                                preserveScroll: true,
                                onSuccess: () => domainForm.reset('host'),
                            });
                        }}
                        className="mt-4 flex gap-2"
                    >
                        <input
                            className="ui-input flex-1"
                            placeholder={t('plat.schools.domainPlaceholder')}
                            value={domainForm.data.host}
                            onChange={(e) => domainForm.setData('host', e.target.value)}
                            required
                        />
                        <button type="submit" className="btn-teal whitespace-nowrap px-3 py-2 text-sm">
                            {t('plat.schools.addDomain')}
                        </button>
                    </form>
                </div>
            </div>

            <section className="mt-8">
                <h2 className="console-section-title">
                    {t('platformSchool.pay.history')}
                </h2>
                <DataTable
                    columns={[
                        { key: 'receipt_number', label: t('billing.col.receipt'), render: (r) => r.receipt_number ?? r.reference },
                        { key: 'method', label: t('billing.col.method'), render: (r) => l.method(r.method) },
                        { key: 'total_label', label: t('billing.col.amount') },
                        { key: 'paid_at', label: t('billing.col.date'), render: (r) => l.date(r.paid_at) },
                        { key: 'period_end', label: t('platformBilling.col.coveredUntil'), render: (r) => l.date(r.period_end) },
                        {
                            key: 'status',
                            label: t('billing.col.status'),
                            render: (r) => <StatusBadge status={r.status} label={l.paymentStatus(r.status)} />,
                        },
                    ]}
                    rows={payments}
                    emptyMessage={t('platformSchool.pay.empty')}
                />
            </section>

            <section className="mt-8">
                <h2 className="console-section-title">
                    {t('plat.schools.usage')}
                </h2>
                <DataTable columns={usageColumns} rows={usageSnapshots} emptyMessage={t('plat.schools.noSnapshots')} />
            </section>
        </AppShell>
    );
}
