import { Input, Select } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function Filters({ action, filters = {}, extra = null, type }) {
    const { t } = useLocale();
    const [q, setQ] = useState(filters.q ?? '');

    function go(params) {
        router.get(
            action,
            {
                academic_year_id: filters.academic_year_id || undefined,
                term_id: filters.term_id || undefined,
                class_id: filters.class_id || undefined,
                level_id: filters.level_id || undefined,
                status: filters.status || undefined,
                q,
                type,
                ...params,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <form
            className="mb-5 flex flex-wrap items-end gap-2 rounded-xl bg-white p-3 shadow-sm"
            onSubmit={(e) => {
                e.preventDefault();
                go({});
            }}
        >
            <Select value={filters.academic_year_id ?? ''} onChange={(e) => go({ academic_year_id: e.target.value || undefined, term_id: undefined })} className="max-w-[12rem]">
                {(filters.years ?? []).map((y) => (
                    <option key={y.id} value={y.id}>
                        {y.name}
                    </option>
                ))}
            </Select>
            <Select value={filters.term_id ?? ''} onChange={(e) => go({ term_id: e.target.value || undefined })} className="max-w-[11rem]">
                <option value="">{t('rpt.col.term')}</option>
                {(filters.terms ?? []).map((term) => (
                    <option key={term.id} value={term.id}>
                        {term.name}
                    </option>
                ))}
            </Select>
            <Select value={filters.level_id ?? ''} onChange={(e) => go({ level_id: e.target.value || undefined })} className="max-w-[11rem]">
                <option value="">{t('rpt.col.level')}</option>
                {(filters.levels ?? []).map((level) => (
                    <option key={level.id} value={level.id}>
                        {level.name}
                    </option>
                ))}
            </Select>
            <Select value={filters.class_id ?? ''} onChange={(e) => go({ class_id: e.target.value || undefined })} className="max-w-[12rem]">
                <option value="">{t('rpt.col.class')}</option>
                {(filters.classes ?? []).map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </Select>
            {extra}
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('rpt.searchPlaceholder')} className="max-w-xs" />
            <button type="submit" className="btn-outline">
                {t('acad.common.search')}
            </button>
        </form>
    );
}
