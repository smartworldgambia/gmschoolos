import { Card } from '@/Components/SchoolOS/Form';
import InputError from '@/Components/InputError';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel, messageText } from '@/lib/academic';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Import({ batches = [], templateUrl, storeUrl, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset } = useForm({ file: null });
    const [open, setOpen] = useState(null);

    useEffect(() => {
        const pending = batches.some((b) => b.status === 'pending' || b.status === 'processing');
        if (!pending) return undefined;
        const id = setInterval(() => router.reload({ only: ['batches'] }), 3000);
        return () => clearInterval(id);
    }, [batches]);

    function submit(e) {
        e.preventDefault();
        post(storeUrl, { forceFormData: true, onSuccess: () => reset('file') });
    }

    return (
        <AppShell title={t('import.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('import.title')} />
            <PageHeader
                title={t('import.title')}
                subtitle={t('import.subtitle')}
                actions={
                    <>
                        <a href={templateUrl} className="btn-outline">
                            {t('import.template')}
                        </a>
                        <Link href={route('students.index')} className="btn-outline">
                            {t('acad.common.back')}
                        </Link>
                    </>
                }
            />

            <Card title={t('import.rulesTitle')} className="mb-6">
                <ul className="list-disc space-y-1 ps-5 text-sm text-slate-600">
                    <li>{t('import.rule.number')}</li>
                    <li>{t('import.rule.dates')}</li>
                    <li>{t('import.rule.duplicates')}</li>
                    <li>{t('import.rule.report')}</li>
                </ul>
            </Card>

            <form onSubmit={submit} className="mb-6 rounded-xl bg-white p-5 shadow-sm">
                <input type="file" accept=".csv,text/csv" onChange={(e) => setData('file', e.target.files[0])} className="block w-full text-sm" />
                <InputError message={messageText(t, errors.file)} className="mt-2" />
                <button type="submit" className="btn-teal mt-4" disabled={processing || !data.file}>
                    {t('import.upload')}
                </button>
            </form>

            <div className="space-y-3">
                {batches.length === 0 ? <p className="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">{t('import.none')}</p> : null}
                {batches.map((b) => (
                    <div key={b.id} className="rounded-xl bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-medium text-navy">{b.original_filename}</p>
                                <p className="text-xs text-slate-500">{b.created_at}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-4 text-sm">
                                <StatusBadge status={b.status} label={enumLabel(t, 'importStatus', b.status)} />
                                <span className="text-emerald-700">
                                    {t('import.imported')}: {b.success_rows ?? 0}
                                </span>
                                <span className="text-red-600">
                                    {t('import.rejected')}: {b.failed_rows ?? 0}
                                </span>
                                {(b.errors ?? []).length > 0 ? (
                                    <button type="button" className="text-teal hover:underline" onClick={() => setOpen(open === b.id ? null : b.id)}>
                                        {open === b.id ? t('import.hideErrors') : t('import.showErrors', { n: b.errors.length })}
                                    </button>
                                ) : null}
                            </div>
                        </div>
                        {open === b.id ? (
                            <div className="mt-3 max-h-72 overflow-y-auto border-t border-slate-100 pt-3">
                                <table className="min-w-full text-start text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase text-slate-500">
                                            <th className="px-2 py-1 text-start">{t('import.row')}</th>
                                            <th className="px-2 py-1 text-start">{t('import.problem')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {b.errors.map((e, i) => (
                                            <tr key={i} className="border-t border-slate-50">
                                                <td className="px-2 py-1 text-slate-600">{e.row ?? '—'}</td>
                                                <td className="px-2 py-1 text-red-700">{messageText(t, e.message)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : null}
                    </div>
                ))}
            </div>
        </AppShell>
    );
}
