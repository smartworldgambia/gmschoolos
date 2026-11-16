import AccountingLayout from '@/Components/SchoolOS/AccountingLayout';
import DataTable from '@/Components/SchoolOS/DataTable';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Journals({ journals, filters = {}, accounts = [], canPost = false, current = 'journal', ...shell }) {
    const { t } = useLocale();
    const [open, setOpen] = useState(false);
    const form = useForm({
        date: new Date().toISOString().slice(0, 10),
        memo: '',
        lines: [
            { account_id: '', debit: '', credit: '', memo: '' },
            { account_id: '', debit: '', credit: '', memo: '' },
        ],
    });

    const columns = [
        { key: 'number', label: t('ais.col.number') },
        { key: 'date', label: t('ais.col.date') },
        { key: 'memo', label: t('ais.col.memo'), cellClassName: 'whitespace-normal' },
        { key: 'source', label: t('ais.col.source'), render: (r) => t(`ais.source.${r.source}`) },
        { key: 'debit', label: t('ais.col.debit'), cellClassName: 'text-end tabular-nums', render: (r) => money(r.debit) },
        {
            key: 'status',
            label: t('ais.col.status'),
            render: (r) => <StatusBadge status={r.status} label={t(`ais.status.${r.status}`)} />,
        },
        {
            key: 'actions',
            label: '',
            render: (r) =>
                canPost && r.status === 'posted' ? (
                    <button
                        type="button"
                        className="btn-outline text-xs"
                        onClick={() => router.post(route('accounting.journals.reverse', r.id))}
                    >
                        {t('ais.reverse')}
                    </button>
                ) : null,
        },
    ];

    function submit(e) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            lines: data.lines.map((l) => ({
                account_id: Number(l.account_id),
                debit: Number(l.debit || 0),
                credit: Number(l.credit || 0),
                memo: l.memo || null,
            })),
        })).post(route('accounting.journals.store'), {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <AccountingLayout
            title={t('ais.journals.title')}
            subtitle={t('ais.journals.subtitle')}
            current={current}
            shell={shell}
            actions={
                canPost ? (
                    <button type="button" className="btn-teal text-sm" onClick={() => setOpen((v) => !v)}>
                        {t('ais.journals.new')}
                    </button>
                ) : null
            }
        >
            {open ? (
                <form onSubmit={submit} className="mb-6 space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap gap-3">
                        <label className="text-sm">
                            <span className="mb-1 block text-slate-500">{t('ais.col.date')}</span>
                            <input type="date" className="rounded-lg border px-3 py-2" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                        </label>
                        <label className="min-w-[16rem] flex-1 text-sm">
                            <span className="mb-1 block text-slate-500">{t('ais.col.memo')}</span>
                            <input className="w-full rounded-lg border px-3 py-2" value={form.data.memo} onChange={(e) => form.setData('memo', e.target.value)} />
                        </label>
                    </div>
                    {form.data.lines.map((line, i) => (
                        <div key={i} className="grid gap-2 sm:grid-cols-4">
                            <select
                                className="rounded-lg border px-3 py-2 text-sm"
                                value={line.account_id}
                                onChange={(e) => {
                                    const lines = [...form.data.lines];
                                    lines[i] = { ...lines[i], account_id: e.target.value };
                                    form.setData('lines', lines);
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
                                    const lines = [...form.data.lines];
                                    lines[i] = { ...lines[i], debit: e.target.value, credit: '' };
                                    form.setData('lines', lines);
                                }}
                            />
                            <input
                                type="number"
                                min="0"
                                placeholder={t('ais.col.credit')}
                                className="rounded-lg border px-3 py-2 text-sm"
                                value={line.credit}
                                onChange={(e) => {
                                    const lines = [...form.data.lines];
                                    lines[i] = { ...lines[i], credit: e.target.value, debit: '' };
                                    form.setData('lines', lines);
                                }}
                            />
                            <input
                                placeholder={t('ais.col.memo')}
                                className="rounded-lg border px-3 py-2 text-sm"
                                value={line.memo}
                                onChange={(e) => {
                                    const lines = [...form.data.lines];
                                    lines[i] = { ...lines[i], memo: e.target.value };
                                    form.setData('lines', lines);
                                }}
                            />
                        </div>
                    ))}
                    <div className="flex gap-2">
                        <button
                            type="button"
                            className="btn-outline text-sm"
                            onClick={() => form.setData('lines', [...form.data.lines, { account_id: '', debit: '', credit: '', memo: '' }])}
                        >
                            {t('ais.addLine')}
                        </button>
                        <button type="submit" className="btn-navy text-sm" disabled={form.processing}>
                            {t('ais.post')}
                        </button>
                    </div>
                </form>
            ) : null}

            <div className="mb-4">
                <select
                    className="rounded-lg border px-3 py-2 text-sm"
                    value={filters.source || ''}
                    onChange={(e) => router.get(route('accounting.journals'), { source: e.target.value || undefined }, { preserveState: true })}
                >
                    <option value="">{t('ais.source.all')}</option>
                    {['manual', 'fee_invoice', 'fee_payment', 'payroll', 'procurement', 'procurement_pay', 'opening', 'reversal'].map((s) => (
                        <option key={s} value={s}>
                            {t(`ais.source.${s}`)}
                        </option>
                    ))}
                </select>
            </div>

            <DataTable columns={columns} rows={journals.data || []} emptyMessage={t('ais.empty.journals')} />
        </AccountingLayout>
    );
}
