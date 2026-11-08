import { Card, ErrorSummary, Input, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

function Categories({ categories }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({
        rows: categories.map((c) => ({ id: c.id, name: c.name, code: c.code, type: c.type, weight: c.weight, max_score: c.max_score, is_active: c.is_active })),
    });
    const set = (i, k, v) => setData('rows', data.rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
    const add = () => setData('rows', [...data.rows, { id: null, name: '', code: '', type: 'ca', weight: 0, max_score: 100, is_active: true }]);
    const active = data.rows.filter((r) => r.is_active);
    const sum = active.reduce((a, r) => a + Number(r.weight || 0), 0);

    return (
        <Card title={t('results.settings.categories')} className="mb-6">
            <p className="mb-4 text-sm text-slate-500">{t('results.settings.categoriesHint')}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('examinations.settings.categories'), { preserveScroll: true });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={errors} />
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-2 py-2 text-start">{t('results.col.name')}</th>
                                <th className="px-2 py-2 text-start">{t('results.settings.code')}</th>
                                <th className="px-2 py-2 text-start">{t('results.col.type')}</th>
                                <th className="px-2 py-2 text-start">{t('results.settings.weight')}</th>
                                <th className="px-2 py-2 text-start">{t('results.settings.max')}</th>
                                <th className="px-2 py-2 text-start">{t('results.settings.active')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.rows.map((r, i) => (
                                <tr key={r.id ?? `n${i}`}>
                                    <td className="px-2 py-1">
                                        <Input value={r.name} onChange={(e) => set(i, 'name', e.target.value)} required maxLength={100} />
                                    </td>
                                    <td className="px-2 py-1">
                                        <Input value={r.code} onChange={(e) => set(i, 'code', e.target.value)} required maxLength={30} className="!w-28" />
                                    </td>
                                    <td className="px-2 py-1">
                                        <Select value={r.type} onChange={(e) => set(i, 'type', e.target.value)} className="!w-auto">
                                            <option value="ca">{t('results.settings.type.ca')}</option>
                                            <option value="exam">{t('results.settings.type.exam')}</option>
                                        </Select>
                                    </td>
                                    <td className="px-2 py-1">
                                        <Input type="number" min="0" max="100" step="0.01" value={r.weight} onChange={(e) => set(i, 'weight', e.target.value)} className="!w-24" />
                                    </td>
                                    <td className="px-2 py-1">
                                        <Input type="number" min="1" max="1000" step="0.01" value={r.max_score} onChange={(e) => set(i, 'max_score', e.target.value)} className="!w-24" />
                                    </td>
                                    <td className="px-2 py-1">
                                        <input type="checkbox" checked={!!r.is_active} onChange={(e) => set(i, 'is_active', e.target.checked)} className="rounded border-gray-300" aria-label={t('results.settings.active')} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className={`text-sm ${Math.abs(sum - 100) < 0.01 ? 'text-emerald-600' : 'text-red-600'}`}>{t('results.settings.weightSum', { n: Math.round(sum * 100) / 100 })}</p>
                <div className="flex flex-wrap gap-2">
                    <button type="button" className="btn-outline" onClick={add} disabled={data.rows.length >= 12}>
                        {t('results.settings.addCategory')}
                    </button>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                </div>
            </form>
        </Card>
    );
}

function Scale({ bands }) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors } = useForm({ bands: bands.map((b) => ({ ...b, remark: b.remark ?? '' })) });
    const set = (i, k, v) => setData('bands', data.bands.map((r, j) => (j === i ? { ...r, [k]: v } : r)));

    return (
        <Card title={t('results.settings.scale')}>
            <p className="mb-4 text-sm text-slate-500">{t('results.settings.scaleHint')}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('examinations.settings.scale'), { preserveScroll: true });
                }}
                className="space-y-3"
            >
                <ErrorSummary errors={errors} />
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-2 py-2 text-start">{t('results.grade')}</th>
                                <th className="px-2 py-2 text-start">{t('results.settings.minScore')}</th>
                                <th className="px-2 py-2 text-start">{t('results.remark')}</th>
                                <th className="px-2 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {data.bands.map((b, i) => (
                                <tr key={i}>
                                    <td className="px-2 py-1">
                                        <Input value={b.letter} onChange={(e) => set(i, 'letter', e.target.value)} required maxLength={10} className="!w-24" />
                                    </td>
                                    <td className="px-2 py-1">
                                        <Input type="number" min="0" max="100" step="0.01" value={b.min_score} onChange={(e) => set(i, 'min_score', e.target.value)} className="!w-24" />
                                    </td>
                                    <td className="px-2 py-1">
                                        <Input value={b.remark} onChange={(e) => set(i, 'remark', e.target.value)} maxLength={100} />
                                    </td>
                                    <td className="px-2 py-1 text-end">
                                        <button type="button" className="text-red-600 hover:underline disabled:opacity-40" disabled={data.bands.length <= 2} onClick={() => setData('bands', data.bands.filter((_, j) => j !== i))}>
                                            {t('acad.common.remove')}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button type="button" className="btn-outline" onClick={() => setData('bands', [...data.bands, { letter: '', min_score: 0, remark: '' }])} disabled={data.bands.length >= 15}>
                        {t('results.settings.addBand')}
                    </button>
                    <button type="submit" className="btn-teal" disabled={processing}>
                        {t('acad.common.save')}
                    </button>
                </div>
            </form>
        </Card>
    );
}

export default function Settings({ categories = [], bands = [], nav = [], user, school, academicYear }) {
    const { t } = useLocale();

    return (
        <AppShell title={t('results.settings')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('results.settings')} />
            <PageHeader
                title={t('results.settings')}
                subtitle={t('results.settings.subtitle')}
                actions={
                    <Link href={route('examinations.index')} className="btn-outline">
                        {t('acad.common.back')}
                    </Link>
                }
            />
            <Categories categories={categories} />
            <Scale bands={bands} />
        </AppShell>
    );
}
