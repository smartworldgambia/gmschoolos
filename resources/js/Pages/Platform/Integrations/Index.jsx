import { Card, Field, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import { fmtWhen } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const STATE_TONE = { none: 'draft', unreadable: 'failed', untested: 'pending', failed: 'failed', tested: 'info' };

const MAIL_FIELDS = ['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'];
const TWILIO_FIELDS = ['twilio_sid', 'twilio_token', 'twilio_from', 'twilio_messaging_service_sid', 'twilio_status_callback'];
const AT_FIELDS = ['at_username', 'at_api_key', 'at_sender_id', 'at_sandbox', 'at_webhook_secret'];

/** One credential input. Secrets are write-only: the placeholder says whether one is stored, never what it is. */
function SecretInput({ name, form, item, error }) {
    const { t } = useLocale();
    const meta = item.secrets[name];
    const hint = meta?.set ? (meta.hint ? t('integ.secret.keepHint', { hint: meta.hint }) : t('integ.secret.keep')) : t('integ.secret.empty');

    return (
        <Field label={t(`integ.f.${name}`)} error={error} hint={hint}>
            <Input
                type="password"
                dir="ltr"
                autoComplete="new-password"
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                maxLength={500}
            />
        </Field>
    );
}

function TextInput({ name, form, error, hint, type = 'text', ...rest }) {
    const { t } = useLocale();

    return (
        <Field label={t(`integ.f.${name}`)} error={error} hint={hint}>
            <Input type={type} dir="ltr" value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} {...rest} />
        </Field>
    );
}

function StatusLine({ item }) {
    const { t, locale } = useLocale();

    return (
        <div className="mb-4 flex flex-wrap items-center gap-2">
            <StatusBadge status={item.active ? 'active' : 'inactive'} label={item.active ? t('integ.active') : t('integ.inactive')} />
            <StatusBadge status={STATE_TONE[item.state] ?? 'draft'} label={t(`integ.state.${item.state}`)} />
            <StatusBadge status={item.verified ? 'verified' : 'draft'} label={item.verified ? t('integ.verified') : t('integ.unverified')} />
            <span className="text-xs text-slate-500">{item.active ? t('integ.using.console') : t('integ.using.server')}</span>
            {item.updated_at ? (
                <span className="text-xs text-slate-500">{t('integ.savedBy', { when: fmtWhen(item.updated_at, locale), name: item.updated_by ?? '' })}</span>
            ) : null}
        </div>
    );
}

function ServerDefault({ item, integration }) {
    const { t } = useLocale();
    const s = item.server;

    return (
        <div className="mb-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            <p className="font-semibold text-slate-700">
                {t('integ.server.label')}: {enumLabel(t, 'integ.driver', s.driver)}
                {s.host ? <span dir="ltr"> {'\u00b7'} {s.host}</span> : null}
            </p>
            <p className="mt-1">{s.real ? t('integ.server.real') : t('integ.server.logOnly')}</p>
            {integration === 'mail' && !s.real ? <p className="mt-1">{t('integ.server.cloudHint')}</p> : null}
            {integration === 'mail' && s.from ? (
                <p className="mt-1" dir="ltr">
                    {t('integ.f.from_address')}: {s.from}
                </p>
            ) : null}
        </div>
    );
}

function CardFlash({ integration }) {
    const { t } = useLocale();
    const flash = usePage().props.flash ?? {};
    if (flash.integration && flash.integration !== integration) {
        return null;
    }
    const success = messageText(t, flash.success);
    const error = messageText(t, flash.error);
    if (!success && !error) {
        return null;
    }

    return (
        <div
            className={
                error
                    ? 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800'
                    : 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900'
            }
            role={error ? 'alert' : 'status'}
            aria-live="polite"
        >
            {error || success}
        </div>
    );
}

