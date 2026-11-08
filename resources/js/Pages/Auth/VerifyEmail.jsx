import PrimaryButton from '@/Components/PrimaryButton';
import { useLocale } from '@/i18n/LocaleProvider';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { t } = useLocale();
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.verify.title')} />

            <div className="mb-4 text-sm text-gray-600">{t('auth.verify.help')}</div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">{t('auth.verify.sent')}</div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>{t('auth.verify.resend')}</PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('auth.verify.logout')}
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
