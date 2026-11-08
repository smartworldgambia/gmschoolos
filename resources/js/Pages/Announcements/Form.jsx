import { AnnouncementsHeader } from '@/Components/Comm/AnnouncementsChrome';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Form({ announcement, audiences = [], classes = [], smsReady = false, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const editing = Boolean(announcement);
    const form = useForm({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        audience: announcement?.audience ?? audiences[0] ?? 'classes',
        class_ids: announcement?.class_ids ?? [],
        include_students: announcement?.include_students ?? true,
        include_guardians: announcement?.include_guardians ?? true,
        channels: announcement?.channels ?? [],
        publish_at: announcement?.publish_at ?? '',
        expires_at: announcement?.expires_at ?? '',
        action: 'publish',
    });

    const submit = (action) => (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, action, publish_at: action === 'schedule' ? d.publish_at : '' }));
        if (editing) {
            form.put(route('announcements.update', announcement.id), { preserveScroll: true });
        } else {
            form.post(route('announcements.store'), { preserveScroll: true });
        }
    };

    const toggle = (key, value) => form.setData(key, form.data[key].includes(value) ? form.data[key].filter((x) => x !== value) : [...form.data[key], value]);

    return (
        <AppShell title={editing ? t('ann.edit') : t('ann.new')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={editing ? t('ann.edit') : t('ann.new')} />
            <AnnouncementsHeader
                title={editing ? t('ann.edit') : t('ann.new')}
                subtitle={t('ann.formSubtitle')}
                actions={
                    <Link href={route('announcements.index')} className="btn-outline">
                        {t('ann.back')}
                    </Link>
                }
            />

            <Card className="max-w-3xl">
                <form className="space-y-4" onSubmit={submit('publish')}>
                    <ErrorSummary errors={form.errors} />
                    <Field label={t('ann.field.title')}>
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                    </Field>
                    <Field label={t('ann.field.body')}>
                        <Textarea rows={7} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required maxLength={5000} />
                    </Field>

                    <Field label={t('ann.field.audience')}>
                        <Select value={form.data.audience} onChange={(e) => form.setData('audience', e.target.value)}>
                            {audiences.map((a) => (
                                <option key={a} value={a}>
                                    {t(`ann.audience.${a}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {form.data.audience === 'classes' ? (
                        <>
                            <Field label={t('ann.field.classes')} hint={classes.length === 0 ? t('ann.noClasses') : undefined}>
                                <div className="grid max-h-48 gap-1 overflow-y-auto rounded-md border border-slate-200 p-2 sm:grid-cols-2">
                                    {classes.map((c) => (
                                        <label key={c.id} className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" className="rounded border-gray-300" checked={form.data.class_ids.includes(c.id)} onChange={() => toggle('class_ids', c.id)} />
                                            {c.name}
                                        </label>
                                    ))}
                                </div>
                            </Field>
                            <div className="flex flex-wrap gap-6 text-sm">
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" className="rounded border-gray-300" checked={form.data.include_students} onChange={(e) => form.setData('include_students', e.target.checked)} />
                                    {t('ann.field.students')}
                                </label>
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" className="rounded border-gray-300" checked={form.data.include_guardians} onChange={(e) => form.setData('include_guardians', e.target.checked)} />
                                    {t('ann.field.guardians')}
                                </label>
                            </div>
                        </>
                    ) : null}

                    <Field label={t('ann.field.channels')} hint={t('ann.field.channelsHint')}>
                        <div className="flex flex-wrap gap-6 text-sm">
                            <label className="flex items-center gap-2 text-slate-500">
                                <input type="checkbox" checked disabled />
                                {t('ann.channel.in_app')}
                            </label>
                            <label className="flex items-center gap-2">
                                <input type="checkbox" className="rounded border-gray-300" checked={form.data.channels.includes('email')} onChange={() => toggle('channels', 'email')} />
                                {t('ann.channel.email')}
                            </label>
                            <label className="flex items-center gap-2">
                                <input type="checkbox" className="rounded border-gray-300" checked={form.data.channels.includes('sms')} onChange={() => toggle('channels', 'sms')} />
                                {t('ann.channel.sms')}
                            </label>
                        </div>
                        {form.data.channels.includes('sms') && !smsReady ? <p className="mt-1 text-xs text-amber-700">{t('ann.smsOff')}</p> : null}
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('ann.field.publishAt')} hint={t('ann.field.publishAtHint')}>
                            <Input type="datetime-local" value={form.data.publish_at} onChange={(e) => form.setData('publish_at', e.target.value)} />
                        </Field>
                        <Field label={t('ann.field.expiresAt')} hint={t('ann.field.expiresAtHint')}>
                            <Input type="datetime-local" value={form.data.expires_at} onChange={(e) => form.setData('expires_at', e.target.value)} />
                        </Field>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('ann.publishNow')}
                        </button>
                        <button type="button" className="btn-navy" disabled={form.processing || !form.data.publish_at} onClick={submit('schedule')}>
                            {t('ann.schedule')}
                        </button>
                        <button type="button" className="btn-outline" disabled={form.processing} onClick={submit('draft')}>
                            {t('ann.saveDraft')}
                        </button>
                    </div>
                </form>
            </Card>
        </AppShell>
    );
}
