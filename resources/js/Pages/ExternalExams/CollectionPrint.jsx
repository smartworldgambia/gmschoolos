import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Head } from '@inertiajs/react';

/** Printable register. No application shell: it is meant to be printed (or saved as PDF from the browser). */
export default function CollectionPrint({ rows = [], tab = 'results', printedAt, printedBy, school = {}, sessions = [], sessionId }) {
    const { t } = useLocale();
    const isResults = tab === 'results';
    const session = sessions.find((s) => s.id === sessionId);

    return (
        <div className="mx-auto max-w-5xl bg-white p-6 text-sm text-slate-900 print:max-w-none print:p-0">
            <Head title={t('col.printTitle')} />
            <style>{`@media print { .no-print { display: none !important; } @page { size: A4 landscape; margin: 12mm; } }`}</style>

            <div className="no-print mb-4 flex gap-2">
                <button type="button" className="btn-navy" onClick={() => window.print()}>
                    {t('col.printNow')}
                </button>
                <button type="button" className="btn-outline" onClick={() => window.close()}>
                    {t('col.close')}
                </button>
            </div>

            <header className="mb-4 border-b border-slate-300 pb-3">
                <h1 className="text-xl font-bold">{school.name}</h1>
                <p className="text-base font-semibold">{isResults ? t('col.printResults') : t('col.printCertificates')}</p>
                {session ? (
                    <p>
                        {session.name} ({session.exam_year})
                    </p>
                ) : null}
                <p className="text-xs text-slate-600">
                    {t('col.printedOn')} {printedAt} · {t('col.printedBy')} {printedBy}
                </p>
            </header>

            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr className="bg-slate-100">
                        <th className="border border-slate-300 px-2 py-1.5 text-start">#</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('ext.col.student')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('ext.col.officialIndex')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('ext.col.status')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('col.received')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('col.collectedOn')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('col.collector')}</th>
                        <th className="border border-slate-300 px-2 py-1.5 text-start">{t('col.signature')}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r, i) => {
                        const rec = isResults ? r.collection : r.certificate;
                        const st = rec?.status ?? (isResults ? 'outstanding' : 'awaiting');

                        return (
                            <tr key={r.id}>
                                <td className="border border-slate-300 px-2 py-2">{i + 1}</td>
                                <td className="border border-slate-300 px-2 py-2">
                                    {r.name}
                                    <br />
                                    <span className="text-slate-500">{r.student_number}</span>
                                </td>
                                <td className="border border-slate-300 px-2 py-2 font-mono" dir="ltr">
                                    {r.official_index_number ?? '—'}
                                </td>
                                <td className="border border-slate-300 px-2 py-2">{isResults && !rec ? t('col.notReceived') : enumLabel(t, isResults ? 'ext.colStatus' : 'ext.certStatus', st)}</td>
                                <td className="border border-slate-300 px-2 py-2">{rec?.received_on ?? ''}</td>
                                <td className="border border-slate-300 px-2 py-2">{rec?.collected_on ?? ''}</td>
                                <td className="border border-slate-300 px-2 py-2">
                                    {rec?.collector_name ?? ''}
                                    {rec?.collector_id_masked ? (
                                        <span className="block font-mono text-slate-500" dir="ltr">
                                            {rec.collector_id_masked}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="h-10 w-40 border border-slate-300 px-2 py-2" />
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            {rows.length === 0 ? <p className="mt-4 text-center text-slate-500">{t('col.empty')}</p> : null}
        </div>
    );
}
