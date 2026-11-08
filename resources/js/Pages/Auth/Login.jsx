import { assetUrl } from '@/lib/assetUrl';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import BrandLogo from '@/Components/Marketing/BrandLogo';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { messageText } from '@/lib/academic';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const inputClass =
    'mt-1.5 block w-full rounded-xl border-navy/15 bg-white px-3.5 py-2.5 text-sm text-brand-ink shadow-sm placeholder:text-slate-400 focus:border-brand-bright focus:ring-brand-bright';

function SchoolMark({ name, accent = '#1683F8', primary = '#082B55' }) {
    return (
        <span
            className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl shadow-soft ring-1 ring-white/20"
            style={{ backgroundColor: primary }}
            aria-hidden="true"
        >
            <svg viewBox="0 0 40 40" className="h-8 w-8">
                <path
                    d="M20 4 8 10v10c0 7.2 4.8 12.2 12 13.5C27.2 32.2 32 27.2 32 20V10L20 4Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="1.8"
                />
                <path d="M20 8v22" stroke={accent} strokeWidth="1.4" />
                <path
                    d="M12 14.5 20 19l8-4.5"
                    stroke="#FFFFFF"
                    strokeWidth="1.3"
                    fill="none"
                    opacity="0.85"
                />
                <text
                    x="20"
                    y="27"
                    textAnchor="middle"
                    fill="white"
                    fontSize="9"
                    fontWeight="700"
                    fontFamily="Plus Jakarta Sans, sans-serif"
                >
                    {String(name || 'S').charAt(0).toUpperCase()}
                </text>
            </svg>
        </span>
    );
}

function SchoolBrandLink({ school, tone = 'onDark', ariaLabel }) {
    const onDark = tone === 'onDark';

    return (
        <Link
            href={school.home_url || '/'}
            aria-label={ariaLabel}
            className="inline-flex max-w-full items-center gap-3 focus-visible:rounded-lg"
        >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-soft ring-1 ring-black/5">
                {school.logo_url || school.logo_path ? (
                    <img
                        src={assetUrl(school.logo_url ?? school.logo_path)}
                        alt=""
                        className="h-full w-full object-contain p-1"
                    />
                ) : (
                    <span
                        className="flex h-full w-full items-center justify-center text-lg font-extrabold text-white"
                        style={{ backgroundColor: school.primary_color || '#082B55' }}
                        aria-hidden="true"
                    >
                        {String(school.name || 'S').charAt(0).toUpperCase()}
                    </span>
                )}
            </span>
            <span
                className={`truncate text-base font-extrabold tracking-tight ${onDark ? 'text-white' : 'text-navy'}`}
            >
                {school.name}
            </span>
        </Link>
    );
}

function AuthBrand({ school }) {
    if (school?.logo_url || school?.logo_path) {
        return (
            <img
                src={assetUrl(school.logo_url ?? school.logo_path)}
                alt=""
                className="h-14 w-14 rounded-2xl bg-white object-contain p-1.5 shadow-soft ring-1 ring-white/25"
            />
        );
    }

    if (school?.name) {
        return (
            <SchoolMark
                name={school.name}
                accent={school.accent_color || '#1683F8'}
                primary={school.primary_color || '#082B55'}
            />
        );
    }

    return (
        <span className="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 shadow-soft ring-1 ring-white/20 backdrop-blur-sm">
            <svg viewBox="0 0 32 32" className="h-8 w-8" aria-hidden="true">
                <path
                    d="M6 10.5 16 5l10 5.5V22L16 27.5 6 22V10.5Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="1.6"
                />
                <path d="M16 5v22.5" stroke="#10B9A5" strokeWidth="1.4" />
                <path d="M6 10.5 16 16l10-5.5" stroke="#1683F8" strokeWidth="1.4" fill="none" />
                <path d="M11 14.2 16 17l5-2.8" stroke="#08A85A" strokeWidth="1.2" fill="none" />
            </svg>
        </span>
    );
}

