import { Card, ErrorSummary, Field, Select } from '@/Components/SchoolOS/Form';
import ProcurementLayout from '@/Components/SchoolOS/ProcurementLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Setup(props) {
    const { settings = {} } = props;
    const { t } = useLocale();
    const f = useForm({ self_approval: Boolean(settings.self_approval) });

    return (
        <ProcurementLayout title={t('proc.setup.title')} subtitle={t('proc.setup.subtitle')} current="setup" shell={props}>
            <Card title={t('proc.setup.rules')} className="max-w-xl">
                <form onSubmit={(e) => { e.preventDefault(); f.put(route('procurement.setup.save'), { preserveScroll: true }); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <Field label={t('proc.setup.self')} hint={t('proc.setup.selfHint')}>
                        <Select value={f.data.self_approval ? '1' : '0'} onChange={(e) => f.setData('self_approval', e.target.value === '1')}>
                            <option value="0">{t('hr.setup.notAllowed')}</option>
                            <option value="1">{t('hr.setup.allowed')}</option>
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('acad.common.save')}</button>
                </form>
            </Card>
        </ProcurementLayout>
    );
}
