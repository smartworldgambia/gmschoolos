import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import { useForm } from '@inertiajs/react';

const blankRules = {
    direction: 'lower_better',
    subjects: [{ code: '', name: '' }],
    grade_points: [{ grade: '', points: '' }],
    required_subjects: [],
    weights: [],
    combinations: [],
    pass_limit: '',
    elective_count: '',
    min_subjects: '',
    aggregate_threshold: '',
};

function setAt(list, index, patch) {
    return list.map((row, i) => (i === index ? { ...row, ...patch } : row));
}

function without(list, index) {
    return list.filter((_, i) => i !== index);
}

/**
 * Configurable admissions rules for one Senior Secondary round. Nothing here is tied to a named school:
 * subjects, grade points, thresholds, weights and programme combinations are all entered by the school.
 */
export default function SeniorRoundForm({ round = null, url, method = 'post', onDone }) {
    const { t } = useLocale();
    const rules = { ...blankRules, ...(round?.rules ?? {}) };
    const { data, setData, post, put, processing, errors } = useForm({
        name: round?.name ?? '',
        instructions: round?.instructions ?? '',
        opens_on: round?.opens_on ?? '',
        closes_on: round?.closes_on ?? '',
        capacity: round?.capacity ?? '',
        waitlist_capacity: round?.waitlist_capacity ?? 0,
        rules: {
            ...rules,
            subjects: rules.subjects?.length ? rules.subjects : blankRules.subjects,
            grade_points: rules.grade_points?.length ? rules.grade_points : blankRules.grade_points,
        },
    });
    const r = data.rules;
    const setRules = (patch) => setData('rules', { ...r, ...patch });
    const codes = r.subjects.map((s) => s.code).filter(Boolean);

    function submit(e) {
        e.preventDefault();
        (method === 'put' ? put : post)(url, { preserveScroll: true, onSuccess: onDone });
    }

    function toggleIn(list, value) {
        return list.includes(value) ? list.filter((x) => x !== value) : [...list, value];
    }

    return (
        <form onSubmit={submit} className="space-y-6">
            <ErrorSummary errors={errors} />

            <Card title={t('ss.form.basics')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('ss.col.name')} className="lg:col-span-2">
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required maxLength={150} />
                    </Field>
                    <Field label={t('ss.col.opens')}>
                        <Input type="date" value={data.opens_on} onChange={(e) => setData('opens_on', e.target.value)} />
                    </Field>
                    <Field label={t('ss.col.closes')}>
                        <Input type="date" value={data.closes_on} onChange={(e) => setData('closes_on', e.target.value)} />
                    </Field>
                    <Field label={t('ss.col.capacity')}>
                        <Input type="number" min="0" value={data.capacity} onChange={(e) => setData('capacity', e.target.value)} required />
                    </Field>
                    <Field label={t('ss.col.waitlistCapacity')}>
                        <Input type="number" min="0" value={data.waitlist_capacity} onChange={(e) => setData('waitlist_capacity', e.target.value)} />
                    </Field>
                    <Field label={t('ss.col.instructions')} className="sm:col-span-2">
                        <Textarea rows={2} value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} maxLength={3000} />
                    </Field>
                </div>
            </Card>

            <Card title={t('ss.form.scoring')}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('ss.rules.direction')} hint={t('ss.rules.directionHelp')}>
                        <Select value={r.direction} onChange={(e) => setRules({ direction: e.target.value })}>
                            <option value="lower_better">{t('ss.rules.lowerBetter')}</option>
                            <option value="higher_better">{t('ss.rules.higherBetter')}</option>
                        </Select>
                    </Field>
                    <Field label={t('ss.rules.threshold')} hint={t('ss.rules.thresholdHelp')}>
                        <Input type="number" step="any" value={r.aggregate_threshold ?? ''} onChange={(e) => setRules({ aggregate_threshold: e.target.value })} />
                    </Field>
                    <Field label={t('ss.rules.passLimit')} hint={t('ss.rules.passLimitHelp')}>
                        <Input type="number" step="any" value={r.pass_limit ?? ''} onChange={(e) => setRules({ pass_limit: e.target.value })} />
                    </Field>
                    <Field label={t('ss.rules.minSubjects')}>
                        <Input type="number" min="0" value={r.min_subjects ?? ''} onChange={(e) => setRules({ min_subjects: e.target.value })} />
                    </Field>
                    <Field label={t('ss.rules.electiveCount')} hint={t('ss.rules.electiveHelp')}>
                        <Input type="number" min="0" value={r.elective_count ?? ''} onChange={(e) => setRules({ elective_count: e.target.value })} />
                    </Field>
                </div>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card
                    title={t('ss.rules.subjects')}
                    actions={
                        <button type="button" className="btn-outline" onClick={() => setRules({ subjects: [...r.subjects, { code: '', name: '' }] })}>
                            {t('acad.common.add')}
                        </button>
                    }
                >
                    <div className="space-y-2">
                        {r.subjects.map((s, i) => (
                            <div key={i} className="grid grid-cols-[7rem_1fr_auto] items-center gap-2">
                                <Input dir="ltr" placeholder={t('acad.common.code')} value={s.code} onChange={(e) => setRules({ subjects: setAt(r.subjects, i, { code: e.target.value.toUpperCase() }) })} maxLength={30} />
                                <Input placeholder={t('ss.rules.subjectName')} value={s.name} onChange={(e) => setRules({ subjects: setAt(r.subjects, i, { name: e.target.value }) })} maxLength={150} />
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setRules({ subjects: without(r.subjects, i) })}>
                                    {t('acad.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card
                    title={t('ss.rules.gradePoints')}
                    actions={
                        <button type="button" className="btn-outline" onClick={() => setRules({ grade_points: [...r.grade_points, { grade: '', points: '' }] })}>
                            {t('acad.common.add')}
                        </button>
                    }
                >
                    <p className="mb-2 text-xs text-slate-500">{t('ss.rules.gradePointsHelp')}</p>
                    <div className="space-y-2">
                        {r.grade_points.map((g, i) => (
                            <div key={i} className="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
                                <Input dir="ltr" placeholder={t('ss.rules.grade')} value={g.grade} onChange={(e) => setRules({ grade_points: setAt(r.grade_points, i, { grade: e.target.value.toUpperCase() }) })} maxLength={12} />
                                <Input type="number" step="any" placeholder={t('ss.rules.points')} value={g.points} onChange={(e) => setRules({ grade_points: setAt(r.grade_points, i, { points: e.target.value }) })} />
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setRules({ grade_points: without(r.grade_points, i) })}>
                                    {t('acad.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title={t('ss.rules.required')}>
                    <p className="mb-2 text-xs text-slate-500">{t('ss.rules.requiredHelp')}</p>
                    <div className="flex flex-wrap gap-x-4 gap-y-1">
                        {codes.length === 0 ? <span className="text-sm text-slate-500">{t('ss.rules.addSubjectsFirst')}</span> : null}
                        {codes.map((c) => (
                            <label key={c} className="flex items-center gap-1.5 text-sm">
                                <input type="checkbox" className="rounded border-gray-300" checked={r.required_subjects.includes(c)} onChange={() => setRules({ required_subjects: toggleIn(r.required_subjects, c) })} />
                                <span dir="ltr">{c}</span>
                            </label>
                        ))}
                    </div>
                </Card>

                <Card
                    title={t('ss.rules.weights')}
                    actions={
                        <button type="button" className="btn-outline" onClick={() => setRules({ weights: [...r.weights, { code: codes[0] ?? '', weight: 1 }] })}>
                            {t('acad.common.add')}
                        </button>
                    }
                >
                    <p className="mb-2 text-xs text-slate-500">{t('ss.rules.weightsHelp')}</p>
                    <div className="space-y-2">
                        {r.weights.map((w, i) => (
                            <div key={i} className="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
                                <Select value={w.code} onChange={(e) => setRules({ weights: setAt(r.weights, i, { code: e.target.value }) })}>
                                    <option value="">{t('acad.common.choose')}</option>
                                    {codes.map((c) => (
                                        <option key={c} value={c}>
                                            {c}
                                        </option>
                                    ))}
                                </Select>
                                <Input type="number" step="any" value={w.weight} onChange={(e) => setRules({ weights: setAt(r.weights, i, { weight: e.target.value }) })} />
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setRules({ weights: without(r.weights, i) })}>
                                    {t('acad.common.remove')}
                                </button>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            <Card
                title={t('ss.rules.combinations')}
                actions={
                    <button type="button" className="btn-outline" onClick={() => setRules({ combinations: [...r.combinations, { name: '', subjects: [], min: 1 }] })}>
                        {t('acad.common.add')}
                    </button>
                }
            >
                <p className="mb-3 text-xs text-slate-500">{t('ss.rules.combinationsHelp')}</p>
                <div className="space-y-3">
                    {r.combinations.map((c, i) => (
                        <div key={i} className="rounded-lg border border-slate-200 p-3">
                            <div className="grid gap-2 sm:grid-cols-[1fr_8rem_auto]">
                                <Input placeholder={t('ss.rules.programmeName')} value={c.name ?? ''} onChange={(e) => setRules({ combinations: setAt(r.combinations, i, { name: e.target.value }) })} maxLength={100} />
                                <Input type="number" min="1" placeholder={t('ss.rules.minOf')} value={c.min ?? ''} onChange={(e) => setRules({ combinations: setAt(r.combinations, i, { min: e.target.value }) })} />
                                <button type="button" className="text-xs text-red-600 hover:underline" onClick={() => setRules({ combinations: without(r.combinations, i) })}>
                                    {t('acad.common.remove')}
                                </button>
                            </div>
                            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                {codes.map((code) => (
                                    <label key={code} className="flex items-center gap-1.5 text-sm">
                                        <input type="checkbox" className="rounded border-gray-300" checked={(c.subjects ?? []).includes(code)} onChange={() => setRules({ combinations: setAt(r.combinations, i, { subjects: toggleIn(c.subjects ?? [], code) }) })} />
                                        <span dir="ltr">{code}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>

            <button type="submit" className="btn-teal" disabled={processing}>
                {t('ss.form.save')}
            </button>
        </form>
    );
}
