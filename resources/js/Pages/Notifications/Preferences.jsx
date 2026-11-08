import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Preferences({ matrix = [], phone, phoneValid, emailVerified, smsEnabled, history = [], nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();
    const prefs = useForm({ prefs: Object.fromEntries(matrix.map((r) => [r.category, { in_app: r.in_app, email: r.email, sms: r.sms }])) });
    const phoneForm = useForm({ phone: phone ?? '' });

    const set = (category, channel, value) => prefs.setData('prefs', { ...prefs.data.prefs, [category]: { ...prefs.data.prefs[category], [channel]: value } });

    return (
        <AppShell title={t('notif.prefs.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('notif.prefs.title')} />
            <PageHeader
                title={t('notif.prefs.title')}
                subtitle={t('notif.prefs.subtitle')}
                actions={
                    <Link href={route('notifications.index')} className="btn-outline">
                        {t('notif.back')}
                    </Link>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card title={t('notif.prefs.channels')}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            prefs.post(route('notifications.preferences.save'), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={prefs.errors} />
                        {!emailVerified ? <p className="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{t('notif.prefs.emailUnverified')}</p> : null}
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="text-xs uppercase tracking-wide text-slate-500">
                                        <th className="py-2 pe-4 text-start font-semibold">{t('notif.category')}</th>
                                        {['in_app', 'email', 'sms'].map((c) => (
                                            <th key={c} className="px-3 py-2 text-center font-semibold">
                                                {t(`notif.channel.${c}`)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {matrix.map((r) => (
                                        <tr key={r.category}>
                                            <td className="py-3 pe-4">
                                                <p className="font-medium text-navy">{enumLabel(t, 'notif.category', r.category)}</p>
                                                <p className="text-xs text-slate-500">{enumLabel(t, 'notif.categoryHelp', r.category)}</p>
                                            </td>
                                            {['in_app', 'email', 'sms'].map((c) => {
                                                const locked = (r.mandatory && c !== 'sms') || (c === 'sms' && !r.sms_available);

                                                return (
                                                    <td key={c} className="px-3 py-3 text-center">
                                                        <input
                                                            type="checkbox"
                                                            className="rounded border-gray-300"
                                                            aria-label={`${enumLabel(t, 'notif.category', r.category)} — ${t(`notif.channel.${c}`)}`}
                                                            checked={locked && c !== 'sms' ? true : Boolean(prefs.data.prefs[r.category]?.[c]) && !(c === 'sms' && !r.sms_available)}
                                                            disabled={locked}
                                                            onChange={(e) => set(r.category, c, e.target.checked)}
                                                        />
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="mt-3 text-xs text-slate-500">{t('notif.prefs.mandatory')}</p>
                        <button type="submit" className="btn-teal mt-4" disabled={prefs.processing}>
                            {t('notif.prefs.save')}
                        </button>
                    </form>
                </Card>

                <Card title={t('notif.phone.title')}>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            phoneForm.post(route('notifications.phone'), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={phoneForm.errors} />
                        <Field label={t('notif.phone.label')} hint={t('notif.phone.hint')}>
                            <Input type="tel" value={phoneForm.data.phone} onChange={(e) => phoneForm.setData('phone', e.target.value)} maxLength={30} />
                        </Field>
                        <p className="text-sm">
                            {phone ? <StatusBadge status={phoneValid ? 'verified' : 'warning'} label={phoneValid ? t('notif.phone.valid') : t('notif.phone.invalid')} /> : <StatusBadge status="draft" label={t('notif.phone.none')} />}
                        </p>
                        <p className="text-xs text-slate-500">{smsEnabled ? t('notif.phone.smsOn') : t('notif.phone.smsOff')}</p>
                        <button type="submit" className="btn-teal" disabled={phoneForm.processing}>
                            {t('notif.phone.save')}
                        </button>
                    </form>
                </Card>
            </div>

            <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">{t('notif.history.title')}</h2>
            <p className="mb-3 text-xs text-slate-500">{t('notif.history.note')}</p>
            <DataTable
                columns={[
                    { key: 'at', label: t('notif.history.when'), render: (r) => fmtWhen(r.at, locale) },
                    { key: 'channel', label: t('notif.history.channel'), render: (r) => t(`notif.channel.${r.channel}`) },
                    { key: 'category', label: t('notif.category'), render: (r) => enumLabel(t, 'notif.category', r.category) },
                    { key: 'summary', label: t('notif.history.summary'), cellClassName: 'max-w-xs truncate' },
                    { key: 'status', label: t('acct.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'notif.deliveryStatus', r.status)} /> },
                ]}
                rows={history}
                emptyMessage={t('notif.history.empty')}
            />
        </AppShell>
    );
}
