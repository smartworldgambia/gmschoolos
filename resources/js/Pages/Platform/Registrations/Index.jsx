import InputError from '@/Components/InputError';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const FILTERS = ['pending', 'cleared', 'confirmed_duplicate', 'all'];
const TONE = { pending: 'pending', cleared: 'completed', confirmed_duplicate: 'cancelled' };

function ReviewCard({ review }) {
    const { t } = useLocale();
    const form = useForm({ notes: '' });
    const school = review.school;
    const pending = review.status === 'pending';

    const act = (name) => form.post(route(name, review.id), { preserveScroll: true, onSuccess: () => form.reset() });

    return (
        <article className="console-card">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold text-navy">{school?.name ?? t('platformRegistrations.unknownSchool')}</h2>
                    <p className="mt-0.5 text-sm text-slate-500" dir="ltr">
                        {school?.slug} {'\u00b7'} {review.created_at}
                    </p>
                </div>
                <StatusBadge status={TONE[review.status]} label={t(`platformRegistrations.status.${review.status}`)} />
            </div>

            <div className="mt-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('platformRegistrations.reasons')}</p>
                <ul className="mt-1 flex flex-wrap gap-2">
                    {review.reasons.map((r) => (
                        <li key={r} className="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                            {t(`platformRegistrations.reason.${r}`)}
                        </li>
                    ))}
                </ul>
            </div>

            {school ? (
                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    {[
                        ['platformRegistrations.f.type', school.category ? t('setup.type.' + school.category) : null],
                        ['platformRegistrations.f.region', [school.region, school.district].filter(Boolean).join(' / ')],
                        ['platformRegistrations.f.regNo', school.registration_number],
                        ['platformRegistrations.f.schoolEmail', school.email],
                        ['platformRegistrations.f.adminEmail', school.admin_email],
                        ['platformRegistrations.f.phone', school.phone],
                        ['platformRegistrations.f.subscription', school.subscription_status ? t('billing.status.' + school.subscription_status) : null],
                        ['platformRegistrations.f.trialEnds', school.trial_ends_at],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-slate-500">{t(label)}</dt>
                            <dd className="break-all font-medium text-navy" dir="auto">
                                {value || '-'}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : null}

            <div className="mt-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('platformRegistrations.matched')}</p>
                {review.matched.length === 0 ? (
                    <p className="mt-1 text-sm text-slate-500">{t('platformRegistrations.noMatched')}</p>
                ) : (
                    <ul className="mt-1 space-y-1 text-sm">
                        {review.matched.map((m) => (
                            <li key={m.id}>
                                <Link href={route('platform.schools.show', m.id)} className="font-medium text-teal hover:underline">
                                    {m.name}
                                </Link>{' '}
                                <span className="text-slate-500" dir="ltr">
                                    ({m.slug}
                                    {m.region ? `, ${m.region}` : ''})
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {pending ? (
                <div className="mt-5 border-t border-slate-100 pt-4">
                    <label className="block text-sm text-slate-600">
                        {t('platformRegistrations.notes')}
                        <textarea className="ui-input mt-1" rows={2} maxLength={1000} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </label>
                    <InputError message={form.errors.notes} className="mt-1" />
                    <InputError message={form.errors.review} className="mt-1" />
                    <p className="mt-2 text-xs text-slate-400">{t('platformRegistrations.confirmNote')}</p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <button type="button" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm" onClick={() => act('platform.registrations.clear')}>
                            {t('platformRegistrations.clear')}
                        </button>
                        <button type="button" disabled={form.processing} className="btn-outline whitespace-nowrap text-sm" onClick={() => act('platform.registrations.confirm')}>
                            {t('platformRegistrations.confirm')}
                        </button>
                    </div>
                </div>
            ) : (
                <p className="mt-4 text-sm text-slate-500">
                    {t('platformRegistrations.decidedBy', { name: review.reviewer ?? '-', date: review.reviewed_at ?? '-' })}
                    {review.notes ? <span className="mt-1 block text-slate-600">{review.notes}</span> : null}
                </p>
            )}
        </article>
    );
}

export default function RegistrationsIndex({ reviews, status, pending = 0, nav = [], user, school }) {
    const { t } = useLocale();
    const { errors = {} } = usePage().props;

    return (
        <AppShell title={t('platformRegistrations.title')} nav={nav} user={user} school={school}>
            <Head title={t('platformRegistrations.title')} />
            <PageHeader title={t('platformRegistrations.title')} subtitle={t('platformRegistrations.subtitle', { count: pending })} />

            <div className="mb-5 flex flex-wrap gap-2" role="tablist">
                {FILTERS.map((f) => (
                    <button
                        key={f}
                        type="button"
                        role="tab"
                        aria-selected={status === f}
                        className={`${status === f ? 'btn-navy' : 'btn-outline'} whitespace-nowrap text-sm`}
                        onClick={() => router.get(route('platform.registrations.index'), { status: f }, { preserveState: true })}
                    >
                        {t(`platformRegistrations.filter.${f}`)}
                    </button>
                ))}
            </div>

            {errors.review ? <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{errors.review}</p> : null}

            {reviews.data.length === 0 ? (
                <p className="console-card text-sm text-slate-600">{t('platformRegistrations.empty')}</p>
            ) : (
                <div className="space-y-4">
                    {reviews.data.map((r) => (
                        <ReviewCard key={r.id} review={r} />
                    ))}
                </div>
            )}

            {reviews.prev_page_url || reviews.next_page_url ? (
                <div className="mt-6 flex gap-3">
                    {reviews.prev_page_url ? (
                        <Link href={reviews.prev_page_url} preserveState className="btn-outline whitespace-nowrap text-sm">
                            {t('platformRegistrations.prev')}
                        </Link>
                    ) : null}
                    {reviews.next_page_url ? (
                        <Link href={reviews.next_page_url} preserveState className="btn-outline whitespace-nowrap text-sm">
                            {t('platformRegistrations.next')}
                        </Link>
                    ) : null}
                </div>
            ) : null}
        </AppShell>
    );
}
