import DataTable from '@/Components/SchoolOS/DataTable';
import { Stat } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { money } from '@/lib/fees';

export default function Reports(props) {
    const { stats = {}, bySupplier = [] } = props;
    const { t } = useLocale();

    return (
        <ProcurementLayout title={t('proc.rep.title')} subtitle={t('proc.rep.subtitle')} current="reports" shell={props}>
            <div className="mb-6 grid gap-4 sm:grid-cols-4">
                <Stat label={t('proc.rep.openReq')} value={stats.open_requests} />
                <Stat label={t('proc.rep.openOrd')} value={stats.open_orders} />
                <Stat label={t('proc.rep.unpaid')} value={money(stats.unpaid)} />
                <Stat label={t('proc.rep.paidYtd')} value={money(stats.paid_ytd)} tone="green" />
            </div>
            <DataTable columns={[{ key: 'supplier', label: t('proc.col.supplier') }, { key: 'total', label: t('proc.rep.paid'), render: (r) => money(r.total) }]} rows={bySupplier} emptyMessage={t('proc.rep.empty')} />
        </ProcurementLayout>
    );
}
