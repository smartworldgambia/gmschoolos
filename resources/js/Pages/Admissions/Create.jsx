import ApplicationFields, { emptyApplication } from '@/Components/School/ApplicationFields';
import { ErrorSummary } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ classes = [], storeUrl, documentTypes = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, transform } = useForm(emptyApplication);
    transform((d) => ({ ...d, documents: d.documents.filter((x) => x.file) }));

    function submit(e) {
        e.preventDefault();
        post(storeUrl, { forceFormData: true });
    }

    return (
        <AppShell title={t('admissions.create.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('admissions.create.title')} />
            <PageHeader
                title={t('admissions.create.title')}
                subtitle={t('admissions.create.subtitle')}
                actions={
                    <Link href={route('admissions.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />
            <form onSubmit={submit} className="space-y-6">
                <ErrorSummary errors={errors} />
                <ApplicationFields data={data} setData={setData} errors={errors} classes={classes} documentTypes={documentTypes} />
                <div className="flex gap-2">
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('admissions.create.submit')}
                    </button>
                    <Link href={route('admissions.index')} className="btn-outline">
                        {t('acad.common.cancel')}
                    </Link>
                </div>
            </form>
        </AppShell>
    );
}
