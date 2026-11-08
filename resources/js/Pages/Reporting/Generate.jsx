import { Card, Select } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { reportingShell } from './shell';

export default function Generate({ nav, user, school, academicYear, tabs = [], can = {}, type = 'termly', preview = [], selectedClass, selectedTerm, filters = {} }) {
    const { t } = useLocale();
    const readyIds = useMemo(() => preview.filter((s) => !s.incomplete).map((s) => s.id), [preview]);
    const { data, setData, post, processing } = useForm({
        type,
        academic_year_id: filters.academic_year_id ?? '',
        term_id: selectedTerm ?? filters.term_id ?? '',
        student_ids: readyIds,
        class_teacher_comment: '',
        allow_incomplete: false,
    });

    useEffect(() => {
        setData({
            type,
            academic_year_id: filters.academic_year_id ?? '',
            term_id: selectedTerm ?? filters.term_id ?? '',
            student_ids: readyIds,
        });
    }, [type, filters.academic_year_id, selectedTerm, filters.term_id, readyIds]);

    function reload(params) {
        router.get(route('reporting.generate'), { type: params.type ?? type, academic_year_id: params.academic_year_id ?? data.academic_year_id, term_id: params.term_id ?? data.term_id, class_id: params.class_id ?? selectedClass }, { preserveState: true, replace: true });
    }

    function toggle(id, checked) {
        setData('student_ids', checked ? [...data.student_ids, id] : data.student_ids.filter((x) => x !== id));
    }

    return (
        <ModuleLayout ns="rpt" title={t('rpt.gen.title')} subtitle={t('rpt.gen.subtitle')} tabs={tabs} current={type === 'annual' ? 'annual' : type === 'final' ? 'final' : 'termly'} shell={reportingShell({ nav, user, school, academicYear })}>
            <p className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('rpt.gen.incompleteHint')}</p>

            <div className="mb-5 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">{t('rpt.col.type')}</label>
                    <Select value={type} onChange={(e) => reload({ type: e.target.value })}>
                        {['termly', 'annual', 'final'].map((x) => (
                            <option key={x} value={x}>
                                {t(`rpt.type.${x}`)}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">{t('rpt.col.year')}</label>
                    <Select value={data.academic_year_id} onChange={(e) => reload({ academic_year_id: e.target.value })}>
                        {(filters.years ?? []).map((y) => (
                            <option key={y.id} value={y.id}>
                                {y.name}
                            </option>
                        ))}
                    </Select>
                </div>
                {type === 'termly' ? (
                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">{t('rpt.col.term')}</label>
                        <Select value={data.term_id} onChange={(e) => reload({ term_id: e.target.value })}>
                            {(filters.terms ?? []).map((term) => (
                                <option key={term.id} value={term.id}>
                                    {term.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                ) : null}
                <div>
                    <label className="mb-1 block text-sm font-medium text-slate-700">{t('rpt.col.class')}</label>
                    <Select value={selectedClass ?? ''} onChange={(e) => reload({ class_id: e.target.value })}>
                        <option value="">{t('rpt.emptyClass')}</option>
                        {(filters.classes ?? []).map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </Select>
                </div>
            </div>

            <Card
                title={t('rpt.gen.step3')}
                actions={
                    preview.length > 0 ? (
                        <button type="button" className="btn-outline" onClick={() => setData('student_ids', readyIds)}>
                            {t('rpt.gen.selectAll')}
                        </button>
                    ) : null
                }
            >
                {preview.length === 0 ? (
                    <p className="text-sm text-slate-500">{selectedClass ? t('rpt.gen.none') : t('rpt.emptyClass')}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-2 py-2 text-start" />
                                    <th className="px-2 py-2 text-start">{t('rpt.col.student')}</th>
                                    <th className="px-2 py-2 text-start">{t('rpt.col.number')}</th>
                                    <th className="px-2 py-2 text-start">{t('rpt.col.average')}</th>
                                    <th className="px-2 py-2 text-start">{t('rpt.col.status')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preview.map((s) => (
                                    <tr key={s.id} className="border-t border-slate-100">
                                        <td className="px-2 py-2">
                                            <input type="checkbox" checked={data.student_ids.includes(s.id)} onChange={(e) => toggle(s.id, e.target.checked)} aria-label={s.name} />
                                        </td>
                                        <td className="px-2 py-2 font-medium text-navy">{s.name}</td>
                                        <td className="px-2 py-2 font-mono text-xs" dir="ltr">
                                            {s.student_number}
                                        </td>
                                        <td className="px-2 py-2">{s.average ?? '—'}</td>
                                        <td className="px-2 py-2">
                                            {s.incomplete ? <span className="text-amber-700">{t('rpt.incomplete')}</span> : <span className="text-emerald-700">{t('rpt.complete')}</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {can.prepare ? (
                <form
                    className="mt-4 flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('reporting.store'));
                    }}
                >
                    <button type="submit" className="btn-teal" disabled={processing || data.student_ids.length === 0}>
                        {t('rpt.gen.run')}
                    </button>
                </form>
            ) : null}
        </ModuleLayout>
    );
}
