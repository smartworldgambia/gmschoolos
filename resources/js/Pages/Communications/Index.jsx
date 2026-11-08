import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select, Stat, Textarea } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const TABS = ['settings', 'templates', 'sms', 'deliveries'];

function Toggle({ label, checked, onChange, hint }) {
    return (
        <div>
            <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" className="rounded border-gray-300" checked={checked} onChange={(e) => onChange(e.target.checked)} />
                {label}
            </label>
            {hint ? <p className="ms-6 mt-0.5 text-xs text-slate-500">{hint}</p> : null}
        </div>
    );
}

function ProviderCard({ provider }) {
    const { t } = useLocale();
    const state = provider.real ? (provider.configured ? 'configured' : 'unconfigured') : provider.driver === 'log' ? 'log' : 'off';
    const tone = state === 'configured' ? 'pending' : state === 'unconfigured' ? 'failed' : 'draft';

    return (
        <Card title={t('comm.provider.title')} actions={<StatusBadge status={tone} label={t(`comm.provider.state.${state}`)} />}>
            <dl className="space-y-1 text-sm">
                <div className="flex gap-2">
                    <dt className="text-slate-500">{t('comm.provider.driver')}:</dt>
                    <dd className="font-medium text-navy">{enumLabel(t, 'comm.provider.name', provider.driver)}</dd>
                </div>
            </dl>
            <p className="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{t(`comm.provider.note.${state}`)}</p>
        </Card>
    );
}

function SettingsTab({ settings, provider, stats, callbacks }) {
    const { t } = useLocale();
    const form = useForm({
        sms_enabled: settings.sms_enabled,
        sms_sender: settings.sms_sender ?? '',
        sms_daily_cap: settings.sms_daily_cap,
        parent_messaging: settings.parent_messaging,
        student_messaging: settings.student_messaging,
    });
    const test = useForm({ phone: '' });

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <div className="space-y-6">
                <ProviderCard provider={provider} />
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <Stat label={t('comm.stats.sent')} value={stats.sent} />
                    <Stat label={t('comm.stats.delivered')} value={stats.delivered} tone="green" />
                    <Stat label={t('comm.stats.failed')} value={stats.failed} tone="red" />
                    <Stat label={t('comm.stats.logged')} value={stats.logged} />
                    <Stat label={t('comm.stats.segments')} value={stats.segments} />
                    <Stat label={t('comm.stats.cost')} value={stats.cost} />
                </div>
                <p className="text-xs text-slate-500">{t('comm.stats.note')}</p>
                <Card title={t('comm.webhooks.title')}>
                    <p className="mb-2 text-xs text-slate-500">{t('comm.webhooks.note')}</p>
                    <ul className="space-y-1 text-xs">
                        <li>
                            <span className="font-semibold">Twilio:</span> <code className="break-all">{callbacks.twilio}</code>
                        </li>
                        <li>
                            <span className="font-semibold">Africa&apos;s Talking:</span> <code className="break-all">{callbacks.africastalking}</code>
                        </li>
                    </ul>
                </Card>
            </div>

            <div className="space-y-6">
                <Card title={t('comm.settings.title')}>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('communications.settings'), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={form.errors} />
                        <Toggle label={t('comm.settings.smsEnabled')} hint={t('comm.settings.smsEnabledHint')} checked={form.data.sms_enabled} onChange={(v) => form.setData('sms_enabled', v)} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('comm.settings.sender')} hint={t('comm.settings.senderHint')}>
                                <Input value={form.data.sms_sender} onChange={(e) => form.setData('sms_sender', e.target.value)} maxLength={11} />
                            </Field>
                            <Field label={t('comm.settings.cap')} hint={t('comm.settings.capHint')}>
                                <Input type="number" min={0} max={20000} value={form.data.sms_daily_cap} onChange={(e) => form.setData('sms_daily_cap', e.target.value)} />
                            </Field>
                        </div>
                        <Toggle label={t('comm.settings.parentMessaging')} hint={t('comm.settings.parentMessagingHint')} checked={form.data.parent_messaging} onChange={(v) => form.setData('parent_messaging', v)} />
                        <Toggle label={t('comm.settings.studentMessaging')} hint={t('comm.settings.studentMessagingHint')} checked={form.data.student_messaging} onChange={(v) => form.setData('student_messaging', v)} />
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('comm.settings.save')}
                        </button>
                    </form>
                </Card>

                <Card title={t('comm.test.title')}>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            test.post(route('communications.sms.test'), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={test.errors} />
                        <Field label={t('comm.test.phone')} hint={t('comm.test.hint')}>
                            <Input type="tel" value={test.data.phone} onChange={(e) => test.setData('phone', e.target.value)} required maxLength={30} />
                        </Field>
                        <button type="submit" className="btn-navy" disabled={test.processing || !settings.sms_enabled}>
                            {t('comm.test.send')}
                        </button>
                        {!settings.sms_enabled ? <p className="text-xs text-amber-700">{t('comm.test.needsOn')}</p> : null}
                    </form>
                </Card>
            </div>
        </div>
    );
}

