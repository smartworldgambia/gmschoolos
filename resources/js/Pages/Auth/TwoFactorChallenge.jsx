import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge({
    method = 'totp',
    destination = '',
    sent = false,
    sendWait = 0,
    status = null,
}) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const help =
        method === 'email'
            ? t('auth.2fa.helpEmail', { destination })
            : method === 'sms'
              ? t('auth.2fa.helpSms', { destination })
              : t('auth.2fa.helpTotp');

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge.store'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.2fa.title')} />

            <div className="mb-4 text-sm text-gray-600">{help}</div>

            {status === 'two-factor-code-sent' || sent ? (
                method !== 'totp' ? (
                    <p className="mb-4 text-sm font-medium text-teal-700">{t('auth.2fa.sent')}</p>
                ) : null
            ) : null}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="code" value={t('auth.2fa.code')} />
                    <TextInput
                        id="code"
                        type="text"
                        name="code"
                        value={data.code}
                        className="mt-1 block w-full"
                        autoComplete="one-time-code"
                        inputMode="numeric"
                        isFocused
                        onChange={(e) => setData('code', e.target.value)}
                    />
                    <InputError message={messageText(t, errors.code) || errors.code} className="mt-2" />
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-end gap-3">
                    {method !== 'totp' ? (
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={sendWait > 0}
                            onClick={() => router.post(route('two-factor.challenge.send'))}
                        >
                            {sendWait > 0 ? t('profile.2fa.resendWait', { seconds: sendWait }) : t('auth.2fa.resend')}
                        </button>
                    ) : null}
                    <PrimaryButton className="ms-0" disabled={processing}>
                        {t('auth.2fa.submit')}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
