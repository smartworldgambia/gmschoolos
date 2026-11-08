import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

/**
 * App shell + heading + a tab bar for an operations module (library, inventory, transport...).
 * `tabs` is a list of `{ key, route }`; each tab label is `t(`${ns}.tab.${key}`)`, so every module keeps its labels in its own catalog.
 */
export default function ModuleLayout({ ns, title, subtitle, actions, tabs = [], current, shell = {}, children }) {
    const { t } = useLocale();

    return (
        <AppShell title={title} nav={shell.nav} user={shell.user} school={shell.school} academicYear={shell.academicYear}>
            <Head title={title} />
            <PageHeader title={title} subtitle={subtitle} actions={actions} />
            {tabs.length > 1 ? (
                <nav className="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label={t(`${ns}.tabs.label`)}>
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={route(tab.route)}
                            className={`whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium ${
                                tab.key === current ? 'border-teal-600 text-navy' : 'border-transparent text-slate-500 hover:text-navy'
                            }`}
                            aria-current={tab.key === current ? 'page' : undefined}
                        >
                            {t(`${ns}.tab.${tab.key}`)}
                        </Link>
                    ))}
                </nav>
            ) : null}
            {children}
        </AppShell>
    );
}
