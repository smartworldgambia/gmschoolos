import TextInput from '@/Components/TextInput';
import { Field } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm() {
    const { t } = useLocale();
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                }

                if (errs.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                }
            },
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-navy">{t('profile.password.title')}</h2>
                <p className="mt-1 text-sm text-slate-500">{t('profile.password.help')}</p>
            </header>

            <form onSubmit={updatePassword} className="profile-form">
                <Field label={t('profile.password.current')} error={errors.current_password}>
                    <TextInput
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        type="password"
                        autoComplete="current-password"
                    />
                </Field>

                <Field label={t('profile.password.new')} error={errors.password}>
                    <TextInput
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        autoComplete="new-password"
                    />
                </Field>

                <Field label={t('profile.password.confirm')} error={errors.password_confirmation}>
                    <TextInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        type="password"
                        autoComplete="new-password"
                    />
                </Field>

                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-navy" disabled={processing}>
                        {t('profile.password.save')}
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