function Actions({ integration, item, form, errors, testLabel, targetLabel, defaultTarget }) {
    const { t, locale } = useLocale();
    const [target, setTarget] = useState(defaultTarget ?? '');
    const canSwitchOn = item.state === 'tested' && !item.active;
    const password = { current_password: form.data.current_password };
    const reset = { preserveScroll: true, onSuccess: () => form.setData('current_password', '') };

    const post = (name, data = password) => router.post(route(`platform.integrations.${name}`, integration), data, reset);
    const sendTest = () => router.post(route('platform.integrations.test', integration), { target }, { preserveScroll: true });

    return (
        <div className="mt-6 space-y-5">
            <CardFlash integration={integration} />
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('integ.password')} hint={t('integ.password.hint')} error={messageText(t, errors.current_password)}>
                    <Input type="password" autoComplete="current-password" value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} />
                </Field>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <button type="submit" disabled={form.processing} className="btn-teal text-sm">
                    {t('integ.save')}
                </button>
                {item.state !== 'none' ? (
                    <button
                        type="button"
                        className="btn-outline text-sm"
                        onClick={() => {
                            if (window.confirm(t('integ.confirm.remove'))) {
                                router.delete(route('platform.integrations.destroy', integration), { data: password, ...reset });
                            }
                        }}
                    >
                        {t('integ.remove')}
                    </button>
                ) : null}
            </div>
            {item.active ? <p className="text-xs text-slate-500">{t('integ.edit.note')}</p> : null}

            {item.state !== 'none' && item.state !== 'unreadable' ? (
                <div className="rounded-lg border border-slate-200 p-4">
                    <h3 className="text-sm font-semibold text-navy">{t('integ.test.title')}</h3>
                    <div className="mt-3 flex flex-wrap items-end gap-2">
                        <Field label={targetLabel} error={messageText(t, errors.target)} className="min-w-[14rem] flex-1">
                            <Input
                                dir="ltr"
                                value={target}
                                onChange={(e) => setTarget(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();      // Enter here sends the test, it must not submit the settings form
                                        sendTest();
                                    }
                                }}
                            />
                        </Field>
                        <button type="button" className="btn-navy text-sm" onClick={sendTest}>
                            {testLabel}
                        </button>
                    </div>

                    {item.state === 'tested' && item.tested_at ? (
                        <p className="mt-3 text-sm text-emerald-700">{t('integ.test.lastOk', { when: fmtWhen(item.tested_at, locale), to: item.tested_to ?? '' })}</p>
                    ) : null}
                    {item.state === 'failed' ? (
                        <div className="mt-3 text-sm text-red-700">
                            <p>{t('integ.test.lastFailed', { when: item.tested_at ? fmtWhen(item.tested_at, locale) : '' })}</p>
                            {item.test_error ? (
                                <p className="mt-1 break-words rounded bg-red-50 px-2 py-1 font-mono text-xs" dir="ltr">
                                    {item.test_error}
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {canSwitchOn ? (
                            <button type="button" className="btn-teal text-sm" onClick={() => post('activate')}>
                                {t('integ.on')}
                            </button>
                        ) : null}
                        {item.active ? (
                            <button type="button" className="btn-outline text-sm" onClick={() => post('deactivate')}>
                                {t('integ.off')}
                            </button>
                        ) : null}
                        {item.state === 'tested' && !item.verified ? (
                            <button type="button" className="btn-outline text-sm" onClick={() => router.post(route('platform.integrations.confirm', integration), {}, { preserveScroll: true })}>
                                {t('integ.confirmReceived')}
                            </button>
                        ) : null}
                        {item.verified && item.confirmed_at ? <span className="text-sm text-emerald-700">{t('integ.confirmedAt', { when: fmtWhen(item.confirmed_at, locale) })}</span> : null}
                    </div>
                    {item.state !== 'tested' ? <p className="mt-3 text-xs text-slate-500">{t('integ.test.needed')}</p> : null}
                </div>
            ) : null}
        </div>
    );
}

function MailCard({ item, testDefaults }) {
    const { t } = useLocale();
    const errors = usePage().props.errors ?? {};
    const form = useForm({ ...item.values, password: '', current_password: '' });
    const err = (name) => messageText(t, form.errors[name] ?? errors[name]);

    return (
        <Card title={t('integ.name.mail')}>
            <StatusLine item={item} />
            <ServerDefault item={item} integration="mail" />
            {item.state === 'none' ? <p className="mb-4 text-sm text-slate-600">{t('integ.mail.prefill')}</p> : null}
            {item.state === 'unreadable' ? <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{t('integ.unreadable.note')}</p> : null}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(route('platform.integrations.save', 'mail'), { preserveScroll: true, onSuccess: () => form.setData({ ...form.data, password: '', current_password: '' }) });
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <TextInput name="host" form={form} error={err('host')} maxLength={190} required />
                    <TextInput name="port" form={form} error={err('port')} type="number" min={1} max={65535} required />
                    <Field label={t('integ.f.encryption')} error={err('encryption')}>
                        <Select value={form.data.encryption} onChange={(e) => form.setData('encryption', e.target.value)}>
                            {['tls', 'ssl'].map((v) => (
                                <option key={v} value={v}>
                                    {t(`integ.enc.${v}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <TextInput name="username" form={form} error={err('username')} maxLength={190} autoComplete="off" />
                    <SecretInput name="password" form={form} item={item} error={err('password')} />
                    <TextInput name="from_address" form={form} error={err('from_address')} type="email" maxLength={190} required />
                    <TextInput name="from_name" form={form} error={err('from_name')} maxLength={120} required />
                </div>
                <Actions
                    integration="mail"
                    item={item}
                    form={form}
                    errors={{ ...errors, ...form.errors }}
                    targetLabel={t('integ.test.email')}
                    testLabel={t('integ.test.sendMail')}
                    defaultTarget={testDefaults.email}
                />
            </form>
        </Card>
    );
}

function SmsCard({ item, testDefaults, webhooks }) {
    const { t } = useLocale();
    const errors = usePage().props.errors ?? {};
    const form = useForm({ ...item.values, twilio_token: '', at_api_key: '', at_webhook_secret: '', current_password: '' });
    const err = (name) => messageText(t, form.errors[name] ?? errors[name]);
    const driver = form.data.driver;
    const atUrl = String(webhooks.africastalking).replace('{secret}', '<secret>');

    return (
        <Card title={t('integ.name.sms')}>
            <StatusLine item={item} />
            <ServerDefault item={item} integration="sms" />
            {item.state === 'unreadable' ? <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{t('integ.unreadable.note')}</p> : null}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(route('platform.integrations.save', 'sms'), {
                        preserveScroll: true,
                        onSuccess: () => form.setData({ ...form.data, twilio_token: '', at_api_key: '', at_webhook_secret: '', current_password: '' }),
                    });
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label={t('integ.f.driver')} error={err('driver')}>
                        <Select value={driver} onChange={(e) => form.setData('driver', e.target.value)}>
                            {['twilio', 'africastalking'].map((v) => (
                                <option key={v} value={v}>
                                    {t(`integ.driver.${v}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>

                {driver === 'twilio' ? (
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <TextInput name="twilio_sid" form={form} error={err('twilio_sid')} maxLength={190} autoComplete="off" />
                        <SecretInput name="twilio_token" form={form} item={item} error={err('twilio_token')} />
                        <TextInput name="twilio_from" form={form} error={err('twilio_from')} maxLength={40} hint={t('integ.hint.twilioFrom')} />
                        <TextInput name="twilio_messaging_service_sid" form={form} error={err('twilio_messaging_service_sid')} maxLength={190} />
                        <div className="sm:col-span-2">
                            <TextInput name="twilio_status_callback" form={form} error={err('twilio_status_callback')} type="url" maxLength={300} />
                        </div>
                        <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 sm:col-span-2">
                            <p className="font-semibold text-slate-700">{t('integ.webhook.title')}</p>
                            <p className="mt-1 break-all font-mono" dir="ltr">
                                {webhooks.twilio}
                            </p>
                            <p className="mt-1">{t('integ.webhook.note')}</p>
                        </div>
                    </div>
                ) : (
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <TextInput name="at_username" form={form} error={err('at_username')} maxLength={190} autoComplete="off" />
                        <SecretInput name="at_api_key" form={form} item={item} error={err('at_api_key')} />
                        <TextInput name="at_sender_id" form={form} error={err('at_sender_id')} maxLength={40} />
                        <SecretInput name="at_webhook_secret" form={form} item={item} error={err('at_webhook_secret')} />
                        <label className="flex items-center gap-2 text-sm font-medium text-slate-700 sm:col-span-2">
                            <input type="checkbox" className="rounded border-gray-300" checked={!!form.data.at_sandbox} onChange={(e) => form.setData('at_sandbox', e.target.checked)} />
                            {t('integ.f.at_sandbox')}
                        </label>
                        <p className="text-xs text-slate-500 sm:col-span-2">{t('integ.hint.atWebhook')}</p>
                        <div className="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 sm:col-span-2">
                            <p className="font-semibold text-slate-700">{t('integ.webhook.title')}</p>
                            <p className="mt-1 break-all font-mono" dir="ltr">
                                {atUrl}
                            </p>
                            <p className="mt-1">{t('integ.webhook.note')}</p>
                        </div>
                    </div>
                )}

                <Actions
                    integration="sms"
                    item={item}
                    form={form}
                    errors={{ ...errors, ...form.errors }}
                    targetLabel={t('integ.test.phone')}
                    testLabel={t('integ.test.sendSms')}
                    defaultTarget={testDefaults.phone}
                />
            </form>
        </Card>
    );
}

function Activity({ rows }) {
    const { t, locale } = useLocale();

    return (
        <Card title={t('integ.activity.title')}>
            {rows.length === 0 ? (
                <p className="text-sm text-slate-500">{t('integ.activity.empty')}</p>
            ) : (
                <ul className="divide-y divide-slate-100 text-sm">
                    {rows.map((r) => (
                        <li key={r.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2">
                            <span className="font-medium text-navy">{t(`integ.name.${r.integration}`)}</span>
                            <span>{enumLabel(t, 'integ.event', r.event)}</span>
                            {r.fields.length ? <span className="text-xs text-slate-500">{r.fields.map((f) => t(`integ.f.${f}`)).join(', ')}</span> : null}
                            <span className="text-xs text-slate-500">{r.by ? t('integ.activity.by', { name: r.by }) : ''}</span>
                            <span className="ms-auto text-xs text-slate-400">{fmtWhen(r.at, locale)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

export default function Index({ items, webhooks, testDefaults, activity = [], nav = [], user, school }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('integ.title')} nav={nav} user={user} school={school}>
            <Head title={t('integ.title')} />
            <PageHeader title={t('integ.title')} subtitle={t('integ.subtitle')} />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="console-card">
                        <h2 className="text-base font-semibold text-navy">{t('integ.scope.title')}</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">{t('integ.scope.body')}</p>
                        <p className="mt-3 text-xs leading-5 text-slate-500">{t('integ.secretsNote')}</p>
                    </div>
                    <div className="console-card">
                        <h2 className="text-base font-semibold text-navy">{t('integ.flow.title')}</h2>
                        <ol className="mt-2 space-y-1 text-sm text-slate-600">
                            {[1, 2, 3, 4].map((n) => (
                                <li key={n}>{t(`integ.flow.${n}`)}</li>
                            ))}
                        </ol>
                    </div>
                </div>

                <MailCard item={items.mail} testDefaults={testDefaults} />
                <SmsCard item={items.sms} testDefaults={testDefaults} webhooks={webhooks} />
                <Activity rows={activity} />
            </div>
        </AppShell>
    );
}
