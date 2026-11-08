import ApplicationFields, { emptyApplication } from '@/Components/School/ApplicationFields';
import { ErrorSummary } from '@/Components/SchoolOS/Form';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useLocale } from '@/i18n/LocaleProvider';
import { Head, Link, useForm } from '@inertiajs/react';

/** Public online application. Creates a reviewable application only: no account, no student record. */
export default function Apply({ school, classes = [], documentTypes = [], submitUrl, siteUrl, applied = null, legal = {} }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, transform, reset } = useForm({
        ...emptyApplication,
        consent: false,
        company_website: '', // honeypot: real visitors never see or fill this
    });
    transform((d) => ({ ...d, documents: d.documents.filter((x) => x.file) }));

    function submit(e) {
        e.preventDefault();
        post(submitUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => reset() });
    }

    return (
        <div className="min-h-screen bg-surface">
            <Head title={`${t('apply.title')} — ${school.name}`} />
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-4">
                    <div className="min-w-0">
                        <p className="truncate text-lg font-bold text-navy">{school.name}</p>
                        <p className="text-sm text-slate-500">{t('apply.title')}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <LanguageSwitcher compact />
                        <Link href={siteUrl} className="btn-outline">
                            {t('apply.backToSite')}
                        </Link>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-4xl px-4 py-8">
                {applied ? (
                    <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900" role="status">
                        <p className="font-semibold">{t('apply.received')}</p>
                        <p className="mt-1 text-sm">{t('apply.receivedBody')}</p>
                        {applied !== 'ADM-RECEIVED' ? (
                            <p className="mt-2 text-sm">
                                {t('apply.reference')}: <span className="font-mono font-semibold">{applied}</span>
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <p className="mb-6 text-sm text-slate-600">{t('apply.intro')}</p>

                <form onSubmit={submit} className="space-y-6">
                    <ErrorSummary errors={errors} />
                    <ApplicationFields data={data} setData={setData} errors={errors} classes={classes} documentTypes={documentTypes} />

                    {/* honeypot */}
                    <div className="absolute -start-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                        <label>
                            Website
                            <input type="text" tabIndex={-1} autoComplete="off" value={data.company_website} onChange={(e) => setData('company_website', e.target.value)} />
                        </label>
                    </div>

                    <label className="flex items-start gap-2 rounded-xl bg-white p-4 text-sm text-slate-700 shadow-sm">
                        <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 rounded border-gray-300" />
                        <span>
                            {t('apply.consent')}{' '}
                            {legal.privacy ? (
                                <a href={legal.privacy} className="text-teal underline" target="_blank" rel="noreferrer">
                                    {t('apply.privacy')}
                                </a>
                            ) : null}
                        </span>
                    </label>
                    {errors.consent ? <p className="text-sm text-red-600">{t('apply.consentRequired')}</p> : null}

                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('apply.submit')}
                    </button>
                    <p className="text-xs text-slate-500">{t('apply.noFee')}</p>
                </form>
            </main>
        </div>
    );
}
