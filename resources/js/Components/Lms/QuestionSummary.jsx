import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';

/** Read-only view of an authored question for staff (the key is shown: never render this for learners). */
export default function QuestionSummary({ q, index }) {
    const { t } = useLocale();

    return (
        <div className="min-w-0 flex-1">
            <p className="text-xs text-slate-500">
                {index !== undefined ? `${index + 1}. ` : ''}
                {enumLabel(t, 'lms.qtype', q.type)} · {q.points} {t('lms.q.pointsShort')}
            </p>
            <p className="mt-1 whitespace-pre-wrap text-sm font-medium text-navy">{q.prompt}</p>
            {q.type === 'multiple_choice' ? (
                <ul className="mt-1 space-y-0.5 text-sm">
                    {(q.options ?? []).map((o, i) => (
                        <li key={i} className={o === q.correct_answer ? 'font-semibold text-emerald-700' : 'text-slate-600'}>
                            {o === q.correct_answer ? '✓ ' : '• '}
                            {o}
                        </li>
                    ))}
                </ul>
            ) : null}
            {q.type === 'true_false' ? (
                <p className="mt-1 text-sm text-emerald-700">
                    ✓ {q.correct_answer === 'true' ? t('lms.q.true') : t('lms.q.false')}
                </p>
            ) : null}
            {q.type === 'short_answer' ? (
                <p className="mt-1 text-sm text-emerald-700">
                    ✓ {(q.accepted_answers ?? []).join(' / ')}
                    {q.case_sensitive ? ` (${t('lms.q.caseSensitiveShort')})` : ''}
                </p>
            ) : null}
            {q.type === 'essay' ? <p className="mt-1 text-sm text-slate-500">{t('lms.q.manualMark')}</p> : null}
        </div>
    );
}
