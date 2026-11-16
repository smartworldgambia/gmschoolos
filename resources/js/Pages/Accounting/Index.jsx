import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import StatCard from '@/Components/SchoolOS/StatCard';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { Link, router } from '@inertiajs/react';

export default function Index({ stats = {}, period = {}, current = 'overview', ...shell }) {
    const { t } = useLocale();

    function applyPeriod(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        router.get(route('accounting.index'), { from: fd.get('from'), to: fd.get('to') }, { preserveState: true });
    }

    return (
        <AccountingLayout title={t('ais.overview.title')} subtitle={t('ais.overview.subtitle')} current={current} shell={shell}>
            {stats.setup_needed ? (
                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    {t('ais.setup.needed')}{' '}
                    {shell.can?.manage ? (
                        <Link href={route('accounting.setup')} className="font-semibold text-teal underline underline-offset-2">
                            {t('ais.setup.open')}
                        </Link>
                    ) : null}
                </div>
            ) : null}

            <form onSubmit={applyPeriod} className="mb-6 flex flex-wrap items-end gap-3">
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.from')}</span>
                    <input type="date" name="from" defaultValue={period.from} className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-slate-500">{t('ais.to')}</span>
                    <input type="date" name="to" defaultValue={period.to} className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </label>
                <button type="submit" className="btn-outline text-sm">
                    {t('ais.apply')}
                </button>
            </form>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title={t('ais.stat.income')} value={money(stats.income)} tone="teal" />
                <StatCard title={t('ais.stat.expenses')} value={money(stats.expenses)} tone="orange" />
                <StatCard title={t('ais.stat.surplus')} value={money(stats.surplus)} tone="navy" />
                <StatCard title={t('ais.stat.cashBank')} value={money(stats.cash_bank)} tone="blue" />
                <StatCard title={t('ais.stat.ar')} value={money(stats.ar)} tone="purple" />
                <StatCard title={t('ais.stat.ap')} value={money(stats.ap)} tone="purple" />
            </div>

            <div className="mt-8 flex flex-wrap gap-2">
                <Link href={route('fees.index')} className="btn-teal text-sm">
                    {t('ais.link.fees')}
                </Link>
                <Link href={route('accounting.trial-balance')} className="btn-navy text-sm">
                    {t('ais.link.trial')}
                </Link>
                <Link href={route('accounting.cashbook')} className="btn-outline text-sm">
                    {t('ais.link.cashbook')}
                </Link>
            </div>
        </AccountingLayout>
    );
}
