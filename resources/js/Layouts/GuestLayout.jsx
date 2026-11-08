import BrandLogo from '@/Components/Marketing/BrandLogo';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useLocale } from '@/i18n/LocaleProvider';

/**
 * Shared guest/auth shell.
 * - variant="card" (default): centered brand + form card for Forgot/Register/etc.
 * - variant="bare": full-bleed children (Login split composition owns its layout).
 */
export default function GuestLayout({ children, variant = 'card' }) {
    const { t } = useLocale();

    return (
        <div className="auth-shell relative flex min-h-screen flex-col overflow-hidden">
            <div
                className="pointer-events-none absolute inset-0 -z-10"
                aria-hidden="true"
            >
                <div className="absolute inset-0 bg-gradient-to-br from-navy-50 via-surface to-teal-50/40" />
                <div className="auth-shell-grid absolute inset-0 opacity-[0.45]" />
                <div className="absolute -start-24 top-[-10%] h-[28rem] w-[28rem] rounded-full bg-brand-bright/15 blur-3xl" />
                <div className="absolute -end-16 bottom-[-8%] h-[22rem] w-[22rem] rounded-full bg-teal-400/20 blur-3xl" />
            </div>

            <div className="absolute end-4 top-4 z-20 sm:end-6 sm:top-6">
                <LanguageSwitcher />
            </div>

            {variant === 'bare' ? (
                <div className="relative flex min-h-screen flex-1 flex-col">{children}</div>
            ) : (
                <div className="relative flex flex-1 flex-col items-center justify-center px-4 py-16 sm:px-6">
                    <BrandLogo
                        className="mb-8"
                        label={t('auth.brand.name')}
                        ariaLabel={t('auth.brand.homeAria')}
                    />
                    <div className="w-full max-w-md rounded-2xl border border-navy/8 bg-white/95 px-6 py-8 shadow-card backdrop-blur-sm sm:px-8">
                        {children}
                    </div>
                </div>
            )}
        </div>
    );
}
