import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import DataTable from '@/Components/SchoolOS/DataTable';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router } from '@inertiajs/react';

export default function TrialBalance({ rows = [], totals = {}, asOf, canExport = false, current = 'trialBalance', ...shell }) {
    const { t } = useLocale();

    const columns = [
        { key: 'code', label: t('ais.col.code') },
        { key: 'name', label: t('ais.col.name') },
        { key: 'type', label: t('ais.col.type'), render: (r) => t(`ais.type.${r.type}`, {}, r.type) },
        { key: 'debit', label: t('ais.col.debit'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.debit) },
        { key: 'credit', label: t('ais.col.credit'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.credit) },
    ];

    return (
        <AccountingLayout
            title={t('ais.trial.title')}
            subtitle={t('ais.trial.subtitle')}
            current={current}
            shell={shell}
            actions={
                canExport ? (
                    <a href={route('accounting.trial-balance', { as_of: asOf, export: 1 })} className="btn-outline text-sm">
                        {t('ais.export')}
                    </a>
                ) : null
            }
        >
            <form
                className="mb-6 flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('accounting.trial-balance'), { as_of: new FormData(e.target).get('as_of') }, { preserveState: true });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.asOf')}</span>
                    <input type="date" name="as_of" defaultValue={asOf} className="rounded-lg border px-3 py-2 text-sm" />
                </label>
                <button type="submit" className="btn-outline text-sm">
                    {t('ais.apply')}
                </button>
            </form>
            <DataTable columns={columns} rows={rows} emptyMessage={t('ais.empty.trial')} />
            <p className="mt-4 text-sm font-semibold text-navy">
                {t('ais.totals')}: {money(totals.debit)} / {money(totals.credit)}
            </p>
        </AccountingLayout>
    );
}
