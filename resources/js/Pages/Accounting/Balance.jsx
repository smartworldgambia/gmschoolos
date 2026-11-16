import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router } from '@inertiajs/react';

function Section({ title, rows }) {
    const { t } = useLocale();
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{title}</h2>
            <ul className="space-y-2">
                {(rows || []).map((r) => (
                    <li key={r.code} className="flex items-center justify-between gap-3 text-sm">
                        <span className="text-slate-600">
                            {r.code} {r.name}
                        </span>
                        <span className="font-semibold tabular-nums text-navy">{money(r.amount)}</span>
                    </li>
                ))}
                {(rows || []).length === 0 ? <li className="text-sm text-slate-400">{t('ais.empty.section')}</li> : null}
            </ul>
        </div>
    );
}

export default function Balance({ sheet = {}, asOf, current = 'balance', ...shell }) {
    const { t } = useLocale();

    return (
        <AccountingLayout title={t('ais.balance.title')} subtitle={t('ais.balance.subtitle')} current={current} shell={shell}>
            <form
                className="mb-6 flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(route('accounting.balance'), { as_of: new FormData(e.target).get('as_of') }, { preserveState: true });
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
            <div className="grid gap-4 lg:grid-cols-3">
                <Section title={t('ais.type.asset')} rows={sheet.assets} />
                <Section title={t('ais.type.liability')} rows={sheet.liabilities} />
                <Section title={t('ais.type.equity')} rows={sheet.equity} />
            </div>
        </AccountingLayout>
    );
}
