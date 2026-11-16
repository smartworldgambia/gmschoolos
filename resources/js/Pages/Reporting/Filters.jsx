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
            className="filter-toolbar"
            onSubmit={(e) => {
                e.preventDefault();
                go({});
            }}
        >
            <Select
                value={filters.academic_year_id ?? ''}
                onChange={(e) => go({ academic_year_id: e.target.value || undefined, term_id: undefined })}
                aria-label={t('rpt.col.year')}
            >
                {(filters.years ?? []).map((y) => (
                    <option key={y.id} value={y.id}>
                        {y.name}
                    </option>
                ))}
            </Select>
            <Select
                value={filters.term_id ?? ''}
                onChange={(e) => go({ term_id: e.target.value || undefined })}
                aria-label={t('rpt.col.term')}
            >
                <option value="">{t('rpt.col.term')}</option>
                {(filters.terms ?? []).map((term) => (
                    <option key={term.id} value={term.id}>
                        {term.name}
                    </option>
                ))}
            </Select>
            <Select
                value={filters.level_id ?? ''}
                onChange={(e) => go({ level_id: e.target.value || undefined })}
                aria-label={t('rpt.col.level')}
            >
                <option value="">{t('rpt.col.level')}</option>
                {(filters.levels ?? []).map((level) => (
                    <option key={level.id} value={level.id}>
                        {level.name}
                    </option>
                ))}
            </Select>
            <Select
                value={filters.class_id ?? ''}
                onChange={(e) => go({ class_id: e.target.value || undefined })}
                aria-label={t('rpt.col.class')}
            >
                <option value="">{t('rpt.col.class')}</option>
                {(filters.classes ?? []).map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </Select>
            {extra}
            <div className="filter-toolbar-search">
                <Input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t('rpt.searchPlaceholder')}
                    aria-label={t('rpt.searchPlaceholder')}
                />
                <button type="submit" className="btn-outline shrink-0">
                    {t('acad.common.search')}
                </button>
            </div>
        </form>
    );
}
