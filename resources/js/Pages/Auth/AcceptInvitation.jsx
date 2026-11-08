import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function AcceptInvitation({ invalid = false, token = '', invitation = null }) {
    const { t } = useLocale();
    const form = useForm({ password: '', password_confirmation: '' });

    if (invalid || !invitation) {
        return (
            <GuestLayout>
                <Head title={t('invite.invalid.title')} />
                <h1 className="text-xl font-bold text-navy">{t('invite.invalid.title')}</h1>
                <p className="mt-2 text-sm leading-6 text-slate-600">{t('invite.invalid.body')}</p>
                <Link href={route('login')} className="btn-teal mt-6 inline-flex whitespace-nowrap text-sm">
                    {t('invite.invalid.login')}
                </Link>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title={t('invite.title')} />
            <h1 className="text-xl font-bold text-navy">{t('invite.title')}</h1>
            <p className="mt-2 text-sm leading-6 text-slate-600">{t('invite.intro', { school: invitation.school })}</p>

            <dl className="mt-4 space-y-2 rounded-xl bg-surface p-4 text-sm">
                <div className="flex justify-between gap-3">
                    <dt className="text-slate-500">{t('invite.name')}</dt>
                    <dd className="font-medium text-navy">{invitation.name}</dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-slate-500">{t('invite.email')}</dt>
                    <dd className="break-all font-medium text-navy" dir="ltr">
                        {invitation.email}
                    </dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-slate-500">{t('invite.role')}</dt>
                    <dd className="font-medium text-navy">{t(`setup.role.${invitation.role}`)}</dd>
                </div>
            </dl>

            <form
                className="mt-5 space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('invitations.accept', token), { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <div>
                    <InputLabel htmlFor="password" value={t('onboarding.password')} />
                    <TextInput id="password" type="password" autoComplete="new-password" className="mt-1 block w-full" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required />
                    <InputError message={form.errors.password} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="password_confirmation" value={t('onboarding.confirmPassword')} />
                    <TextInput id="password_confirmation" type="password" autoComplete="new-password" className="mt-1 block w-full" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required />
                    <InputError message={form.errors.token} className="mt-1" />
                </div>
                <button type="submit" disabled={form.processing} className="btn-teal w-full whitespace-nowrap text-sm">
                    {t('invite.accept')}
                </button>
            </form>
        </GuestLayout>
    );
}
