import TextInput from '@/Components/TextInput';
import { Field } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, useForm, router } from '@inertiajs/react';
import ProfileLayout from './ProfileLayout';

export default function TwoFactor({
    enabled = false,
    confirmedAt = null,
    setup = null,
    status = null,
    recoveryCodes = null,
}) {
    const { t } = useLocale();
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });

    const statusLine = enabled
        ? confirmedAt
            ? t('profile.2fa.statusOn', { date: confirmedAt })
            : t('profile.2fa.statusOnNoDate')
        : t('profile.2fa.statusOff');

    return (
        <ProfileLayout title={t('profile.2fa.title')} className="max-w-2xl">
            <p className="mb-5">
                <Link href={route('profile.edit')} className="text-sm font-semibold text-teal hover:text-teal-700">
                    {t('profile.2fa.back')}
                </Link>
            </p>

            <section className="profile-card space-y-6">
                {status === 'two-factor-enabled' ? (
                    <p className="text-sm font-medium text-teal-700">{t('profile.2fa.nowEnabled')}</p>
                ) : null}
                {status === 'two-factor-disabled' ? (
                    <p className="text-sm font-medium text-teal-700">{t('profile.2fa.nowDisabled')}</p>
                ) : null}

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

                {!enabled && !setup ? (
                    <button type="button" className="btn-navy" onClick={() => router.post(route('two-factor.start'))}>
                        {t('profile.2fa.enable')}
                    </button>
                ) : null}

                {setup ? (
                    <div className="space-y-4">
                        <p className="text-sm text-slate-500">{t('profile.2fa.setupHelp')}</p>
                        <div className="rounded-xl bg-surface px-4 py-3 font-mono text-sm break-all text-navy">{setup.secret}</div>
                        <p className="text-xs text-slate-400 break-all">{t('profile.2fa.otpauth', { url: setup.otpauth_url })}</p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                confirmForm.post(route('two-factor.confirm'));
                            }}
                            className="max-w-sm space-y-3"
                        >
                            <Field label={t('profile.2fa.code')} error={confirmForm.errors.code}>
                                <TextInput
                                    id="code"
                                    value={confirmForm.data.code}
                                    onChange={(e) => confirmForm.setData('code', e.target.value)}
                                    autoComplete="one-time-code"
                                />
                            </Field>
                            <button type="submit" className="btn-teal" disabled={confirmForm.processing}>
                                {t('profile.2fa.confirm')}
                            </button>
                        </form>
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
                        <Field label={t('profile.2fa.disablePassword')} error={disableForm.errors.password}>
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
            </section>
        </ProfileLayout>
    );
}
