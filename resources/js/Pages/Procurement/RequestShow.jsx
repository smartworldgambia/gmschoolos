import DataTable from '@/Components/SchoolOS/DataTable';
import { Card } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import ReasonAction from '@/Components/SchoolOS/ReasonAction';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { money } from '@/lib/fees';
import { Link, router, useForm } from '@inertiajs/react';

export default function RequestShow(props) {
    const { request: pr, can = {}, suppliers = [] } = props;
    const { t } = useLocale();
    const order = useForm({ supplier_id: '', purchase_request_id: pr.id, items: [] });

    return (
        <ProcurementLayout title={pr.number} subtitle={pr.title} current="requests" shell={props} actions={<Link href={route('procurement.requests.index')} className="btn-outline">{t('acad.common.back')}</Link>}>
            <p className="mb-4"><StatusBadge status={pr.status} label={enumLabel(t, 'proc.status', pr.status)} /> · {pr.requester} · {money(pr.estimated_total)}</p>
            {pr.justification ? <p className="mb-4 text-sm text-slate-600">{pr.justification}</p> : null}
            <DataTable
                columns={[
                    { key: 'description', label: t('proc.col.item') },
                    { key: 'quantity', label: t('proc.col.qty') },
                    { key: 'estimated_unit_cost', label: t('proc.col.est'), render: (r) => money(r.estimated_unit_cost) },
                    { key: 'item', label: t('inv.col.sku') },
                ]}
                rows={pr.items}
                emptyMessage={t('proc.noLines')}
            />
            <div className="mt-4 flex flex-wrap gap-2">
                {pr.status === 'draft' && (pr.own || can.manage) ? (
                    <button type="button" className="btn-teal" onClick={() => router.post(route('procurement.requests.submit', pr.id))}>{t('proc.submit')}</button>
                ) : null}
                {pr.status === 'submitted' && can.approve ? (
                    <>
                        <ReasonAction url={route('procurement.requests.decide', pr.id)} fieldName="note" fields={{ decision: 'approved' }} required={false} label={t('acad.common.approve')} tone="teal" />
                        <ReasonAction url={route('procurement.requests.decide', pr.id)} fieldName="note" fields={{ decision: 'rejected' }} label={t('acad.common.reject')} tone="danger" />
                    </>
                ) : null}
                {['draft', 'submitted', 'approved'].includes(pr.status) && can.manage ? (
                    <button type="button" className="btn-outline !text-red-700" onClick={() => window.confirm(t('proc.cancelConfirm')) && router.post(route('procurement.requests.cancel', pr.id))}>{t('acad.common.cancel')}</button>
                ) : null}
            </div>
            {pr.status === 'approved' && can.manage ? (
                <Card title={t('proc.order.fromRequest')} className="mt-8 max-w-xl">
                    <form onSubmit={(e) => { e.preventDefault(); order.post(route('procurement.orders.store')); }} className="space-y-3">
                        <select className="block w-full rounded-md border-gray-300 text-sm" value={order.data.supplier_id} onChange={(e) => order.setData('supplier_id', e.target.value)} required>
                            <option value="">{t('proc.chooseSupplier')}</option>
                            {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        <button type="submit" className="btn-teal" disabled={order.processing}>{t('proc.order.create')}</button>
                    </form>
                </Card>
            ) : null}
            {pr.decision_note ? <p className="mt-4 text-sm text-slate-600">{pr.decider}: {pr.decision_note}</p> : null}
        </ProcurementLayout>
    );
}
