import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { STAFF_METHODS, money } from '@/lib/fees';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const STATUSES = ['pending', 'confirmed', 'rejected', 'voided'];

export default function Payments(props) {
    const { payments, filters = {}, pendingCount = 0, can = {} } = props;
    const { t, locale } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');
    const filter = (patch) => router.get(route('fees.payments.index'), { ...filters, ...patch }, { preserveState: true, replace: true });

    const columns = [
        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) },
        { key: 'receipt_number', label: t('fin.col.receipt') },
        {
            key: 'invoice',
            label: t('fin.col.invoice'),
            render: (r) => (
                <Link href={route('fees.invoices.show', r.invoice_id)} className="text-teal-700 hover:underline">
                    {r.invoice}
                </Link>
            ),
        },
        { key: 'student', label: t('fin.col.student') },
        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
        { key: 'reference', label: t('fin.col.reference') },
        { key: 'by', label: t('fin.col.by'), render: (r) => (r.submitted ? `${r.by ?? ''} · ${t('fin.payments.byFamily')}` : r.by) },
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex flex-wrap items-start gap-2 whitespace-normal">
                    {r.status === 'pending' && can.verify ? (
                        <>
                            <button type="button" className="btn-teal" onClick={() => router.post(route('fees.payments.confirm', r.id), {}, { preserveScroll: true })}>
                                {t('fin.action.confirm')}
                            </button>
                            <ReasonAction url={route('fees.payments.reject', r.id)} fieldName="note" label={t('fin.action.reject')} tone="danger" />
                        </>
                    ) : null}
                    {r.status === 'confirmed' ? (
                        <a href={route('fees.payments.receipt', r.id)} target="_blank" rel="noreferrer" className="btn-outline">
                            {t('fin.action.receipt')}
                        </a>
                    ) : null}
                    {r.has_evidence ? (
                        <a href={route('fees.payments.evidence', r.id)} className="btn-outline">
                            {t('fin.action.evidence')}
                        </a>
                    ) : null}
                    {r.status === 'confirmed' && can.approve && !r.is_reconciled ? <ReasonAction url={route('fees.payments.void', r.id)} label={t('fin.action.void')} tone="danger" /> : null}
                </div>
            ),
        },
    ];

    return (
        <FinanceLayout title={t('fin.payments.title')} subtitle={t('fin.payments.subtitle')} current="payments" shell={props}>
            {pendingCount > 0 ? (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{t('fin.index.pendingNote', { n: pendingCount })}</div>
            ) : null}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    filter({ q, page: undefined });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t('acad.common.search')} className="min-w-[14rem] flex-1">
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('fin.payments.searchPlaceholder')} />
                </Field>
                <Field label={t('fin.col.status')}>
                    <Select value={filters.status ?? ''} onChange={(e) => filter({ status: e.target.value, page: undefined })}>
                        <option value="">{t('acad.common.all')}</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {t(`fin.status.${s}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t('fin.col.method')}>
                    <Select value={filters.method ?? ''} onChange={(e) => filter({ method: e.target.value, page: undefined })}>
                        <option value="">{t('acad.common.all')}</option>
                        {STAFF_METHODS.map((m) => (
                            <option key={m} value={m}>
                                {t(`fin.method.${m}`)}
                            </option>
                        ))}
                    </Select>
                </Field>
                <button type="submit" className="btn-navy">
                    {t('acad.common.search')}
                </button>
            </form>

            <DataTable columns={columns} rows={payments.data} emptyMessage={t('fin.payments.empty')} />
            <Pager links={payments.links} />
        </FinanceLayout>
    );
}
