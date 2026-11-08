import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({
    testimonial = {},
    updateUrl,
    landingUrl = '/',
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const form = useForm({
        quote: testimonial.quote ?? '',
        name: testimonial.name ?? '',
        role: testimonial.role ?? '',
        school: testimonial.school ?? '',
        photo_url: testimonial.photo_url ?? '',
        rating: testimonial.rating ?? 5,
        published: testimonial.published ?? true,
    });

    function submit(e) {
        e.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    }

    const stars = Math.max(1, Math.min(5, Number(form.data.rating) || 1));

    return (
        <AppShell title={t('plat.mkt.title')} nav={nav} user={user} school={school}>
            <Head title={t('plat.mkt.headTitle')} />

            <PageHeader
                title={t('plat.mkt.heading')}
                subtitle={t('plat.mkt.subtitle')}
                actions={
                    <Link href={landingUrl} className="btn-outline whitespace-nowrap text-sm">
                        {t('plat.mkt.view')}
                    </Link>
                }
            />

            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={submit} className="console-card space-y-5">
                    <div>
                        <InputLabel htmlFor="quote" value={t('plat.mkt.quote')} />
                        <textarea
                            id="quote"
                            rows={4}
                            className="ui-input mt-1"
                            value={form.data.quote}
                            onChange={(e) => form.setData('quote', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.quote} className="mt-1" />
                        <p className="mt-1 text-xs text-slate-500">{t('plat.mkt.quoteHelp')}</p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="name" value={t('plat.mkt.name')} />
                            <TextInput id="name" className="mt-1 w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="role" value={t('plat.mkt.role')} />
                            <TextInput id="role" className="mt-1 w-full" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} />
                            <InputError message={form.errors.role} className="mt-1" />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="school" value={t('plat.mkt.school')} />
                            <TextInput id="school" className="mt-1 w-full" value={form.data.school} onChange={(e) => form.setData('school', e.target.value)} />
                            <InputError message={form.errors.school} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="rating" value={t('plat.mkt.rating')} />
                            <TextInput id="rating" type="number" min="1" max="5" className="mt-1 w-full" value={form.data.rating} onChange={(e) => form.setData('rating', Number(e.target.value))} required />
                            <InputError message={form.errors.rating} className="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="photo_url" value={t('plat.mkt.photo')} />
                        <TextInput id="photo_url" className="mt-1 w-full" value={form.data.photo_url} onChange={(e) => form.setData('photo_url', e.target.value)} />
                        <InputError message={form.errors.photo_url} className="mt-1" />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-brand-ink">
                        <input
                            type="checkbox"
                            className="rounded border-slate-300 text-teal focus:ring-teal"
                            checked={form.data.published}
                            onChange={(e) => form.setData('published', e.target.checked)}
                        />
                        {t('plat.mkt.published')}
                    </label>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                            {t('plat.mkt.save')}
                        </button>
                        {form.recentlySuccessful ? (
                            <span className="text-sm font-medium text-brand-green">{t('plat.mkt.saved')}</span>
                        ) : null}
                    </div>
                </form>

                <aside className="console-card">
                    <h2 className="text-base font-semibold text-navy">{t('plat.mkt.preview')}</h2>
                    <blockquote className="mt-5 rounded-2xl border border-surface-muted bg-surface p-5">
                        <p className="text-sm leading-relaxed text-brand-ink">
                            {form.data.quote ? `“${form.data.quote}”` : '—'}
                        </p>
                        <div className="mt-4 flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-teal/15 text-sm font-bold text-teal">
                                {form.data.photo_url ? (
                                    <img src={form.data.photo_url} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    (form.data.name || '?').charAt(0).toUpperCase()
                                )}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-navy">{form.data.name || '—'}</p>
                                <p className="truncate text-xs text-slate-500">
                                    {[form.data.role, form.data.school].filter(Boolean).join(' · ') || '—'}
                                </p>
                                <p className="mt-1 text-xs text-amber-500" aria-hidden="true">
                                    {'★'.repeat(stars)}
                                    {'☆'.repeat(5 - stars)}
                                </p>
                            </div>
                        </div>
                    </blockquote>
                </aside>
            </div>
        </AppShell>
    );
}
