import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { Head } from '@inertiajs/react';

/**
 * Public document verification (the page a QR code opens). It deliberately shows very little:
 * type, reference, school, issue date, validity and the holder's initials. No marks, no full name.
 */
export default function Show({ result }) {
    const { t } = useLocale();
    const state = result.state;
    const tone =
        state === 'valid'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
            : state === 'revoked' || state === 'superseded'
              ? 'border-red-200 bg-red-50 text-red-900'
              : 'border-amber-200 bg-amber-50 text-amber-900';

    return (
        <div className="min-h-screen bg-surface">
            <Head title={t('ver.title')} />
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-4">
                    <p className="text-lg font-bold text-navy">{t('ver.title')}</p>
                    <LanguageSwitcher compact />
                </div>
            </header>

            <main className="mx-auto max-w-2xl px-4 py-8">
                <div className={`rounded-xl border p-5 ${tone}`} role="status">
                    <p className="text-lg font-semibold">{t(`ver.state.${state}`)}</p>
                    <p className="mt-1 text-sm">{t(`ver.stateHelp.${state}`)}</p>
                </div>

                {state !== 'not_found' ? (
                    <dl className="mt-6 grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 rounded-xl bg-white p-5 text-sm shadow-sm">
                        <dt className="text-slate-500">{t('rec.col.type')}</dt>
                        <dd className="font-medium text-navy">{enumLabel(t, 'rec.type', result.type)}</dd>
                        <dt className="text-slate-500">{t('rec.col.reference')}</dt>
                        <dd className="font-mono" dir="ltr">
                            {result.reference}
                        </dd>
                        <dt className="text-slate-500">{t('ver.school')}</dt>
                        <dd>{result.school}</dd>
                        <dt className="text-slate-500">{t('rec.col.issueDate')}</dt>
                        <dd>{result.issue_date ?? '—'}</dd>
                        <dt className="text-slate-500">{t('ver.holderInitials')}</dt>
                        <dd className="font-semibold" dir="ltr">
                            {result.initials || '—'}
                        </dd>
                        {state === 'revoked' && result.revoked_on ? (
                            <>
                                <dt className="text-slate-500">{t('ver.revokedOn')}</dt>
                                <dd className="text-red-700">{result.revoked_on}</dd>
                            </>
                        ) : null}
                    </dl>
                ) : null}

                <p className="mt-6 rounded-lg border border-slate-200 bg-white p-4 text-xs text-slate-600">{t('ver.disclaimer')}</p>
                <p className="mt-2 text-xs text-slate-500">{t('ver.privacy')}</p>
            </main>
        </div>
    );
}