export default function Login({ status, canResetPassword, school = null }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const brandName = school?.name || t('auth.brand.name');
    const panelTitle = school?.name
        ? t('auth.login.panelTitleSchool').replace('{name}', school.name)
        : t('auth.login.panelTitle');
    const primary = school?.primary_color || '#082B55';
    const accent = school?.accent_color || '#1683F8';
    // Brand the left panel with the school's own colours when the login was opened from a school site.
    const panelStyle = school
        ? {
              background: `radial-gradient(ellipse 80% 60% at 15% 20%, color-mix(in srgb, ${accent} 38%, transparent), transparent 55%), radial-gradient(ellipse 70% 50% at 90% 85%, color-mix(in srgb, ${accent} 22%, transparent), transparent 50%), linear-gradient(165deg, color-mix(in srgb, ${primary} 70%, #000) 0%, ${primary} 55%, color-mix(in srgb, ${primary} 80%, #fff) 100%)`,
          }
        : undefined;

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout variant="bare">
            <Head title={school?.name ? `${t('auth.login.title')} · ${school.name}` : t('auth.login.title')} />

            <div className="grid min-h-screen flex-1 lg:grid-cols-2">
                {/* Visual / brand panel */}
                <aside
                    className="auth-login-panel relative hidden overflow-hidden text-white lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12 xl:px-16"
                    style={panelStyle}
                >
                    <div className="auth-login-panel-glow pointer-events-none absolute inset-0" aria-hidden="true" />
                    <div className="auth-shell-grid pointer-events-none absolute inset-0 opacity-30 mix-blend-soft-light" aria-hidden="true" />

                    <div className="relative z-10">
                        {school ? (
                            <SchoolBrandLink
                                school={school}
                                tone="onDark"
                                ariaLabel={t('auth.login.schoolHomeAria', { name: school.name })}
                            />
                        ) : (
                            <BrandLogo
                                tone="onDark"
                                label={t('auth.brand.name')}
                                ariaLabel={t('auth.brand.homeAria')}
                            />
                        )}
                    </div>

                    <div className="relative z-10 max-w-md animate-auth-rise">
                        {school ? null : (
                            <>
                                <div className="mb-6">
                                    <AuthBrand school={school} />
                                </div>
                                <p className="text-xs font-bold uppercase tracking-[0.16em] text-teal-200/90">
                                    {t('auth.login.panelEyebrow')}
                                </p>
                            </>
                        )}
                        <h1 className="mt-3 text-3xl font-extrabold leading-tight tracking-tight xl:text-4xl">
                            {panelTitle}
                        </h1>
                        <p className="mt-4 text-base leading-relaxed text-slate-200/90">
                            {t('auth.login.panelBody')}
                        </p>
                        <p className="mt-6 text-sm font-medium text-white/80">
                            {t('auth.login.rolesHint')}
                        </p>
                    </div>

                    <p className="relative z-10 text-xs text-white/50">
                        {school ? `${t('publicSite.powered')} ${t('auth.brand.name')}` : t('auth.login.panelFooter')}
                    </p>
                </aside>

                {/* Form panel */}
                <div className="relative flex flex-1 flex-col justify-center px-4 py-16 sm:px-8 lg:px-12 xl:px-20">
                    <div className="mx-auto w-full max-w-[24rem] animate-auth-rise">
                        {/* Mobile brand header */}
                        <div className="mb-8 lg:hidden">
                            {school ? (
                                <SchoolBrandLink
                                    school={school}
                                    tone="onLight"
                                    ariaLabel={t('auth.login.schoolHomeAria', { name: school.name })}
                                />
                            ) : (
                                <BrandLogo label={t('auth.brand.name')} ariaLabel={t('auth.brand.homeAria')} />
                            )}
                        </div>

                        <div className="mb-8">
                            <h2 className="text-2xl font-extrabold tracking-tight text-navy sm:text-[1.75rem]">
                                {t('auth.login.heading')}
                            </h2>
                            <p className="mt-2 text-sm leading-relaxed text-brand-mute">
                                {school ? t('auth.login.subtitleSchool', { name: school.name }) : t('auth.login.subtitle')}
                            </p>
                            <p className="mt-2 text-sm text-brand-mute lg:hidden">
                                {t('auth.login.rolesHint')}
                            </p>
                        </div>

                        {status && (
                            <div
                                className="mb-6 rounded-xl border border-brand-green/20 bg-teal-50 px-4 py-3 text-sm font-medium text-emerald-800"
                                role="status"
                            >
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5" noValidate>
                            <div>
                                <InputLabel
                                    htmlFor="email"
                                    value={t('auth.login.email')}
                                    className="!font-semibold !text-navy"
                                />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className={inputClass}
                                    autoComplete="username"
                                    placeholder={t('auth.login.emailPlaceholder')}
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                <InputError message={messageText(t, errors.email) || errors.email} className="mt-2" />
                            </div>

                            <div>
                                <div className="flex items-center justify-between gap-3">
                                    <InputLabel
                                        htmlFor="password"
                                        value={t('auth.login.password')}
                                        className="!font-semibold !text-navy"
                                    />
                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="text-xs font-semibold text-brand-blue hover:text-navy focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-brand-bright"
                                        >
                                            {t('auth.login.forgot')}
                                        </Link>
                                    )}
                                </div>
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className={inputClass}
                                    autoComplete="current-password"
                                    placeholder={t('auth.login.passwordPlaceholder')}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                />
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <label className="flex items-center gap-2.5">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    className="rounded border-navy/25 text-navy focus:ring-brand-bright"
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                <span className="text-sm text-brand-mute">{t('auth.login.remember')}</span>
                            </label>

                            <button
                                type="submit"
                                className="btn-navy btn-cta w-full rounded-xl px-4 py-3 text-sm shadow-soft hover:bg-navy-600"
                                style={school ? { backgroundColor: primary, borderColor: primary } : undefined}
                                disabled={processing}
                            >
                                {t('auth.login.submit')}
                            </button>
                        </form>

                        <p className="mt-8 text-center text-sm text-brand-mute">
                            <Link
                                href={school?.home_url || '/'}
                                className="font-semibold text-navy hover:text-brand-blue focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-brand-bright"
                            >
                                {school ? t('auth.login.backSite') : t('auth.login.backHome')}
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
