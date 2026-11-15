import TextInput from '@/Components/TextInput';
import { Field } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProfileLayout from './ProfileLayout';

export default function TwoFactor({
    enabled = false,
    method = null,
    required = false,
    confirmedAt = null,
    phone = '',
    smsAvailable = false,
    emailMasked = '',
    phoneMasked = null,
    sendWait = 0,
    setup = null,
    status = null,
    recoveryCodes = null,
}) {
    const { t } = useLocale();
    const pageErrors = usePage().props.errors ?? {};
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const [smsPhone, setSmsPhone] = useState(phone || '');

    const statusLine = enabled
        ? method === 'email'
            ? t('profile.2fa.statusOnEmail')
            : method === 'sms'
              ? t('profile.2fa.statusOnSms')
              : confirmedAt
                ? t('profile.2fa.statusOnTotp', { date: confirmedAt })
                : t('profile.2fa.statusOnNoDate')
        : t('profile.2fa.statusOff');

    const start = (chosen, extra = {}) => {
        router.post(route('two-factor.start'), { method: chosen, ...extra });
    };

    const body = (
        <>
            {required ? (
                <div className="mb-5">
                    <h1 className="text-xl font-bold text-navy">{t('profile.2fa.requiredTitle')}</h1>
                    <p className="mt-2 text-sm leading-6 text-slate-600">{t('profile.2fa.requiredHelp')}</p>
                </div>
            ) : (
                <p className="mb-5">
                    <Link href={route('profile.edit')} className="text-sm font-semibold text-teal hover:text-teal-700">
                        {t('profile.2fa.back')}
                    </Link>
                </p>
            )}

            <section className={required ? 'space-y-6' : 'profile-card space-y-6'}>
                {status === 'two-factor-enabled' ? (
                    <p className="text-sm font-medium text-teal-700">{t('profile.2fa.nowEnabled')}</p>
                ) : null}
                {status === 'two-factor-disabled' ? (
                    <p className="text-sm font-medium text-teal-700">{t('profile.2fa.nowDisabled')}</p>
                ) : null}
                {status === 'two-factor-code-sent' ? (
                    <p className="text-sm font-medium text-teal-700">{t('profile.2fa.sentTo', { destination: setup?.destination || '' })}</p>
                ) : null}

                {!required ? (
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold text-navy">{t('profile.2fa.status')}</h2>
                            <p className="mt-1 text-sm text-slate-500">{statusLine}</p>
                        </div>
                        <span
                            className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold leading-none whitespace-nowrap ${
                                enabled ? 'bg-teal/15 text-teal-800' : 'bg-slate-100 text-slate-600'
                            }`}
                        >
                            {enabled ? t('profile.2fa.badgeOn') : t('profile.2fa.badgeOff')}
                        </span>
                    </div>
                ) : null}

                {!enabled && !setup ? (
                    <div className="space-y-3">
                        <button
                            type="button"
                            className="btn-teal w-full"
                            onClick={() => start('email')}
                        >
                            {t('profile.2fa.sendEmail')}
                        </button>
                        <p className="text-xs text-slate-500">{t('profile.2fa.methodEmailHelp', { email: emailMasked })}</p>

                        <div className="rounded-xl border border-navy/10 p-4">
                            <p className="text-sm font-semibold text-navy">{t('profile.2fa.methodSms')}</p>
                            {smsAvailable ? (
                                <>
                                    <p className="mt-1 text-xs text-slate-500">{t('profile.2fa.phoneHint')}</p>
                                    <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                                        <Field
                                            className="min-w-0 flex-1"
                                            label={t('profile.2fa.phone')}
                                            error={messageText(t, pageErrors.phone)}
                                        >
                                            <TextInput
                                                id="phone"
                                                value={smsPhone}
                                                onChange={(e) => setSmsPhone(e.target.value)}
                                                autoComplete="tel"
                                                dir="ltr"
                                                className="w-full"
                                                placeholder={phoneMasked || '7123456'}
                                            />
                                        </Field>
                                        <button
                                            type="button"
                                            className="btn-navy"
                                            onClick={() => start('sms', { phone: smsPhone })}
                                        >
                                            {t('profile.2fa.sendSms')}
                                        </button>
                                    </div>
                                </>
                            ) : (
                                <p className="mt-1 text-xs text-slate-500">{t('profile.2fa.smsUnavailable')}</p>
                            )}
                        </div>

                        <button type="button" className="btn-outline w-full" onClick={() => start('totp')}>
                            {t('profile.2fa.methodTotp')}
                        </button>
                        <p className="text-xs text-slate-500">{t('profile.2fa.methodTotpHelp')}</p>
                    </div>
                ) : null}

                {setup?.method === 'totp' ? (
                    <div className="space-y-4">
                        <p className="text-sm text-slate-500">{t('profile.2fa.setupHelp')}</p>
                        <div className="rounded-xl bg-surface px-4 py-3 font-mono text-sm break-all text-navy">{setup.secret}</div>
                        <p className="text-xs text-slate-400 break-all">{t('profile.2fa.otpauth', { url: setup.otpauth_url })}</p>
                        <ConfirmCode form={confirmForm} t={t} />
                        <button type="button" className="text-sm font-semibold text-teal hover:text-teal-700" onClick={() => start('cancel')}>
                            {t('profile.2fa.chooseOther')}
                        </button>
                    </div>
                ) : null}

                {setup && setup.method !== 'totp' ? (
                    <div className="space-y-4">
                        <p className="text-sm text-slate-600">{t('profile.2fa.sentTo', { destination: setup.destination || '' })}</p>
                        <ConfirmCode form={confirmForm} t={t} />
                        <div className="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                className="btn-outline"
                                disabled={sendWait > 0}
                                onClick={() => router.post(route('two-factor.otp.send'))}
                            >
                                {sendWait > 0 ? t('profile.2fa.resendWait', { seconds: sendWait }) : t('profile.2fa.resend')}
                            </button>
                            <button type="button" className="text-sm font-semibold text-teal hover:text-teal-700" onClick={() => start('cancel')}>
                                {t('profile.2fa.chooseOther')}
                            </button>
                        </div>
                    </div>
                ) : null}

                {recoveryCodes?.length > 0 ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h3 className="font-semibold text-amber-900">{t('profile.2fa.recovery')}</h3>
                        <p className="mt-1 text-sm text-amber-800">{t('profile.2fa.recoveryHelp')}</p>
                        <ul className="mt-3 grid gap-1 font-mono text-sm text-amber-950 sm:grid-cols-2">
                            {recoveryCodes.map((code) => (
                                <li key={code}>{code}</li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                {enabled ? (
                    <form
                        className="max-w-sm space-y-3 border-t border-surface-muted pt-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            disableForm.delete(route('two-factor.disable'));
                        }}
                    >
                        <h3 className="text-base font-semibold text-navy">{t('profile.2fa.disableTitle')}</h3>
                        <Field label={t('profile.2fa.disablePassword')} error={messageText(t, disableForm.errors.password) || disableForm.errors.password}>
                            <TextInput
                                id="password"
                                type="password"
                                value={disableForm.data.password}
                                onChange={(e) => disableForm.setData('password', e.target.value)}
                            />
                        </Field>
                        <button type="submit" className="btn-danger" disabled={disableForm.processing}>
                            {t('profile.2fa.disable')}
                        </button>
                    </form>
                ) : null}

                {required ? (
                    <p className="pt-2">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-sm text-slate-600 underline hover:text-navy"
                        >
                            {t('auth.verify.logout')}
                        </Link>
                    </p>
                ) : null}
            </section>
        </>
    );

    if (required) {
        return (
            <GuestLayout>
                <Head title={t('profile.2fa.title')} />
                {body}
            </GuestLayout>
        );
    }

    return (
        <ProfileLayout title={t('profile.2fa.title')} className="max-w-2xl">
            {body}
        </ProfileLayout>
    );
}

function ConfirmCode({ form, t }) {
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('two-factor.confirm'));
            }}
            className="max-w-sm space-y-3"
        >
            <Field label={t('profile.2fa.code')} error={messageText(t, form.errors.code)}>
                <TextInput
                    id="code"
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value)}
                    autoComplete="one-time-code"
                    inputMode="numeric"
                />
            </Field>
            <button type="submit" className="btn-teal" disabled={form.processing}>
                {t('profile.2fa.confirm')}
            </button>
        </form>
    );
}
