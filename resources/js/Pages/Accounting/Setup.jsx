import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { router, useForm } from '@inertiajs/react';

export default function Setup({
    accounts = [],
    maps = {},
    mapKeys = [],
    locks = [],
    hasOpening = false,
    current = 'setup',
    ...shell
}) {
    const { t } = useLocale();
    const accountForm = useForm({ code: '', name: '', type: 'expense', subtype: '' });
    const mapForm = useForm({ maps: { ...maps } });
    const lockForm = useForm({ year_month: new Date().toISOString().slice(0, 7) });
    const openingForm = useForm({
        date: new Date().toISOString().slice(0, 10),
        lines: [
            { account_id: '', debit: '', credit: '' },
            { account_id: '', debit: '', credit: '' },
        ],
    });

    return (
        <AccountingLayout title={t('ais.setup.title')} subtitle={t('ais.setup.subtitle')} current={current} shell={shell}>
            <section className="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-3 text-base font-semibold text-navy">{t('ais.setup.maps')}</h2>
                <form
                    className="grid gap-3 sm:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        mapForm.post(route('accounting.maps.save'));
                    }}
                >
                    {mapKeys.map((key) => (
                        <label key={key} className="text-sm">
                            <span className="mb-1 block text-slate-500">{t(`ais.map.${key}`, {}, key)}</span>
                            <select
                                className="w-full rounded-lg border px-3 py-2"
                                value={mapForm.data.maps[key] || ''}
                                onChange={(e) => mapForm.setData('maps', { ...mapForm.data.maps, [key]: e.target.value ? Number(e.target.value) : null })}
                            >
                                <option value="">{t('ais.pickAccount')}</option>
                                {accounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ))}
                    <div className="sm:col-span-2">
                        <button type="submit" className="btn-navy text-sm" disabled={mapForm.processing}>
                            {t('ais.setup.saveMaps')}
                        </button>
                    </div>
                </form>
            </section>

            <section className="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-3 text-base font-semibold text-navy">{t('ais.setup.coa')}</h2>
                <ul className="mb-4 divide-y divide-slate-100">
                    {accounts.map((a) => (
                        <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span>
                                <span className="font-mono font-semibold text-navy">{a.code}</span> {a.name}{' '}
                                <span className="text-slate-400">({t(`ais.type.${a.type}`)})</span>
                            </span>
                            {!a.is_system && !a.has_lines ? (
                                <button type="button" className="btn-outline text-xs" onClick={() => router.delete(route('accounting.accounts.destroy', a.id))}>
                                    {t('ais.delete')}
                                </button>
                            ) : null}
                        </li>
                    ))}
                </ul>
                <form
                    className="grid gap-2 sm:grid-cols-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        accountForm.post(route('accounting.accounts.store'), { onSuccess: () => accountForm.reset() });
                    }}
                >
                    <input className="rounded-lg border px-3 py-2 text-sm" placeholder={t('ais.col.code')} value={accountForm.data.code} onChange={(e) => accountForm.setData('code', e.target.value)} />
                    <input className="rounded-lg border px-3 py-2 text-sm" placeholder={t('ais.col.name')} value={accountForm.data.name} onChange={(e) => accountForm.setData('name', e.target.value)} />
                    <select className="rounded-lg border px-3 py-2 text-sm" value={accountForm.data.type} onChange={(e) => accountForm.setData('type', e.target.value)}>
                        {['asset', 'liability', 'equity', 'income', 'expense'].map((type) => (
                            <option key={type} value={type}>
                                {t(`ais.type.${type}`)}
                            </option>
                        ))}
                    </select>
                    <button type="submit" className="btn-teal text-sm" disabled={accountForm.processing}>
                        {t('ais.setup.addAccount')}
                    </button>
                </form>
            </section>

            <section className="mb-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-3 text-base font-semibold text-navy">{t('ais.setup.periods')}</h2>
                <form
                    className="mb-3 flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        lockForm.post(route('accounting.periods.lock'));
                    }}
                >
                    <input type="month" className="rounded-lg border px-3 py-2 text-sm" value={lockForm.data.year_month} onChange={(e) => lockForm.setData('year_month', e.target.value)} />
                    <button type="submit" className="btn-navy text-sm">
                        {t('ais.setup.lock')}
                    </button>
                </form>
                <ul className="space-y-1 text-sm text-slate-600">
                    {locks.map((l) => (
                        <li key={l.year_month} className="flex items-center gap-2">
                            <span>{l.year_month}</span>
                            <button
                                type="button"
                                className="btn-outline text-xs"
                                onClick={() => router.post(route('accounting.periods.unlock'), { year_month: l.year_month })}
                            >
                                {t('ais.setup.unlock')}
                            </button>
                        </li>
                    ))}
                </ul>
            </section>

            {!hasOpening ? (
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-base font-semibold text-navy">{t('ais.setup.opening')}</h2>
                    <form
                        className="space-y-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            openingForm
                                .transform((data) => ({
                                    ...data,
                                    lines: data.lines.map((l) => ({
                                        account_id: Number(l.account_id),
                                        debit: Number(l.debit || 0),
                                        credit: Number(l.credit || 0),
                                    })),
                                }))
                                .post(route('accounting.opening.store'));
                        }}
                    >
                        <input type="date" className="rounded-lg border px-3 py-2 text-sm" value={openingForm.data.date} onChange={(e) => openingForm.setData('date', e.target.value)} />
                        {openingForm.data.lines.map((line, i) => (
                            <div key={i} className="grid gap-2 sm:grid-cols-3">
                                <select
                                    className="rounded-lg border px-3 py-2 text-sm"
                                    value={line.account_id}
                                    onChange={(e) => {
                                        const lines = [...openingForm.data.lines];
                                        lines[i] = { ...lines[i], account_id: e.target.value };
                                        openingForm.setData('lines', lines);
                                    }}
                                >
                                    <option value="">{t('ais.pickAccount')}</option>
                                    {accounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} — {a.name}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    type="number"
                                    min="0"
                                    placeholder={t('ais.col.debit')}
                                    className="rounded-lg border px-3 py-2 text-sm"
                                    value={line.debit}
                                    onChange={(e) => {
                                        const lines = [...openingForm.data.lines];
                                        lines[i] = { ...lines[i], debit: e.target.value, credit: '' };
                                        openingForm.setData('lines', lines);
                                    }}
                                />
                                <input
                                    type="number"
                                    min="0"
                                    placeholder={t('ais.col.credit')}
                                    className="rounded-lg border px-3 py-2 text-sm"
                                    value={line.credit}
                                    onChange={(e) => {
                                        const lines = [...openingForm.data.lines];
                                        lines[i] = { ...lines[i], credit: e.target.value, debit: '' };
                                        openingForm.setData('lines', lines);
                                    }}
                                />
                            </div>
                        ))}
                        <button type="submit" className="btn-teal text-sm" disabled={openingForm.processing}>
                            {t('ais.setup.postOpening')}
                        </button>
                    </form>
                </section>
            ) : null}
        </AccountingLayout>
    );
}
