import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, useForm } from '@inertiajs/react';

function Table({ heads, rows, empty }) {
    return (
        <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        {heads.map((h) => (
                            <th key={h} className="px-4 py-3 text-start">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={heads.length} className="px-4 py-6 text-center text-slate-500">
                                {empty}
                            </td>
                        </tr>
                    ) : null}
                    {rows.map((cells, i) => (
                        <tr key={i}>
                            {cells.map((c, j) => (
                                <td key={j} className="px-4 py-3">
                                    {c}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ExternalContent({ tools = [], packages = [], linkedLessons = [] }) {
    const { t } = useLocale();
    const toolForm = useForm({ name: '', launch_url: '', client_id: '', deployment_id: '' });
    const pkgForm = useForm({ title: '', version: '1.2', entry_point: '' });

    return (
        <LmsLayout
            title={t('lms.ext.title')}
            subtitle={t('lms.ext.subtitle')}
            author
            actions={
                <Link href={route('lms.courses.index')} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <p className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('lms.ext.disclaimer')}</p>

            <div className="grid gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">{t('lms.ext.tools')}</h2>
                    <Table
                        heads={[t('lms.ext.name'), t('lms.ext.url'), t('lms.common.status')]}
                        rows={tools.map((x) => [x.name, <span key="u" dir="ltr" className="break-all text-xs">{x.launch_url}</span>, <StatusBadge key="s" status={x.is_active ? 'active' : 'inactive'} label={x.is_active ? t('lms.ext.active') : t('lms.ext.inactive')} />])}
                        empty={t('acad.common.none')}
                    />
                    <Card className="mt-3">
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                toolForm.post(route('lms.external.tools.store'), { preserveScroll: true, onSuccess: () => toolForm.reset() });
                            }}
                        >
                            <ErrorSummary errors={toolForm.errors} />
                            <Field label={t('lms.ext.name')}>
                                <Input value={toolForm.data.name} onChange={(e) => toolForm.setData('name', e.target.value)} required maxLength={150} />
                            </Field>
                            <Field label={t('lms.ext.url')} hint={t('lms.ext.urlHelp')}>
                                <Input type="url" dir="ltr" value={toolForm.data.launch_url} onChange={(e) => toolForm.setData('launch_url', e.target.value)} placeholder="https://" required />
                            </Field>
                            <button type="submit" className="btn-navy" disabled={toolForm.processing}>
                                {t('lms.ext.addTool')}
                            </button>
                        </form>
                    </Card>
                </section>

                <section>
                    <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">{t('lms.ext.packages')}</h2>
                    <Table
                        heads={[t('lms.common.title'), t('lms.ext.version'), t('lms.common.status')]}
                        rows={packages.map((x) => [x.title, x.version, <StatusBadge key="s" status="info" label={t('lms.ext.linkOnly')} />])}
                        empty={t('acad.common.none')}
                    />
                    <Card className="mt-3">
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                pkgForm.post(route('lms.external.packages.store'), { preserveScroll: true, onSuccess: () => pkgForm.reset() });
                            }}
                        >
                            <ErrorSummary errors={pkgForm.errors} />
                            <Field label={t('lms.common.title')}>
                                <Input value={pkgForm.data.title} onChange={(e) => pkgForm.setData('title', e.target.value)} required maxLength={150} />
                            </Field>
                            <Field label={t('lms.ext.version')}>
                                <Select value={pkgForm.data.version} onChange={(e) => pkgForm.setData('version', e.target.value)}>
                                    <option value="1.2">1.2</option>
                                    <option value="2004">2004</option>
                                </Select>
                            </Field>
                            <Field label={t('lms.ext.url')} hint={t('lms.ext.urlHelp')}>
                                <Input type="url" dir="ltr" value={pkgForm.data.entry_point} onChange={(e) => pkgForm.setData('entry_point', e.target.value)} placeholder="https://" />
                            </Field>
                            <button type="submit" className="btn-navy" disabled={pkgForm.processing}>
                                {t('lms.ext.addPackage')}
                            </button>
                        </form>
                    </Card>
                </section>
            </div>

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">{t('lms.ext.linked')}</h2>
                <Table
                    heads={[t('lms.ext.lesson'), t('lms.ext.course'), t('lms.lesson.type'), t('lms.ext.url')]}
                    rows={linkedLessons.map((l) => [l.title, l.course ?? '—', l.content_type, <span key="u" dir="ltr" className="break-all text-xs">{l.launch_url ?? '—'}</span>])}
                    empty={t('lms.ext.noneLinked')}
                />
            </section>
        </LmsLayout>
    );
}
