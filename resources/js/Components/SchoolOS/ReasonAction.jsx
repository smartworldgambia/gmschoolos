import { ErrorSummary, Field, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * A button that opens a small inline form asking for a reason, then posts it.
 * Used for every workflow step that must leave an explanation in the audit trail
 * (correct a grade, revoke a document, reject an application...).
 * `label` is the already-translated button text; `fields` adds extra hidden values to the request.
 */
export default function ReasonAction({ url, method = 'post', label, reasonLabel, tone = 'outline', required = true, fieldName = 'reason', fields = {}, className = '' }) {
    const { t } = useLocale();
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, patch, processing, errors, reset, transform } = useForm({ [fieldName]: '' });
    transform((d) => ({ ...d, ...fields }));

    const send = { post, put, patch }[method] ?? post;
    const toneClass = tone === 'danger' ? 'btn-outline !text-red-700' : tone === 'teal' ? 'btn-teal' : tone === 'navy' ? 'btn-navy' : 'btn-outline';

    function submit(e) {
        e.preventDefault();
        send(url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    }

    if (!open) {
        return (
            <button type="button" className={`${toneClass} ${className}`} onClick={() => setOpen(true)}>
                {label}
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="w-full max-w-md rounded-lg border border-slate-200 bg-slate-50 p-3">
            <ErrorSummary errors={errors} />
            <Field label={reasonLabel ?? t('acad.common.reason')}>
                <Textarea rows={2} value={data[fieldName]} onChange={(e) => setData(fieldName, e.target.value)} required={required} maxLength={500} />
            </Field>
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="submit" className={tone === 'danger' ? 'btn-navy' : 'btn-teal'} disabled={processing}>
                    {label}
                </button>
                <button type="button" className="btn-outline" onClick={() => setOpen(false)}>
                    {t('acad.common.cancel')}
                </button>
            </div>
        </form>
    );
}
