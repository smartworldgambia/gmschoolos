import LmsLayout from '@/Components/Lms/LmsLayout';
import { Card, ErrorSummary, Field, Input, Select } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link, useForm } from '@inertiajs/react';

export default function Banks({ banks = [], subjects = [] }) {
    const { t } = useLocale();
    const form = useForm({ title: '', subject_id: '', is_shared: false });

    return (
        <LmsLayout
            title={t('lms.banks.title')}
            subtitle={t('lms.banks.subtitle')}
            author
            actions={
                <Link href={route('lms.courses.index')} className="btn-outline">
                    {t('lms.common.back')}
                </Link>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-start">{t('lms.common.title')}</th>
                                <th className="px-4 py-3 text-start">{t('lms.course.subject')}</th>
                                <th className="px-4 py-3 text-start">{t('lms.banks.questions')}</th>
                                <th className="px-4 py-3 text-start">{t('lms.banks.owner')}</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {banks.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                        {t('lms.banks.empty')}
                                    </td>
                                </tr>
                            ) : null}
                            {banks.map((b) => (
                                <tr key={b.id}>
                                    <td className="px-4 py-3 font-medium text-navy">
                                        {b.title}
                                        {b.is_shared ? (
                                            <span className="ms-2">
                                                <StatusBadge status="info" label={t('lms.banks.shared')} />
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3">{b.subject ?? '—'}</td>
                                    <td className="px-4 py-3">{b.questions}</td>
                                    <td className="px-4 py-3">{b.mine ? t('lms.banks.you') : b.owner ?? '—'}</td>
                                    <td className="px-4 py-3 text-end">
                                        <Link href={b.url} className="btn-outline">
                                            {t('lms.common.open')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Card title={t('lms.banks.new')}>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('lms.banks.store'), { preserveScroll: true });
                        }}
                    >
                        <ErrorSummary errors={form.errors} />
                        <Field label={t('lms.common.title')}>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('lms.course.subject')}>
                            <Select value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                                <option value="">{t('lms.common.none')}</option>
                                {subjects.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <label className="flex items-start gap-2 text-sm">
                            <input type="checkbox" className="mt-1 rounded border-gray-300" checked={form.data.is_shared} onChange={(e) => form.setData('is_shared', e.target.checked)} />
                            <span>
                                {t('lms.banks.share')}
                                <span className="block text-xs text-slate-500">{t('lms.banks.shareHelp')}</span>
                            </span>
                        </label>
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('lms.banks.create')}
                        </button>
                    </form>
                </Card>
            </div>
        </LmsLayout>
    );
}
