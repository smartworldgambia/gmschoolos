import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import DataTable from '@/Components/SchoolOS/DataTable';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router } from '@inertiajs/react';

export default function Income({ rows = [], totals = {}, period = {}, canExport = false, current = 'income', ...shell }) {
    const { t } = useLocale();
    const columns = [
        { key: 'code', label: t('ais.col.code') },
        { key: 'name', label: t('ais.col.name') },
        { key: 'type', label: t('ais.col.type'), render: (r) => t(`ais.type.${r.type}`, {}, r.type) },
        { key: 'amount', label: t('ais.col.amount'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.amount) },
    ];

    return (
        <AccountingLayout
            title={t('ais.income.title')}
            subtitle={t('ais.income.subtitle')}
            current={current}
            shell={shell}
            actions={
                canExport ? (
                    <a href={route('accounting.income', { from: period.from, to: period.to, export: 1 })} className="btn-outline text-sm">
                        {t('ais.export')}
                    </a>
                ) : null
            }
        >
            <form
                className="mb-6 flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    const fd = new FormData(e.target);
                    router.get(route('accounting.income'), { from: fd.get('from'), to: fd.get('to') }, { preserveState: true });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.from')}</span>
                    <input type="date" name="from" defaultValue={period.from} className="rounded-lg border px-3 py-2 text-sm" />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.to')}</span>
                    <input type="date" name="to" defaultValue={period.to} className="rounded-lg border px-3 py-2 text-sm" />
                </label>
                <button type="submit" className="btn-outline text-sm">
                    {t('ais.apply')}
                </button>
            </form>
            <DataTable columns={columns} rows={rows} emptyMessage={t('ais.empty.income')} />
            <p className="mt-4 text-sm font-semibold text-navy">
                {t('ais.stat.surplus')}: {money(totals.surplus)}
            </p>
        </AccountingLayout>
    );
}