function TemplateRow({ tpl }) {
    const { t } = useLocale();
    const [values, setValues] = useState(Object.fromEntries(tpl.locales.map((l) => [l.locale, l.custom])));

    const save = (locale) => router.post(route('communications.templates'), { key: tpl.key, locale, body: values[locale] ?? '' }, { preserveScroll: true });

    return (
        <Card title={enumLabel(t, 'comm.tpl', tpl.key)}>
            <p className="mb-3 text-xs text-slate-500">{enumLabel(t, 'comm.tplHelp', tpl.key)}</p>
            <div className="grid gap-4 lg:grid-cols-3">
                {tpl.locales.map((l) => (
                    <div key={l.locale}>
                        <Field label={t(`comm.locale.${l.locale}`)} hint={`${t('comm.tpl.default')}: ${l.default}`}>
                            <Textarea rows={3} dir={l.locale === 'ar' ? 'rtl' : 'ltr'} maxLength={480} value={values[l.locale] ?? ''} placeholder={l.default} onChange={(e) => setValues({ ...values, [l.locale]: e.target.value })} />
                        </Field>
                        <div className="mt-2 flex items-center justify-between gap-2">
                            <span className="text-xs text-slate-400">{(values[l.locale] ?? '').length}/480</span>
                            <button type="button" className="btn-outline !px-3 !py-1.5 text-xs" onClick={() => save(l.locale)}>
                                {t('comm.tpl.save')}
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </Card>
    );
}

export default function Index({ tab, settings, provider, stats, templates = [], smsLog, deliveries, filters = {}, callbacks, nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();

    const smsColumns = [
        { key: 'at', label: t('notif.history.when'), render: (r) => fmtWhen(r.at, locale) },
        { key: 'to', label: t('comm.sms.to') },
        { key: 'template', label: t('comm.sms.template'), render: (r) => (r.template ? enumLabel(t, 'comm.tpl', r.template) : '—') },
        { key: 'status', label: t('acct.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'comm.sms.status', r.status)} /> },
        { key: 'segments', label: t('comm.sms.segments') },
        { key: 'cost', label: t('comm.sms.cost'), render: (r) => (r.cost ? `${r.cost} ${r.currency ?? ''}` : '—') },
        { key: 'error', label: t('comm.sms.error'), cellClassName: 'max-w-[14rem] truncate', render: (r) => r.error ?? '—' },
        {
            key: 'actions',
            label: t('acct.col.actions'),
            render: (r) =>
                r.can_retry ? (
                    <button type="button" className="btn-outline !px-3 !py-1.5 text-xs" onClick={() => router.post(route('communications.sms.retry', r.id), {}, { preserveScroll: true })}>
                        {t('comm.sms.retry')}
                    </button>
                ) : null,
        },
    ];

    const deliveryColumns = [
        { key: 'at', label: t('notif.history.when'), render: (r) => fmtWhen(r.at, locale) },
        { key: 'user', label: t('comm.deliveries.person') },
        { key: 'channel', label: t('notif.history.channel'), render: (r) => t(`notif.channel.${r.channel}`) },
        { key: 'category', label: t('notif.category'), render: (r) => enumLabel(t, 'notif.category', r.category) },
        { key: 'summary', label: t('notif.history.summary'), cellClassName: 'max-w-xs truncate' },
        { key: 'status', label: t('acct.col.status'), render: (r) => <StatusBadge status={r.status} label={enumLabel(t, 'notif.deliveryStatus', r.status)} /> },
        { key: 'error', label: t('comm.sms.error'), cellClassName: 'max-w-[14rem] truncate', render: (r) => r.error ?? '—' },
    ];

    return (
        <AppShell title={t('comm.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('comm.title')} />
            <PageHeader title={t('comm.title')} subtitle={t('comm.subtitle')} />

            <div className="mb-5 flex flex-wrap gap-2" role="tablist">
                {TABS.map((k) => (
                    <Link
                        key={k}
                        href={route('communications.index', { tab: k })}
                        role="tab"
                        aria-selected={tab === k}
                        preserveScroll
                        className={`rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap ${tab === k ? 'bg-navy text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'}`}
                    >
                        {t(`comm.tab.${k}`)}
                    </Link>
                ))}
            </div>

            {tab === 'settings' ? <SettingsTab settings={settings} provider={provider} stats={stats} callbacks={callbacks} /> : null}

            {tab === 'templates' ? (
                <div className="space-y-6">
                    <p className="text-sm text-slate-500">{t('comm.tpl.intro')}</p>
                    {templates.map((tpl) => (
                        <TemplateRow key={tpl.key} tpl={tpl} />
                    ))}
                </div>
            ) : null}

            {tab === 'sms' && smsLog ? (
                <>
                    <div className="mb-4 flex items-center gap-3">
                        <Select className="!w-auto" aria-label={t('acct.col.status')} value={filters.status ?? ''} onChange={(e) => router.get(route('communications.index'), { tab: 'sms', status: e.target.value || undefined }, { preserveState: true, replace: true })}>
                            <option value="">{t('acct.status.all')}</option>
                            {['queued', 'sent', 'delivered', 'failed', 'logged'].map((s) => (
                                <option key={s} value={s}>
                                    {enumLabel(t, 'comm.sms.status', s)}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <p className="mb-3 text-xs text-slate-500">{t('comm.sms.note')}</p>
                    <DataTable columns={smsColumns} rows={smsLog.data} emptyMessage={t('comm.sms.empty')} />
                    <Pager links={smsLog.links} />
                </>
            ) : null}

            {tab === 'deliveries' && deliveries ? (
                <>
                    <p className="mb-3 text-xs text-slate-500">{t('comm.deliveries.note')}</p>
                    <DataTable columns={deliveryColumns} rows={deliveries.data} emptyMessage={t('notif.history.empty')} />
                    <Pager links={deliveries.links} />
                </>
            ) : null}
        </AppShell>
    );
}
