import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input } from '@/Components/SchoolOS/Form';
import EventsLayout from '@/Components/SchoolOS/EventsLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

export default function Venues(props) {
    const { venues = [] } = props;
    const { t } = useLocale();
    const f = useForm({ name: '', capacity: '', location: '' });
    f.transform((d) => ({ ...d, capacity: d.capacity === '' ? null : Number(d.capacity) }));

    return (
        <EventsLayout title={t('ev.venues.title')} subtitle={t('ev.venues.subtitle')} current="venues" canManage shell={props}>
            <DataTable columns={[{ key: 'name', label: t('inv.col.name') }, { key: 'capacity', label: t('tr.capacity') }, { key: 'location', label: t('inv.col.location') }]} rows={venues} emptyMessage={t('ev.venues.empty')} />
            <Card title={t('ev.venues.add')} className="mt-8 max-w-3xl">
                <form onSubmit={(e) => { e.preventDefault(); f.post(route('events.venues.store'), { preserveScroll: true, onSuccess: () => f.reset() }); }} className="grid gap-3 sm:grid-cols-3">
                    <ErrorSummary errors={f.errors} />
                    <Field label={t('inv.col.name')}><Input value={f.data.name} onChange={(e) => f.setData('name', e.target.value)} required /></Field>
                    <Field label={t('tr.capacity')}><Input type="number" min="1" value={f.data.capacity} onChange={(e) => f.setData('capacity', e.target.value)} /></Field>
                    <Field label={t('inv.col.location')}><Input value={f.data.location} onChange={(e) => f.setData('location', e.target.value)} /></Field>
                    <button type="submit" className="btn-teal self-end" disabled={f.processing}>{t('ev.venues.save')}</button>
                </form>
            </Card>
        </EventsLayout>
    );
}
