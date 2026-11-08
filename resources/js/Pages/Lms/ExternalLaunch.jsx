import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, usePage } from '@inertiajs/react';

export default function ExternalLaunch({ lesson = {}, launchUrl }) {
    const { t } = useLocale();
    const { nav = [], user, school, academicYear } = usePage().props;

    return (
        <AppShell title={t('lms.ext.launchTitle')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={`${t('lms.ext.launchTitle')} · ${lesson.title ?? ''}`} />
            <PageHeader title={lesson.title ?? t('lms.ext.title')} subtitle={lesson.course ?? ''} />
            <div className="max-w-xl rounded-xl bg-white p-6 shadow-sm">
                <p className="text-sm text-slate-600">{t('lms.ext.disclaimer')}</p>
                <a href={launchUrl} target="_blank" rel="noreferrer noopener" className="btn-teal mt-4 inline-flex">
                    {t('lms.ext.open')}
                </a>
                <p className="mt-3 break-all text-xs text-slate-400" dir="ltr">
                    {launchUrl}
                </p>
                <Link href={route('lms.courses.index')} className="mt-6 inline-block text-sm font-medium text-teal hover:underline">
                    {t('lms.common.back')}
                </Link>
            </div>
        </AppShell>
    );
}
