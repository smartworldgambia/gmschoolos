import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import LibraryLayout from '@/Components/SchoolOS/LibraryLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Setup(props) {
    const { rules = {} } = props;
    const { t } = useLocale();

    const f = useForm({
        loan_days: rules.loan_days ?? 14,
        fine_per_day: rules.fine_per_day ?? 0,
        max_loans_student: rules.max_loans_student ?? 0,
        max_loans_staff: rules.max_loans_staff ?? 0,
        max_renewals: rules.max_renewals ?? 0,
        block_if_overdue: Boolean(rules.block_if_overdue),
    });
    f.transform((d) => ({
        loan_days: Number(d.loan_days),
        fine_per_day: Number(d.fine_per_day),
        max_loans_student: Number(d.max_loans_student),
        max_loans_staff: Number(d.max_loans_staff),
        max_renewals: Number(d.max_renewals),
        block_if_overdue: d.block_if_overdue,
    }));
    const num = (key, min = 0) => (
        <Input type="number" min={min} step="1" value={f.data[key]} onChange={(e) => f.setData(key, e.target.value)} required />
    );

    return (
        <LibraryLayout title={t('lib.setup.title')} subtitle={t('lib.setup.subtitle')} current="setup" shell={props}>
            <Card title={t('lib.setup.rules')} className="max-w-3xl">
                <p className="mb-3 text-sm text-slate-600">{t('lib.setup.note')}</p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.put(route('library.setup.save'), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={f.errors} />
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label={t('lib.setup.loanDays')}>{num('loan_days', 1)}</Field>
                        <Field label={t('lib.setup.finePerDay')} hint={t('lib.setup.finePerDayHint')}>
                            {num('fine_per_day')}
                        </Field>
                        <Field label={t('lib.setup.maxStudent')} hint={t('lib.setup.zeroHint')}>
                            {num('max_loans_student')}
                        </Field>
                        <Field label={t('lib.setup.maxStaff')} hint={t('lib.setup.zeroHint')}>
                            {num('max_loans_staff')}
                        </Field>
                        <Field label={t('lib.setup.maxRenewals')}>{num('max_renewals')}</Field>
                        <Field label={t('lib.setup.blockOverdue')} hint={t('lib.setup.blockOverdueHint')}>
                            <Select value={f.data.block_if_overdue ? '1' : '0'} onChange={(e) => f.setData('block_if_overdue', e.target.value === '1')}>
                                <option value="1">{t('acad.common.yes')}</option>
                                <option value="0">{t('acad.common.no')}</option>
                            </Select>
                        </Field>
                    </div>
                    <button type="submit" className="btn-teal" disabled={f.processing}>
                        {t('acad.common.save')}
                    </button>
                </form>
            </Card>
        </LibraryLayout>
    );
}
