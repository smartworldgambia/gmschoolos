import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, todayIso } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { FAMILY_METHODS, STAFF_METHODS, isOpenInvoice, money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 py-1.5 text-sm">
            <dt className="text-slate-500">{label}</dt>
            <dd className="text-end font-medium text-navy">{children}</dd>
        </div>
    );
}

function RecordPayment({ invoice }) {
    const { t } = useLocale();
    const f = useForm({ invoice_id: invoice.id, amount: invoice.balance, method: 'cash', paid_on: todayIso(), reference: '', payer_name: '', notes: '' });
    f.transform((d) => ({ ...d, amount: Number(d.amount) }));

    return (
        <Card title={t('fin.record.title')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(route('fees.payments.store'), { preserveScroll: true, onSuccess: () => f.reset('reference', 'payer_name', 'notes') });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label={t('fin.col.amount')} hint={t('fin.record.balanceHint', { amount: money(invoice.balance) })}>
                        <Input type="number" min="1" max={invoice.balance} step="1" value={f.data.amount} onChange={(e) => f.setData('amount', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.col.method')}>
                        <Select value={f.data.method} onChange={(e) => f.setData('method', e.target.value)}>
                            {STAFF_METHODS.map((m) => (
                                <option key={m} value={m}>
                                    {t(`fin.method.${m}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.col.reference')} hint={f.data.method !== 'cash' && f.data.method !== 'other' ? t('fin.record.referenceHint') : undefined}>
                        <Input value={f.data.reference} maxLength={100} onChange={(e) => f.setData('reference', e.target.value)} required={['wave', 'bank_transfer', 'card'].includes(f.data.method)} />
                    </Field>
                    <Field label={t('fin.record.paidOn')}>
                        <Input type="date" max={todayIso()} value={f.data.paid_on} onChange={(e) => f.setData('paid_on', e.target.value)} />
                    </Field>
                    <Field label={t('fin.record.payer')} className="sm:col-span-2">
                        <Input value={f.data.payer_name} maxLength={150} onChange={(e) => f.setData('payer_name', e.target.value)} />
                    </Field>
                </div>
                <Field label={t('acad.common.notes')}>
                    <Textarea rows={2} maxLength={500} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
                </Field>
                <button type="submit" className="btn-teal" disabled={f.processing}>
                    {t('fin.record.submit')}
                </button>
            </form>
        </Card>
    );
}

function SubmitPayment({ invoice, methods }) {
    const { t } = useLocale();
    const f = useForm({ amount: Math.max(0, invoice.balance - invoice.pending), method: methods[0]?.method ?? 'wave', reference: '', payer_name: '', notes: '', evidence: null });
    const available = FAMILY_METHODS.filter((m) => methods.some((x) => x.method === m));
    const chosen = methods.filter((x) => x.method === f.data.method);

    return (
        <Card title={t('fin.submit.title')}>
            <p className="mb-3 text-sm text-slate-600">{t('fin.submit.intro')}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(route('fees.submissions.store', invoice.id), { forceFormData: true, preserveScroll: true, onSuccess: () => f.reset('reference', 'notes', 'evidence') });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={f.errors} />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label={t('fin.submit.method')}>
                        <Select value={f.data.method} onChange={(e) => f.setData('method', e.target.value)}>
                            {available.map((m) => (
                                <option key={m} value={m}>
                                    {t(`fin.method.${m}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label={t('fin.col.amount')}>
                        <Input type="number" min="1" step="1" value={f.data.amount} onChange={(e) => f.setData('amount', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.submit.reference')} hint={t('fin.submit.referenceHint')}>
                        <Input value={f.data.reference} maxLength={100} onChange={(e) => f.setData('reference', e.target.value)} required />
                    </Field>
                    <Field label={t('fin.record.payer')}>
                        <Input value={f.data.payer_name} maxLength={150} onChange={(e) => f.setData('payer_name', e.target.value)} />
                    </Field>
                </div>
                {chosen.map((m) => (
                    <div key={m.id} className="rounded-lg bg-slate-50 p-3 text-sm">
                        <p className="font-medium text-navy">{m.label}</p>
                        {m.account_name ? <p>{m.account_name}</p> : null}
                        {m.bank_name ? <p>{m.bank_name}</p> : null}
                        {m.account_number ? (
                            <p dir="ltr" className="font-mono">
                                {m.account_number}
                            </p>
                        ) : null}
                        {m.instructions ? <p className="mt-1 whitespace-pre-line text-slate-600">{m.instructions}</p> : null}
                    </div>
                ))}
                <Field label={t('fin.submit.evidence')} hint={t('fin.submit.evidenceHint')}>
                    <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => f.setData('evidence', e.target.files[0] ?? null)} className="block w-full text-sm" />
                </Field>
                <Field label={t('acad.common.notes')}>
                    <Textarea rows={2} maxLength={500} value={f.data.notes} onChange={(e) => f.setData('notes', e.target.value)} />
                </Field>
                <button type="submit" className="btn-teal" disabled={f.processing}>
                    {t('fin.submit.submit')}
                </button>
            </form>
        </Card>
    );
}

function Installments({ invoice, installments, rules, canManage }) {
    const { t, locale } = useLocale();
    const f = useForm({ count: 2, first_due_on: '' });
    f.transform((d) => ({ ...d, count: Number(d.count) }));

    return (
        <Card title={t('fin.inst.title')}>
            {installments.length > 0 ? (
                <DataTable
                    columns={[
                        { key: 'number', label: '#' },
                        { key: 'due_on', label: t('fin.col.due'), render: (r) => fmtDay(r.due_on, locale) },
                        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
                        { key: 'amount_paid', label: t('fin.col.paid'), render: (r) => money(r.amount_paid) },
                    ]}
                    rows={installments}
                />
            ) : (
                <p className="text-sm text-slate-500">{t('fin.inst.none')}</p>
            )}
            {canManage && installments.length > 0 && invoice.amount_paid === 0 ? (
                <button type="button" className="btn-outline mt-3" onClick={() => router.delete(route('fees.invoices.installments.clear', invoice.id), { preserveScroll: true })}>
                    {t('fin.inst.clear')}
                </button>
            ) : null}
            {canManage && rules.installments && invoice.amount_paid === 0 && isOpenInvoice(invoice.raw_status) ? (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.post(route('fees.invoices.installments', invoice.id), { preserveScroll: true });
                    }}
                    className="mt-4 space-y-3 border-t border-slate-100 pt-4"
                >
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('fin.inst.count')} hint={t('fin.inst.max', { n: rules.installments_max })}>
                            <Input type="number" min="2" max={rules.installments_max} value={f.data.count} onChange={(e) => f.setData('count', e.target.value)} required />
                        </Field>
                        <Field label={t('fin.inst.firstDue')}>
                            <Input type="date" min={todayIso()} value={f.data.first_due_on} onChange={(e) => f.setData('first_due_on', e.target.value)} required />
                        </Field>
                    </div>
                    <button type="submit" className="btn-navy" disabled={f.processing}>
                        {installments.length > 0 ? t('fin.inst.replace') : t('fin.inst.set')}
                    </button>
                </form>
            ) : null}
        </Card>
    );
}

export default function InvoiceShow(props) {
    const { invoice, items = [], installments = [], payments = [], methods = [], rules = {}, can = {} } = props;
    const { t, locale } = useLocale();
    const open = isOpenInvoice(invoice.raw_status);

    const itemColumns = [
        { key: 'description', label: t('fin.manual.description'), render: (r) => (r.kind === 'fee' ? r.description : enumLabel(t, 'fin.kind', r.kind)) },
        { key: 'kind', label: t('fin.col.kind'), render: (r) => enumLabel(t, 'fin.kind', r.kind) },
        { key: 'category', label: t('fin.manual.category') },
        {
            key: 'amount',
            label: t('fin.col.amount'),
            cellClassName: 'text-end',
            render: (r) => (r.kind === 'fee' || r.kind === 'manual' ? money(r.amount) : `− ${money(r.amount)}`),
        },
    ];

    const paymentColumns = [
        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) },
        { key: 'receipt_number', label: t('fin.col.receipt') },
        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
        ...(can.staff ? [{ key: 'reference', label: t('fin.col.reference') }] : []),
        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <div className="flex flex-wrap items-start gap-2 whitespace-normal">
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
                    {r.status === 'pending' && can.verify ? (
                        <>
                            <button type="button" className="btn-teal" onClick={() => router.post(route('fees.payments.confirm', r.id), {}, { preserveScroll: true })}>
                                {t('fin.action.confirm')}
                            </button>
                            <ReasonAction url={route('fees.payments.reject', r.id)} fieldName="note" label={t('fin.action.reject')} tone="danger" />
                        </>
                    ) : null}
                    {r.status === 'confirmed' && can.approve && !r.is_reconciled ? (
                        <ReasonAction url={route('fees.payments.void', r.id)} label={t('fin.action.void')} tone="danger" />
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <FinanceLayout
            title={invoice.invoice_number}
            subtitle={invoice.student}
            current={can.staff ? 'invoices' : 'overview'}
            shell={props}
            actions={
                <>
                    {can.staff ? (
                        <Link href={route('fees.statements.show', invoice.student_id)} className="btn-outline">
                            {t('fin.action.statement')}
                        </Link>
                    ) : null}
                    <Link href={route(can.staff ? 'fees.invoices.index' : 'fees.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Card title={t('fin.show.summary')} className="lg:col-span-1">
                    <dl className="divide-y divide-slate-100">
                        <Row label={t('fin.col.status')}>
                            <StatusBadge status={invoice.status} label={enumLabel(t, 'fin.status', invoice.status)} />
                        </Row>
                        <Row label={t('fin.col.student')}>
                            {invoice.student} ({invoice.student_number})
                        </Row>
                        {invoice.class ? <Row label={t('fin.col.class')}>{invoice.class}</Row> : null}
                        {invoice.year ? <Row label={t('fin.show.year')}>{invoice.year}{invoice.term ? ` · ${invoice.term}` : ''}</Row> : null}
                        <Row label={t('fin.show.issued')}>{fmtDay(invoice.issued_at, locale) || '—'}</Row>
                        <Row label={t('fin.col.due')}>{fmtDay(invoice.due_on, locale) || '—'}</Row>
                        <Row label={t('fin.show.subtotal')}>{money(invoice.subtotal)}</Row>
                        {invoice.adjustments_total > 0 ? <Row label={t('fin.show.adjustments')}>− {money(invoice.adjustments_total)}</Row> : null}
                        <Row label={t('fin.col.total')}>{money(invoice.total_amount)}</Row>
                        <Row label={t('fin.col.paid')}>{money(invoice.amount_paid)}</Row>
                        {invoice.pending > 0 ? <Row label={t('fin.show.pending')}>{money(invoice.pending)}</Row> : null}
                        <Row label={t('fin.col.balance')}>{money(invoice.balance)}</Row>
                    </dl>
                    {invoice.notes ? <p className="mt-3 whitespace-pre-line text-sm text-slate-600">{invoice.notes}</p> : null}
                    {invoice.cancel_reason ? (
                        <p className="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">
                            {t('fin.show.cancelReason')}: {invoice.cancel_reason}
                        </p>
                    ) : null}
                    {can.approve && invoice.raw_status !== 'cancelled' ? (
                        <div className="mt-4">
                            <ReasonAction url={route('fees.invoices.cancel', invoice.id)} label={t('fin.action.cancelInvoice')} tone="danger" />
                        </div>
                    ) : null}
                </Card>

                <div className="space-y-6 lg:col-span-2">
                    <Card title={t('fin.show.lines')}>
                        <DataTable columns={itemColumns} rows={items} emptyMessage={t('fin.show.noLines')} />
                    </Card>
                    <Card title={t('fin.show.payments')}>
                        <DataTable columns={paymentColumns} rows={payments} emptyMessage={t('fin.show.noPayments')} />
                    </Card>
                </div>
            </div>

            {open ? (
                <div className="mt-6 grid gap-6 lg:grid-cols-2">
                    {can.record ? <RecordPayment invoice={invoice} /> : null}
                    {!can.staff && rules.submissions && methods.length > 0 && invoice.balance - invoice.pending > 0 ? <SubmitPayment invoice={invoice} methods={methods} /> : null}
                    {(can.manage && rules.installments) || installments.length > 0 ? <Installments invoice={invoice} installments={installments} rules={rules} canManage={!!can.manage} /> : null}
                </div>
            ) : installments.length > 0 ? (
                <div className="mt-6">
                    <Installments invoice={invoice} installments={installments} rules={rules} canManage={!!can.manage} />
                </div>
            ) : null}
        </FinanceLayout>
    );
}
