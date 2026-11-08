import { Card, Field, Input } from '@/Components/SchoolOS/Form';
import ModuleLayout from '@/Components/SchoolOS/ModuleLayout';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, useForm } from '@inertiajs/react';
import { reportingShell } from './shell';

export default function Grading({ nav, user, school, academicYear, tabs = [], settings = {}, bands = [], scaleUrl }) {
    const { t } = useLocale();
    const { data, setData, post, processing } = useForm({
        show_position: !!settings.show_position,
        show_photo: settings.show_photo !== false,
        show_dob: !!settings.show_dob,
        term_weights: {
            1: settings.term_weights?.['1'] ?? settings.term_weights?.[1] ?? '',
            2: settings.term_weights?.['2'] ?? settings.term_weights?.[2] ?? '',
            3: settings.term_weights?.['3'] ?? settings.term_weights?.[3] ?? '',
        },
    });

    return (
        <ModuleLayout
            ns="rpt"
            title={t('rpt.grade.title')}
            subtitle={t('rpt.grade.subtitle')}
            tabs={tabs}
            current="grading"
            shell={reportingShell({ nav, user, school, academicYear })}
            actions={
                <Link href={scaleUrl} className="btn-outline">
                    {t('rpt.grade.openScale')}
                </Link>
            }
        >
            <Card title={t('rpt.grade.weights')} className="mb-6">
                <p className="mb-4 text-sm text-slate-500">{t('rpt.grade.weightsHint')}</p>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('reporting.grading.save'));
                    }}
                >
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[1, 2, 3].map((n) => (
                            <Field key={n} label={t('rpt.grade.term', { n })}>
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={data.term_weights[n]}
                                    onChange={(e) => setData('term_weights', { ...data.term_weights, [n]: e.target.value })}
                                />
                            </Field>
                        ))}
                    </div>
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={!!data.show_position} onChange={(e) => setData('show_position', e.target.checked)} />
                        {t('rpt.grade.showPosition')}
                    </label>
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={!!data.show_photo} onChange={(e) => setData('show_photo', e.target.checked)} />
                        {t('rpt.grade.showPhoto')}
                    </label>
                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" checked={!!data.show_dob} onChange={(e) => setData('show_dob', e.target.checked)} />
                        {t('rpt.grade.showDob')}
                    </label>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                </form>
            </Card>

            <Card title={t('rpt.grade.currentScale')}>
                <table className="min-w-full text-sm">
                    <thead className="text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-2 py-2 text-start">{t('rpt.col.grade')}</th>
                            <th className="px-2 py-2 text-start">{t('results.settings.minScore')}</th>
                            <th className="px-2 py-2 text-start">{t('results.col.remark')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {bands.map((b) => (
                            <tr key={b.letter} className="border-t border-slate-100">
                                <td className="px-2 py-2 font-semibold">{b.letter}</td>
                                <td className="px-2 py-2">{b.min_score}</td>
                                <td className="px-2 py-2">{b.remark}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </ModuleLayout>
    );
}
