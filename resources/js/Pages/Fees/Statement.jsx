import DataTable from '@/Components/SchoolOS/DataTable';
import FinanceLayout from '@/Components/SchoolOS/FinanceLayout';
import { Card, Stat } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';
import { Link } from '@inertiajs/react';

export default function Statement(props) {
    const { statement } = props;
    const { t, locale } = useLocale();

    return (
        <FinanceLayout
            title={t('fin.statement.title')}
            subtitle={`${statement.student.name} (${statement.student.student_number})`}
            current="overview"
            shell={props}
            actions={
                <Link href={route('fees.index')} className="btn-outline">
                    {t('acad.common.back')}
                </Link>
            }
        >
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat label={t('fin.stat.billed')} value={money(statement.billed)} />
                <Stat label={t('fin.stat.collected')} value={money(statement.paid)} tone="green" />
                <Stat label={t('fin.col.balance')} value={money(statement.balance)} tone={statement.balance > 0 ? 'red' : 'navy'} />
            </div>

            <Card title={t('fin.index.invoices')} className="mb-6">
                <DataTable
                    columns={[
                        { key: 'number', label: t('fin.col.invoice') },
                        { key: 'issued_at', label: t('fin.show.issued'), render: (r) => fmtDay(r.issued_at, locale) },
                        { key: 'total', label: t('fin.col.total'), render: (r) => money(r.total) },
                        { key: 'paid', label: t('fin.col.paid'), render: (r) => money(r.paid) },
                        { key: 'status', label: t('fin.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'fin.status', r.status)} /> },
                    ]}
                    rows={statement.invoices}
                    keyField="number"
                    emptyMessage={t('fin.index.noInvoices')}
                />
            </Card>

            <Card title={t('fin.index.recentPayments')}>
                <DataTable
                    columns={[
                        { key: 'receipt', label: t('fin.col.receipt') },
                        { key: 'paid_at', label: t('fin.col.date'), render: (r) => fmtDay(r.paid_at, locale) },
                        { key: 'amount', label: t('fin.col.amount'), render: (r) => money(r.amount) },
                        { key: 'method', label: t('fin.col.method'), render: (r) => enumLabel(t, 'fin.method', r.method) },
                    ]}
                    rows={statement.payments}
                    keyField="receipt"
                    emptyMessage={t('fin.index.noPayments')}
                />
            </Card>
        </FinanceLayout>
    );
}
