import InputError from '@/Components/InputError';
import { Card, ErrorSummary, Field, Input } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Settings({ signatories = [], reminders = {}, hasStamp = false, levels = [], estimateText, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const main = useForm({
        signatories: [0, 1, 2].map((i) => signatories[i] ?? { name: '', title: '' }),
        reminders: { enabled: !!reminders.enabled, interval_days: reminders.interval_days ?? 14, max: reminders.max ?? 5 },
    });
    const stamp = useForm({ stamp: null });
    const lv = useForm({ levels: levels.map((l) => ({ id: l.id, name: l.name, grade_number: l.grade_number ?? '' })) });

    return (
        <AppShell title={t('rec.settingsTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('rec.settingsTitle')} />
            <PageHeader
                title={t('rec.settingsTitle')}
                subtitle={t('rec.settingsSubtitle')}
                actions={
                    <Link href={route('records.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />

            <form
                className="mb-6 space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    main.put(route('records.settings.update'), { preserveScroll: true });
                }}
            >
                <ErrorSummary errors={main.errors} />
                <Card title={t('rec.signatories')}>
                    <p className="mb-3 text-sm text-slate-600">{t('rec.signatoriesHelp')}</p>
                    <div className="space-y-2">
                        {main.data.signatories.map((s, i) => (
                            <div key={i} className="grid gap-2 sm:grid-cols-2">
                                <Input
                                    placeholder={t('rec.signatoryName')}
                                    value={s.name ?? ''}
                                    onChange={(e) => main.setData('signatories', main.data.signatories.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))}
                                    maxLength={120}
                                />
                                <Input
                                    placeholder={t('rec.signatoryTitle')}
                                    value={s.title ?? ''}
                                    onChange={(e) => main.setData('signatories', main.data.signatories.map((x, j) => (j === i ? { ...x, title: e.target.value } : x)))}
                                    maxLength={120}
                                />
                            </div>
                        ))}
                    </div>
                </Card>

                <Card title={t('rec.reminders')}>
                    <p className="mb-3 text-sm text-slate-600">{t('col.estimate')}</p>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <label className="flex items-center gap-2 text-sm sm:pt-6">
                            <input type="checkbox" className="rounded border-gray-300" checked={main.data.reminders.enabled} onChange={(e) => main.setData('reminders', { ...main.data.reminders, enabled: e.target.checked })} />
                            {t('rec.remindersEnabled')}
                        </label>
                        <Field label={t('rec.remindEvery')}>
                            <Input type="number" min="1" max="365" value={main.data.reminders.interval_days} onChange={(e) => main.setData('reminders', { ...main.data.reminders, interval_days: e.target.value })} />
                        </Field>
                        <Field label={t('rec.remindMax')}>
                            <Input type="number" min="1" max="50" value={main.data.reminders.max} onChange={(e) => main.setData('reminders', { ...main.data.reminders, max: e.target.value })} />
                        </Field>
                    </div>
                </Card>
                <button type="submit" className="btn-teal" disabled={main.processing}>
                    {t('acad.common.save')}
                </button>
            </form>

            <Card title={t('rec.stamp')} className="mb-6">
                <p className="mb-3 text-sm text-slate-600">{hasStamp ? t('rec.stampSet') : t('rec.stampNone')}</p>
                <form
                    className="flex flex-wrap items-center gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        stamp.post(route('records.settings.stamp'), { forceFormData: true, preserveScroll: true, onSuccess: () => stamp.reset('stamp') });
                    }}
                >
                    <input type="file" accept="image/png,image/jpeg" onChange={(e) => stamp.setData('stamp', e.target.files[0])} className="text-sm" />
                    <button type="submit" className="btn-navy" disabled={stamp.processing || !stamp.data.stamp}>
                        {t('rec.uploadStamp')}
                    </button>
                    {hasStamp ? (
                        <button type="button" className="btn-outline !text-red-700" onClick={() => router.delete(route('records.settings.stamp.destroy'), { preserveScroll: true })}>
                            {t('rec.removeStamp')}
                        </button>
                    ) : null}
                </form>
                <InputError message={messageText(t, stamp.errors.stamp)} className="mt-2" />
            </Card>

            <Card title={t('rec.levelMap')}>
                <p className="mb-3 text-sm text-slate-600">{t('rec.levelMapHelp')}</p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        lv.put(route('records.settings.levels'), { preserveScroll: true });
                    }}
                    className="space-y-3"
                >
                    <ErrorSummary errors={lv.errors} />
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {lv.data.levels.map((l, i) => (
                            <div key={l.id} className="grid grid-cols-[1fr_6rem] items-center gap-2">
                                <span className="text-sm">{l.name}</span>
                                <Input
                                    type="number"
                                    min="1"
                                    max="13"
                                    placeholder="—"
                                    value={l.grade_number}
                                    onChange={(e) => lv.setData('levels', lv.data.levels.map((x, j) => (j === i ? { ...x, grade_number: e.target.value } : x)))}
                                />
                            </div>
                        ))}
                    </div>
                    <button type="submit" className="btn-navy" disabled={lv.processing}>
                        {t('acad.common.save')}
                    </button>
                </form>
            </Card>
        </AppShell>
    );
}
