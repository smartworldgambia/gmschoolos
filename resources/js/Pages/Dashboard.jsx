import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({
    home,
    links = [],
    message,
    nav = [],
    user,
    school,
    academicYear,
}) {
    const { t } = useLocale();
    const roleLinks =
        links.length > 0
            ? links
            : [
                  home?.admin ? { key: 'admin', href: home.admin } : null,
                  home?.teacher ? { key: 'teacher', href: home.teacher } : null,
                  home?.student ? { key: 'student', href: home.student } : null,
                  home?.parent ? { key: 'parent', href: home.parent } : null,
                  home?.fees ? { key: 'fees', href: home.fees } : null,
                  home?.academics ? { key: 'academics', href: home.academics } : null,
                  home?.lms ? { key: 'lms', href: home.lms } : null,
              ].filter(Boolean);

    const fromArray = Array.isArray(home) ? home : roleLinks;

    return (
        <AppShell title={t('dash.home.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('dash.home.title')} />

            <PageHeader title={t('dash.home.welcome')} subtitle={message ?? t('dash.home.subtitle')} />

            {fromArray.length === 0 ? (
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <p className="text-sm text-slate-600">{t('dash.home.empty')}</p>
                    {home && typeof home === 'string' ? (
                        <Link href={home} className="btn-teal mt-4 inline-flex whitespace-nowrap">
                            {t('dash.home.continue')}
                        </Link>
                    ) : null}
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {fromArray.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="rounded-xl bg-white p-5 shadow-sm transition hover:ring-2 hover:ring-teal/30"
                        >
                            <p className="text-base font-semibold text-navy">
                                {link.key ? t(`dash.home.link.${link.key}`) : (link.label ?? link.name)}
                            </p>
                            {link.description ? (
                                <p className="mt-1 text-sm text-slate-500">{link.description}</p>
                            ) : (
                                <p className="mt-1 text-sm text-teal">{t('dash.home.open')}</p>
                            )}
                        </Link>
                    ))}
                </div>
            )}
        </AppShell>
    );
}
