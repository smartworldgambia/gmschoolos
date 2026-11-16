import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import DataTable from '@/Components/SchoolOS/DataTable';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router } from '@inertiajs/react';

export default function Ledger({ accounts = [], accountId = null, rows = [], period = {}, current = 'ledger', ...shell }) {
    const { t } = useLocale();

    function apply(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        router.get(route('accounting.ledger'), { account_id: fd.get('account_id'), from: fd.get('from'), to: fd.get('to') }, { preserveState: true });
    }

    const columns = [
        { key: 'date', label: t('ais.col.date') },
        { key: 'number', label: t('ais.col.number') },
        { key: 'memo', label: t('ais.col.memo'), cellClassName: 'whitespace-normal' },
        { key: 'debit', label: t('ais.col.debit'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.debit) },
        { key: 'credit', label: t('ais.col.credit'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.credit) },
        { key: 'balance', label: t('ais.col.balance'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.balance) },
    ];

    return (
        <AccountingLayout title={t('ais.ledger.title')} subtitle={t('ais.ledger.subtitle')} current={current} shell={shell}>
            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3">
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.account')}</span>
                    <select name="account_id" defaultValue={accountId || ''} className="rounded-lg border px-3 py-2 text-sm">
                        <option value="">{t('ais.pickAccount')}</option>
                        {accounts.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.code} — {a.name}
                            </option>
                        ))}
                    </select>
                </label>
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
            <DataTable columns={columns} rows={rows} emptyMessage={t('ais.empty.ledger')} />
        </AccountingLayout>
    );
}
