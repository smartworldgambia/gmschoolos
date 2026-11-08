import { useBillingLabels } from '@/Components/Billing/useBillingLabels';
import InputError from '@/Components/InputError';
import ConsoleBanner from '@/Components/SchoolOS/ConsoleBanner';
import ConsoleSection from '@/Components/SchoolOS/ConsoleSection';
import DataTable from '@/Components/SchoolOS/DataTable';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import AppShell from '@/Layouts/AppShell';
import { Head, useForm } from '@inertiajs/react';

export default function Index({
    types = [],
    history = [],
    storeUrl,
    defaultCurrency = 'GMD',
    nav = [],
    user,
    school,
}) {
    const l = useBillingLabels();
    const { t } = l;

    const form = useForm({
        school_type: types[0]?.type ?? 'primary',
        amount: '',
        currency: defaultCurrency,
        tax_rate: '',
        effective_from: '',
        description: '',
    });

    const hasPlaceholders = types.some((x) => (x.description ?? '').startsWith('PLACEHOLDER'));

    const currentColumns = [
        { key: 'type', label: t('platformPricing.col.type'), render: (r) => l.schoolType(r.type) },
        {
            key: 'total_label',
            label: t('platformPricing.col.price'),
            render: (r) => r.total_label ?? <span className="text-amber-700">{t('platformPricing.notConfigured')}</span>,
        },
        { key: 'tax_rate', label: t('platformPricing.col.tax'), render: (r) => (r.tax_rate ? `${Number(r.tax_rate)}%` : null) },
        { key: 'effective_from', label: t('platformPricing.col.effective'), render: (r) => l.date(r.effective_from) },
        { key: 'schools', label: t('platformPricing.col.schools') },
    ];

    const historyColumns = [
        { key: 'school_type', label: t('platformPricing.col.type'), render: (r) => l.schoolType(r.school_type) },
        {
            key: 'amount',
            label: t('platformPricing.col.price'),
            render: (r) => `${r.currency} ${Number(r.amount).toLocaleString()}`,
        },
        { key: 'tax_rate', label: t('platformPricing.col.tax'), render: (r) => (r.tax_rate ? `${Number(r.tax_rate)}%` : null) },
        { key: 'effective_from', label: t('platformPricing.col.effective'), render: (r) => l.date(r.effective_from) },
        {
            key: 'status',
            label: t('platformPricing.col.state'),
            render: (r) => <StatusBadge status={r.status} label={l.priceState(r.status)} />,
        },
        { key: 'created_by', label: t('platformPricing.col.setBy') },
        {
            key: 'description',
            label: t('platformPricing.col.note'),
            cellClassName: 'whitespace-normal max-w-xs',
        },
    ];

    function submit(e) {
        e.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('amount', 'tax_rate', 'effective_from', 'description'),
        });
    }

    return (
        <AppShell title={t('platformPricing.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformPricing.title')} />

            <PageHeader title={t('platformPricing.title')} subtitle={t('platformPricing.subtitle')} />

            {hasPlaceholders ? (
                <ConsoleBanner tone="warn">{t('platformPricing.placeholderWarning')}</ConsoleBanner>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-5">
                <ConsoleSection className="lg:col-span-3" title={t('platformPricing.current')} icon="pricing">
                    <DataTable columns={currentColumns} rows={types} keyField="type" emptyMessage={t('platformPricing.historyEmpty')} />
                </ConsoleSection>

                <section className="lg:col-span-2">
                    <form onSubmit={submit} className="console-card">
                        <h2 className="text-base font-semibold text-navy">{t('platformPricing.form.title')}</h2>
                        <div className="mt-4 space-y-3">
                            <label className="block text-sm text-slate-600">
                                {t('platformPricing.form.type')}
                                <select
                                    className="ui-input mt-1"
                                    value={form.data.school_type}
                                    onChange={(e) => form.setData('school_type', e.target.value)}
                                >
                                    {types.map((x) => (
                                        <option key={x.type} value={x.type}>
                                            {l.schoolType(x.type)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.school_type} className="mt-1" />
                            </label>
                            <div className="grid grid-cols-3 gap-3">
                                <label className="col-span-2 block text-sm text-slate-600">
                                    {t('platformPricing.form.amount')}
                                    <input
                                        type="number"
                                        min="1"
                                        required
                                        className="ui-input mt-1"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                    />
                                </label>
                                <label className="block text-sm text-slate-600">
                                    {t('platformPricing.form.currency')}
                                    <input
                                        maxLength={3}
                                        required
                                        className="ui-input mt-1 uppercase"
                                        value={form.data.currency}
                                        onChange={(e) => form.setData('currency', e.target.value.toUpperCase())}
                                    />
                                </label>
                            </div>
                            <InputError message={form.errors.amount ?? form.errors.currency} className="mt-1" />
                            <div className="grid grid-cols-2 gap-3">
                                <label className="block text-sm text-slate-600">
                                    {t('platformPricing.form.tax')}
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        className="ui-input mt-1"
                                        value={form.data.tax_rate}
                                        onChange={(e) => form.setData('tax_rate', e.target.value)}
                                    />
                                </label>
                                <label className="block text-sm text-slate-600">
                                    {t('platformPricing.form.effective')}
                                    <input
                                        type="date"
                                        className="ui-input mt-1"
                                        value={form.data.effective_from}
                                        onChange={(e) => form.setData('effective_from', e.target.value)}
                                    />
                                </label>
                            </div>
                            <label className="block text-sm text-slate-600">
                                {t('platformPricing.form.note')}
                                <input
                                    className="ui-input mt-1"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                            </label>
                            <p className="text-xs text-slate-500">{t('platformPricing.form.help')}</p>
                            <button type="submit" disabled={form.processing} className="btn-teal w-full whitespace-nowrap text-sm">
                                {t('platformPricing.form.save')}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <ConsoleSection className="mt-8" title={t('platformPricing.history')} icon="ops">
                <DataTable columns={historyColumns} rows={history} emptyMessage={t('platformPricing.historyEmpty')} />
            </ConsoleSection>
        </AppShell>
    );
}
