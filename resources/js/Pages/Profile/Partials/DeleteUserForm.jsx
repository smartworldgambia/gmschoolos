import Modal from '@/Components/Modal';
import TextInput from '@/Components/TextInput';
import { Field } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const { t } = useLocale();
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <section>
            <header>
                <h2 className="text-base font-semibold text-red-900">{t('profile.delete.title')}</h2>
                <p className="mt-1 text-sm text-red-800/80">{t('profile.delete.help')}</p>
            </header>

            <button type="button" className="btn-danger mt-5" onClick={confirmUserDeletion}>
                {t('profile.delete.button')}
            </button>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-semibold text-navy">{t('profile.delete.confirmTitle')}</h2>
                    <p className="mt-2 text-sm text-slate-500">{t('profile.delete.confirmBody')}</p>

                    <div className="mt-5">
                        <Field label={t('profile.delete.password')} error={errors.password}>
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                isFocused
                                placeholder={t('profile.delete.password')}
                            />
                        </Field>
                    </div>

                    <div className="mt-6 flex flex-wrap justify-end gap-2">
                        <button type="button" className="btn-outline" onClick={closeModal}>
                            {t('profile.delete.cancel')}
                        </button>
                        <button type="submit" className="btn-danger" disabled={processing}>
                            {t('profile.delete.button')}
                        </button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
