import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { router, useForm } from '@inertiajs/react';

export default function Suppliers(props) {
    const { suppliers = [] } = props;
    const { t } = useLocale();
    const f = useForm({ name: '', contact_person: '', phone: '', email: '', address: '' });

    return (
        <ProcurementLayout title={t('proc.sup.title')} subtitle={t('proc.sup.subtitle')} current="suppliers" shell={props}>
            <DataTable
                columns={[
                    { key: 'name', label: t('inv.col.name') },
                    { key: 'contact_person', label: t('proc.sup.contact') },
                    { key: 'phone', label: t('proc.sup.phone') },
                    { key: 'email', label: t('proc.sup.email') },
                    { key: 'is_active', label: t('proc.col.status'), render: (r) => <StatusBadge status={r.is_active ? 'active' : 'inactive'} label={t(r.is_active ? 'inv.status.active' : 'inv.status.inactive')} /> },
                    {
                        key: 'actions',
                        label: '',
                        render: (r) => (
                            <button type="button" className="btn-outline" onClick={() => router.put(route('procurement.suppliers.update', r.id), { name: r.name, contact_person: r.contact_person, phone: r.phone, email: r.email, address: r.address, is_active: !r.is_active }, { preserveScroll: true })}>
                                {r.is_active ? t('hr.component.deactivate') : t('hr.component.activate')}
                            </button>
                        ),
                    },
                ]}
                rows={suppliers}
                emptyMessage={t('proc.sup.empty')}
            />
            <Card title={t('proc.sup.add')} className="mt-8 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); f.post(route('procurement.suppliers.store'), { preserveScroll: true, onSuccess: () => f.reset() }); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('inv.col.name')}><Input value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} required /></Field>
                        <Field label={t('proc.sup.contact')}><Input value={f.data.contact_person} onChange={(e) => f.setData('contact_person', e.target.value)} /></Field>
                        <Field label={t('proc.sup.phone')}><Input value={f.data.phone} onChange={(e) => f.setData('phone', e.target.value)} /></Field>
                        <Field label={t('proc.sup.email')}><Input type="email" value={f.data.email} onChange={(e) => f.setData('email', e.target.value)} /></Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('proc.sup.save')}</button>
                </form>
            </Card>
        </ProcurementLayout>
    );
}
