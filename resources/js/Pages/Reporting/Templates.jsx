import { Card, Field, Input, Textarea } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { useForm } from '@inertiajs/react';
import { reportingShell } from './shell';

const FLAG_KEYS = {
    show_logo: 'showLogo',
    show_photo: 'showPhoto',
    show_scale: 'showScale',
    show_attendance: 'showAttendance',
    show_comments: 'showComments',
    show_signatures: 'showSignatures',
    show_stamp: 'showStamp',
    show_position: 'showPosition',
};

function TemplateForm({ template }) {
    const { t } = useLocale();
    const { data, setData, put, processing } = useForm({
        name: template.name,
        is_default: !!template.is_default,
        config: {
            title: template.config?.title ?? '',
            footer: template.config?.footer ?? '',
            show_logo: !!template.config?.show_logo,
            show_photo: !!template.config?.show_photo,
            show_scale: !!template.config?.show_scale,
            show_attendance: !!template.config?.show_attendance,
            show_comments: !!template.config?.show_comments,
            show_signatures: !!template.config?.show_signatures,
            show_stamp: !!template.config?.show_stamp,
            show_position: !!template.config?.show_position,
        },
    });

    const flags = ['show_logo', 'show_photo', 'show_scale', 'show_attendance', 'show_comments', 'show_signatures', 'show_stamp', 'show_position'];

    return (
        <Card title={`${enumLabel(t, 'rpt.tpl.band', template.program_band)} — ${template.name}`} className="mb-4">
            <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    put(route('reporting.templates.update', template.id), { preserveScroll: true });
                }}
            >
                <Field label={t('rpt.tpl.reportTitle')} className="sm:col-span-2">
                    <Input value={data.config.title} onChange={(e) => setData('config', { ...data.config, title: e.target.value })} required />
                </Field>
                <Field label={t('rec.col.type')}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                </Field>
                <Field label={t('rpt.tpl.footer')} className="sm:col-span-2">
                    <Textarea value={data.config.footer} onChange={(e) => setData('config', { ...data.config, footer: e.target.value })} rows={2} />
                </Field>
                <div className="grid gap-2 sm:col-span-2 sm:grid-cols-2">
                    {flags.map((flag) => (
                        <label key={flag} className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={!!data.config[flag]} onChange={(e) => setData('config', { ...data.config, [flag]: e.target.checked })} />
                            {t(`rpt.tpl.${FLAG_KEYS[flag]}`)}
                        </label>
                    ))}
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={!!data.is_default} onChange={(e) => setData('is_default', e.target.checked)} />
                        {t('rpt.tpl.default')}
                    </label>
                </div>
                <div>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('rpt.tpl.save')}
                    </button>
                </div>
            </form>
        </Card>
    );
}

export default function Templates({ nav, user, school, academicYear, tabs = [], templates = [] }) {
    const { t } = useLocale();

    return (
        <ModuleLayout ns="rpt" title={t('rpt.tpl.title')} subtitle={t('rpt.tpl.subtitle')} tabs={tabs} current="templates" shell={reportingShell({ nav, user, school, academicYear })}>
            {templates.map((tpl) => (
                <TemplateForm key={tpl.id} template={tpl} />
            ))}
        </ModuleLayout>
    );
}
