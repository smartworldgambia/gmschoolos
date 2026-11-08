import DataTable from '@/Components/SchoolOS/DataTable';
import HrLayout from '@/Components/SchoolOS/HrLayout';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay } from '@/lib/comm';
import { money } from '@/lib/fees';

export default function MyPayslips(props) {
    const { linked = false, payslips = [] } = props;
    const { t, locale } = useLocale();

    const columns = [
        { key: 'period_label', label: t('hr.col.period') },
        { key: 'gross', label: t('hr.col.gross'), render: (r) => money(r.gross) },
        { key: 'deductions', label: t('hr.col.deductions'), render: (r) => money(r.deductions) },
        { key: 'net', label: t('hr.col.net'), render: (r) => <strong>{money(r.net)}</strong> },
        { key: 'status', label: t('hr.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'hr.status', r.status)} /> },
        { key: 'paid_at', label: t('hr.payroll.paidOn'), render: (r) => (r.paid_at ? fmtDay(r.paid_at, locale) : '—') },
        {
            key: 'actions',
            label: '',
            render: (r) => (
                <a href={route('hr.payslips.show', r.id)} target="_blank" rel="noreferrer" className="btn-outline">
                    {t('hr.payslip.open')}
                </a>
            ),
        },
    ];

    return (
        <HrLayout title={t('hr.payslip.title')} subtitle={t('hr.payslip.subtitle')} current="myPayslips" shell={props}>
            {!linked ? <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{t('hr.my.notLinked')}</p> : <DataTable columns={columns} rows={payslips} emptyMessage={t('hr.payslip.empty')} />}
        </HrLayout>
    );
}
