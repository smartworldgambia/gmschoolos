import { roleLabel } from '@/lib/comm';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import ProfileLayout from './ProfileLayout';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

function initials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    const first = parts[0]?.[0] ?? '';
    const second = parts[1]?.[0] ?? '';

    return `${first}${second}`.toUpperCase() || '?';
}

function ShieldIcon() {
    return (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.8}
                d="M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
            />
        </svg>
    );
}

export default function Edit({ mustVerifyEmail, status, twoFactorEnabled = false, twoFactorSettingsUrl }) {
    const { t } = useLocale();
    const page = usePage();
    const authUser = page.props.user ?? page.props.auth?.user ?? {};
    const school = page.props.school;
    const displayName = authUser.name ?? t('shell.user');
    const roleSlug = authUser.role ?? authUser.roles?.[0]?.name ?? authUser.roles?.[0] ?? null;
    const displayRole = roleSlug ? roleLabel(t, roleSlug) : t('shell.member');
    const schoolName = school?.slug === 'platform' ? t('shell.platformConsole') : school?.name;
    const twoFaHref = twoFactorSettingsUrl || route('two-factor.settings');

    return (
        <ProfileLayout title={t('profile.title')}>
            <header className="mb-5 hidden lg:block">
                <h1 className="text-2xl font-bold tracking-tight text-navy">{t('profile.title')}</h1>
                <p className="mt-1 text-sm text-slate-500">{t('profile.subtitle')}</p>
            </header>

            <div className="profile-grid">
                <aside className="profile-identity">
                    <div className="flex items-center gap-4 lg:flex-col lg:items-start">
                        <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-teal text-lg font-bold text-white shadow-sm lg:h-16 lg:w-16 lg:text-xl">
                            {initials(displayName)}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-base font-semibold text-navy">{displayName}</p>
                            <p className="mt-0.5 truncate text-sm text-slate-500">{authUser.email}</p>
                            <p className="mt-2 text-xs font-medium text-slate-500 lg:hidden">
                                <span className="capitalize">{displayRole}</span>
                                {schoolName ? <span className="text-slate-400"> · {schoolName}</span> : null}
                            </p>
                        </div>
                    </div>
                    <dl className="mt-4 hidden space-y-3 border-t border-surface-muted pt-4 lg:block">
                        <div>
                            <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('profile.identity.role')}</dt>
                            <dd className="mt-0.5 text-sm font-medium capitalize text-navy">{displayRole}</dd>
                        </div>
                        {schoolName ? (
                            <div>
                                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{t('profile.identity.school')}</dt>
                                <dd className="mt-0.5 truncate text-sm font-medium text-navy">{schoolName}</dd>
                            </div>
                        ) : null}
                    </dl>
                    <div className="mt-4 flex items-center justify-between gap-3 border-t border-surface-muted pt-4">
                        <span className="text-xs font-medium text-slate-500">{t('profile.2fa.title')}</span>
                        <span
                            className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold leading-none whitespace-nowrap ${
                                twoFactorEnabled ? 'bg-teal/15 text-teal-800' : 'bg-slate-100 text-slate-600'
                            }`}
                        >
                            {twoFactorEnabled ? t('profile.2fa.badgeOn') : t('profile.2fa.badgeOff')}
                        </span>
                    </div>
                </aside>

                <div className="space-y-5">
                    <section className="profile-card">
                        <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    </section>

                    <section className="profile-card">
                        <UpdatePasswordForm />
                    </section>

                    <section className="profile-card">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-navy/5 text-navy">
                                    <ShieldIcon />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="text-base font-semibold text-navy">{t('profile.2fa.title')}</h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {twoFactorEnabled ? t('profile.2fa.enabled') : t('profile.2fa.disabled')}
                                    </p>
                                </div>
                            </div>
                            <Link href={twoFaHref} className="btn-navy">
                                {t('profile.2fa.manage')}
                            </Link>
                        </div>
                    </section>

                    <section className="profile-card-danger">
                        <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-red-700">
                            {t('profile.danger.zone')}
                        </p>
                        <DeleteUserForm />
                    </section>
                </div>
            </div>
        </ProfileLayout>
    );
}
