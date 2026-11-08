import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge.store'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.2fa.title')} />

            <div className="mb-4 text-sm text-gray-600">{t('auth.2fa.help')}</div>

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
                        isFocused
                        onChange={(e) => setData('code', e.target.value)}
                    />
                    <InputError message={errors.code} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        {t('auth.2fa.submit')}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
