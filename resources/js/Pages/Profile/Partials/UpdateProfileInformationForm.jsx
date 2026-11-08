import TextInput from '@/Components/TextInput';
import { Field } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const { t } = useLocale();
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-navy">{t('profile.info.title')}</h2>
                <p className="mt-1 text-sm text-slate-500">{t('profile.info.help')}</p>
            </header>

            <form onSubmit={submit} className="profile-form">
                <Field label={t('profile.info.name')} error={errors.name}>
                    <TextInput
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoComplete="name"
                    />
                </Field>

                <Field label={t('profile.info.email')} error={errors.email}>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />
                </Field>

                {mustVerifyEmail && user.email_verified_at === null ? (
                    <div>
                        <p className="text-sm text-slate-700">
                            {t('profile.info.unverified')}{' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="font-semibold text-teal underline underline-offset-2 hover:text-teal-700"
                            >
                                {t('profile.info.resend')}
                            </Link>
                        </p>
                        {status === 'verification-link-sent' ? (
                            <p className="mt-2 text-sm font-medium text-teal-700">{t('profile.info.sent')}</p>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('profile.save')}
                    </button>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm font-medium text-teal-700">{t('profile.saved')}</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
