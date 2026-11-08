import { Card, ErrorSummary, Field, Select } from '@/Components/SchoolOS/Form';
import EventsLayout from '@/Components/SchoolOS/EventsLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Setup(props) {
    const { settings = {} } = props;
    const { t } = useLocale();
    const f = useForm({ announce_on_publish: Boolean(settings.announce_on_publish) });

    return (
        <EventsLayout title={t('ev.setup.title')} subtitle={t('ev.setup.subtitle')} current="setup" canManage shell={props}>
            <Card title={t('ev.setup.rules')} className="max-w-xl">
                <form onSubmit={(e) => { e.preventDefault(); f.put(route('events.setup.save'), { preserveScroll: true }); }} className="space-y-3">
                    <ErrorSummary errors={f.errors} />
                    <Field label={t('ev.setup.announce')} hint={t('ev.setup.announceHint')}>
                        <Select value={f.data.announce_on_publish ? '1' : '0'} onChange={(e) => f.setData('announce_on_publish', e.target.value === '1')}>
                            <option value="0">{t('acad.common.no')}</option>
                            <option value="1">{t('acad.common.yes')}</option>
                        </Select>
                    </Field>
                    <button type="submit" className="btn-teal" disabled={f.processing}>{t('acad.common.save')}</button>
                </form>
            </Card>
        </EventsLayout>
    );
}
