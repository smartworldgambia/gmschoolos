import InputError from '@/Components/InputError';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'mt-1 w-full rounded-lg border-slate-200 text-sm';

const STATUS_TONE = { done: 'completed', skipped: 'scheduled', todo: 'pending' };

function Field({ label, error, children, hint }) {
    return (
        <label className="block text-sm text-slate-600">
            {label}
            {children}
            {hint ? <span className="mt-1 block text-xs text-slate-400">{hint}</span> : null}
            <InputError message={error} className="mt-1" />
        </label>
    );
}

function Card({ title, help, children }) {
    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
            <h2 className="text-lg font-semibold text-navy">{title}</h2>
            {help ? <p className="mt-1 text-sm leading-6 text-slate-500">{help}</p> : null}
            <div className="mt-5">{children}</div>
        </section>
    );
}

/** Footer shared by every step: skip (when allowed) and continue-later. */
function StepActions({ step, links, children }) {
    const { t } = useLocale();

    return (
        <div className="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            {children}
            {step.skippable && step.status !== 'done' ? (
                <button
                    type="button"
                    className="btn-outline whitespace-nowrap text-sm"
                    onClick={() => router.post(route('setup.steps.skip', step.key), {}, { preserveScroll: true })}
                >
                    {t('setup.skip')}
                </button>
            ) : null}
            <Link href={links.dashboard} className="btn-outline ms-auto whitespace-nowrap text-sm">
                {t('setup.later')}
            </Link>
        </div>
    );
}

function ItemList({ items, onRemove, render, empty }) {
    const { t } = useLocale();

    if (!items.length) {
        return <p className="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">{empty}</p>;
    }

    return (
        <ul className="divide-y divide-slate-100 rounded-xl border border-slate-100">
            {items.map((item) => (
                <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <span className="min-w-0 truncate text-navy">{render(item)}</span>
                    <button
                        type="button"
                        className="shrink-0 whitespace-nowrap text-xs font-semibold text-red-600 hover:underline"
                        onClick={() => onRemove(item)}
                    >
                        {t('setup.remove')}
                    </button>
                </li>
            ))}
        </ul>
    );
}

function ProfileStep({ step, profile, links }) {
    const { t } = useLocale();
    const form = useForm({
        registration_number: profile.registration_number ?? '',
        school_email: profile.school_email ?? '',
        phone: profile.phone ?? '',
        address: profile.address ?? '',
        region: profile.region ?? '',
        district: profile.district ?? '',
        tagline: profile.tagline ?? '',
    });
    const set = (k) => (e) => form.setData(k, e.target.value);

    return (
        <Card title={t('setup.profile.title')} help={t('setup.profile.help')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('setup.profile'), { preserveScroll: true });
                }}
            >
                <p className="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <span className="font-semibold text-navy">{profile.name}</span>
                    <span className="mt-0.5 block text-xs text-slate-400">{t('setup.profile.nameLocked')}</span>
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('onboarding.schoolEmail')} error={form.errors.school_email}>
                        <input type="email" className={inputClass} value={form.data.school_email} onChange={set('school_email')} required />
                    </Field>
                    <Field label={t('onboarding.schoolPhone')} error={form.errors.phone}>
                        <input type="tel" className={inputClass} value={form.data.phone} onChange={set('phone')} required />
                    </Field>
                    <Field label={t('onboarding.region')} error={form.errors.region}>
                        <input className={inputClass} value={form.data.region} onChange={set('region')} required />
                    </Field>
                    <Field label={t('onboarding.district')} error={form.errors.district}>
                        <input className={inputClass} value={form.data.district} onChange={set('district')} required />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label={t('onboarding.address')} error={form.errors.address}>
                            <input className={inputClass} value={form.data.address} onChange={set('address')} required />
                        </Field>
                    </div>
                    <Field label={t('onboarding.registrationNumber')} error={form.errors.registration_number} hint={t('onboarding.registrationNumberHelp')}>
                        <input className={inputClass} value={form.data.registration_number} onChange={set('registration_number')} />
                    </Field>
                    <Field label={t('setup.profile.tagline')} error={form.errors.tagline}>
                        <input className={inputClass} value={form.data.tagline} onChange={set('tagline')} maxLength={160} />
                    </Field>
                </div>
                <StepActions step={step} links={links}>
                    <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                        {t('setup.saveContinue')}
                    </button>
                </StepActions>
            </form>
        </Card>
    );
}

function BrandingStep({ step, branding, links }) {
    const { t } = useLocale();
    const form = useForm({ logo: null, primary_color: branding.primary_color, accent_color: branding.accent_color });

    return (
        <Card title={t('setup.branding.title')} help={t('setup.branding.help')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('setup.branding'), { forceFormData: true, preserveScroll: true });
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2 flex items-center gap-4">
                        {branding.logo_url ? (
                            <img src={branding.logo_url} alt={t('setup.branding.logoAlt')} className="h-16 w-16 rounded-xl border border-slate-100 object-contain" />
                        ) : (
                            <span className="flex h-16 w-16 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-400">{t('setup.branding.noLogo')}</span>
                        )}
                        <div className="min-w-0 flex-1">
                            <Field label={t('onboarding.logo')} error={form.errors.logo}>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="mt-1 block w-full text-sm text-slate-500 file:me-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-teal-700 hover:file:bg-teal-100"
                                    onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)}
                                />
                            </Field>
                        </div>
                    </div>
                    <Field label={t('setup.branding.primary')} error={form.errors.primary_color}>
                        <input type="color" className="mt-1 h-10 w-full rounded-lg border-slate-200" value={form.data.primary_color} onChange={(e) => form.setData('primary_color', e.target.value)} />
                    </Field>
                    <Field label={t('setup.branding.accent')} error={form.errors.accent_color}>
                        <input type="color" className="mt-1 h-10 w-full rounded-lg border-slate-200" value={form.data.accent_color} onChange={(e) => form.setData('accent_color', e.target.value)} />
                    </Field>
                </div>
                <StepActions step={step} links={links}>
                    <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                        {t('setup.saveContinue')}
                    </button>
                </StepActions>
            </form>
        </Card>
    );
}

function TypeStep({ step, schoolType, links }) {
    const { t } = useLocale();
    const label = t(`setup.type.${schoolType.category}`);

    return (
        <Card title={t('setup.type.title')} help={t('setup.type.help')}>
            <dl className="grid gap-3 text-sm sm:grid-cols-3">
                <div className="rounded-xl bg-slate-50 p-4">
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('setup.type.current')}</dt>
                    <dd className="mt-1 text-base font-semibold text-navy">{label}</dd>
                </div>
                <div className="rounded-xl bg-slate-50 p-4">
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('setup.type.price')}</dt>
                    <dd className="mt-1 text-base font-semibold text-navy" dir="ltr">
                        {schoolType.annual_price ? t('onboarding.fee.perYear', { amount: schoolType.annual_price }) : t('billing.notSet')}
                    </dd>
                </div>
                <div className="rounded-xl bg-slate-50 p-4">
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('setup.type.trialEnds')}</dt>
                    <dd className="mt-1 text-base font-semibold text-navy" dir="ltr">{schoolType.trial_ends_at ?? '-'}</dd>
                </div>
            </dl>
            <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{t('setup.type.changeNote')}</p>
            <StepActions step={step} links={links}>
                <button type="button" className="btn-teal whitespace-nowrap text-sm" onClick={() => router.post(route('setup.type'), {}, { preserveScroll: true })}>
                    {t('setup.type.confirm')}
                </button>
            </StepActions>
        </Card>
    );
}

function NameCodeForm({ routeName, nameLabel, codeLabel, addLabel }) {
    const form = useForm({ name: '', code: '' });

    return (
        <form
            className="mt-4 flex flex-wrap items-end gap-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route(routeName), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <div className="min-w-[10rem] flex-1">
                <Field label={nameLabel} error={form.errors.name}>
                    <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={120} />
                </Field>
            </div>
            <div className="w-32">
                <Field label={codeLabel} error={form.errors.code}>
                    <input className={inputClass} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} maxLength={20} />
                </Field>
            </div>
            <button type="submit" disabled={form.processing} className="btn-navy whitespace-nowrap text-sm">
                {addLabel}
            </button>
        </form>
    );
}

function DoneButton({ stepKey, label }) {
    return (
        <button type="button" className="btn-teal whitespace-nowrap text-sm" onClick={() => router.post(route('setup.steps.done', stepKey), {}, { preserveScroll: true })}>
            {label}
        </button>
    );
}

function StructureStep({ step, levels, links }) {
    const { t } = useLocale();

    return (
        <Card title={t('setup.structure.title')} help={t('setup.structure.help')}>
            <ItemList
                items={levels}
                empty={t('setup.structure.empty')}
                render={(l) => (l.code ? `${l.name} (${l.code})` : l.name)}
                onRemove={(l) => router.delete(route('setup.levels.destroy', l.id), { preserveScroll: true })}
            />
            <NameCodeForm routeName="setup.levels.store" nameLabel={t('setup.structure.levelName')} codeLabel={t('setup.code')} addLabel={t('setup.structure.add')} />
            <StepActions step={step} links={links}>
                <DoneButton stepKey="structure" label={t('setup.structure.confirm')} />
            </StepActions>
        </Card>
    );
}

function YearStep({ step, academicYear, links }) {
    const { t } = useLocale();
    const year = academicYear ?? { name: '', starts_on: '', ends_on: '', terms: [] };
    const form = useForm({
        name: year.name,
        starts_on: year.starts_on ?? '',
        ends_on: year.ends_on ?? '',
        terms: year.terms.length ? year.terms : [{ id: null, name: '', starts_on: '', ends_on: '' }],
    });

    const setTerm = (i, k, v) => form.setData('terms', form.data.terms.map((row, idx) => (idx === i ? { ...row, [k]: v } : row)));
    const termError = Object.entries(form.errors).find(([k]) => k.startsWith('terms'))?.[1];

    return (
        <Card title={t('setup.year.title')} help={t('setup.year.help')}>
            <p className="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{t('setup.year.placeholderNote')}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('setup.year'), { preserveScroll: true });
                }}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label={t('onboarding.academicYear')} error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('setup.year.starts')} error={form.errors.starts_on}>
                        <input type="date" className={inputClass} value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} required />
                    </Field>
                    <Field label={t('setup.year.ends')} error={form.errors.ends_on}>
                        <input type="date" className={inputClass} value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} required />
                    </Field>
                </div>

                <h3 className="mt-6 text-sm font-semibold text-navy">{t('setup.year.terms')}</h3>
                <div className="mt-2 space-y-3">
                    {form.data.terms.map((row, i) => (
                        <div key={row.id ?? `new-${i}`} className="grid items-end gap-3 sm:grid-cols-[1fr_9rem_9rem_auto]">
                            <Field label={t('setup.year.termName')}>
                                <input className={inputClass} value={row.name} onChange={(e) => setTerm(i, 'name', e.target.value)} required />
                            </Field>
                            <Field label={t('setup.year.starts')}>
                                <input type="date" className={inputClass} value={row.starts_on} onChange={(e) => setTerm(i, 'starts_on', e.target.value)} required />
                            </Field>
                            <Field label={t('setup.year.ends')}>
                                <input type="date" className={inputClass} value={row.ends_on} onChange={(e) => setTerm(i, 'ends_on', e.target.value)} required />
                            </Field>
                            <button
                                type="button"
                                disabled={form.data.terms.length === 1}
                                className="btn-outline whitespace-nowrap text-sm disabled:opacity-40"
                                onClick={() => form.setData('terms', form.data.terms.filter((_, idx) => idx !== i))}
                            >
                                {t('setup.remove')}
                            </button>
                        </div>
                    ))}
                </div>
                <InputError message={termError} className="mt-2" />
                {form.data.terms.length < 6 ? (
                    <button
                        type="button"
                        className="btn-outline mt-3 whitespace-nowrap text-sm"
                        onClick={() => form.setData('terms', [...form.data.terms, { id: null, name: '', starts_on: '', ends_on: '' }])}
                    >
                        {t('setup.year.addTerm')}
                    </button>
                ) : null}
                <StepActions step={step} links={links}>
                    <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                        {t('setup.saveContinue')}
                    </button>
                </StepActions>
            </form>
        </Card>
    );
}

function DepartmentsStep({ step, departments, links }) {
    const { t } = useLocale();

    return (
        <Card title={t('setup.departments.title')} help={t('setup.departments.help')}>
            <ItemList
                items={departments}
                empty={t('setup.departments.empty')}
                render={(d) => (d.code ? `${d.name} (${d.code})` : d.name)}
                onRemove={(d) => router.delete(route('setup.departments.destroy', d.id), { preserveScroll: true })}
            />
            <NameCodeForm routeName="setup.departments.store" nameLabel={t('setup.departments.name')} codeLabel={t('setup.code')} addLabel={t('setup.departments.add')} />
            <StepActions step={step} links={links}>
                <DoneButton stepKey="departments" label={t('setup.continue')} />
            </StepActions>
        </Card>
    );
}

function ClassesStep({ step, classes, levels, links }) {
    const { t } = useLocale();
    const form = useForm({ level_id: levels[0]?.id ?? '', name: '', stream: '', capacity: '' });
    const levelName = (id) => levels.find((l) => l.id === id)?.name ?? '-';

    return (
        <Card title={t('setup.classes.title')} help={t('setup.classes.help')}>
            <ItemList
                items={classes}
                empty={t('setup.classes.empty')}
                render={(c) => `${levelName(c.level_id)} - ${c.name}${c.stream ? ` ${c.stream}` : ''}${c.capacity ? ` (${c.capacity})` : ''}`}
                onRemove={(c) => router.delete(route('setup.classes.destroy', c.id), { preserveScroll: true })}
            />
            {levels.length === 0 ? (
                <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{t('setup.classes.needLevel')}</p>
            ) : (
                <form
                    className="mt-4 grid items-end gap-3 sm:grid-cols-[1fr_1fr_8rem_8rem_auto]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('setup.classes.store'), { preserveScroll: true, onSuccess: () => form.reset('name', 'stream', 'capacity') });
                    }}
                >
                    <Field label={t('setup.classes.level')} error={form.errors.level_id}>
                        <select className={inputClass} value={form.data.level_id} onChange={(e) => form.setData('level_id', e.target.value)} required>
                            {levels.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label={t('setup.classes.name')} error={form.errors.name}>
                        <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label={t('setup.classes.stream')} error={form.errors.stream}>
                        <input className={inputClass} value={form.data.stream} onChange={(e) => form.setData('stream', e.target.value)} maxLength={50} />
                    </Field>
                    <Field label={t('setup.classes.capacity')} error={form.errors.capacity}>
                        <input type="number" min="1" max="500" className={inputClass} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                    </Field>
                    <button type="submit" disabled={form.processing} className="btn-navy whitespace-nowrap text-sm">
                        {t('setup.classes.add')}
                    </button>
                </form>
            )}
            <StepActions step={step} links={links}>
                <DoneButton stepKey="classes" label={t('setup.classes.confirm')} />
            </StepActions>
        </Card>
    );
}

function SubjectsStep({ step, subjects, links }) {
    const { t } = useLocale();

    return (
        <Card title={t('setup.subjects.title')} help={t('setup.subjects.help')}>
            <ItemList
                items={subjects}
                empty={t('setup.subjects.empty')}
                render={(s) => (s.code ? `${s.name} (${s.code})` : s.name)}
                onRemove={(s) => router.delete(route('setup.subjects.destroy', s.id), { preserveScroll: true })}
            />
            <NameCodeForm routeName="setup.subjects.store" nameLabel={t('setup.subjects.name')} codeLabel={t('setup.code')} addLabel={t('setup.subjects.add')} />
            <StepActions step={step} links={links}>
                <DoneButton stepKey="subjects" label={t('setup.subjects.confirm')} />
            </StepActions>
        </Card>
    );
}

function StaffStep({ step, invitations, invitableRoles, links }) {
    const { t } = useLocale();
    const form = useForm({ name: '', email: '', role: invitableRoles[0] ?? 'teacher' });

    return (
        <Card title={t('setup.staff.title')} help={t('setup.staff.help')}>
            <form
                className="grid items-end gap-3 sm:grid-cols-[1fr_1fr_12rem_auto]"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('setup.invitations.store'), { preserveScroll: true, onSuccess: () => form.reset('name', 'email') });
                }}
            >
                <Field label={t('setup.staff.name')} error={form.errors.name}>
                    <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </Field>
                <Field label={t('setup.staff.email')} error={form.errors.email}>
                    <input type="email" className={inputClass} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                </Field>
                <Field label={t('setup.staff.role')} error={form.errors.role}>
                    <select className={inputClass} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                        {invitableRoles.map((r) => (
                            <option key={r} value={r}>
                                {t(`setup.role.${r}`)}
                            </option>
                        ))}
                    </select>
                </Field>
                <button type="submit" disabled={form.processing} className="btn-navy whitespace-nowrap text-sm">
                    {t('setup.staff.send')}
                </button>
            </form>
            <p className="mt-3 text-xs text-slate-400">{t('setup.staff.note')}</p>

            <div className="mt-5">
                {invitations.length === 0 ? (
                    <p className="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">{t('setup.staff.empty')}</p>
                ) : (
                    <ul className="divide-y divide-slate-100 rounded-xl border border-slate-100">
                        {invitations.map((i) => (
                            <li key={i.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                <span className="min-w-0">
                                    <span className="block truncate font-medium text-navy">{i.name}</span>
                                    <span className="block truncate text-xs text-slate-500" dir="ltr">
                                        {i.email} {'\u00b7'} {t(`setup.role.${i.role}`)}
                                    </span>
                                </span>
                                <span className="flex items-center gap-3">
                                    <StatusBadge status={i.state === 'accepted' ? 'completed' : i.state === 'pending' ? 'pending' : 'cancelled'} label={t(`setup.invite.${i.state}`)} />
                                    {i.state !== 'accepted' ? (
                                        <button type="button" className="whitespace-nowrap text-xs font-semibold text-teal hover:underline" onClick={() => router.post(route('setup.invitations.resend', i.id), {}, { preserveScroll: true })}>
                                            {t('setup.staff.resend')}
                                        </button>
                                    ) : null}
                                    {i.state === 'pending' ? (
                                        <button type="button" className="whitespace-nowrap text-xs font-semibold text-red-600 hover:underline" onClick={() => router.delete(route('setup.invitations.destroy', i.id), { preserveScroll: true })}>
                                            {t('setup.staff.revoke')}
                                        </button>
                                    ) : null}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <StepActions step={step} links={links}>
                <DoneButton stepKey="staff" label={t('setup.continue')} />
            </StepActions>
        </Card>
    );
}

function StudentsStep({ step, links, count }) {
    const { t } = useLocale();

    return (
        <Card title={t('setup.students.title')} help={t('setup.students.help')}>
            <div className="grid gap-3 sm:grid-cols-2">
                <Link href={links.import} className="rounded-xl border border-slate-200 p-4 text-sm hover:border-teal">
                    <span className="block font-semibold text-navy">{t('setup.students.import')}</span>
                    <span className="mt-1 block text-slate-500">{t('setup.students.importHelp')}</span>
                </Link>
                <Link href={links.admissions} className="rounded-xl border border-slate-200 p-4 text-sm hover:border-teal">
                    <span className="block font-semibold text-navy">{t('setup.students.admission')}</span>
                    <span className="mt-1 block text-slate-500">{t('setup.students.admissionHelp')}</span>
                </Link>
            </div>
            <p className="mt-4 text-sm text-slate-500">{t('setup.students.count', { count: count ?? 0 })}</p>
            <StepActions step={step} links={links}>
                <DoneButton stepKey="students" label={t('setup.students.done')} />
            </StepActions>
        </Card>
    );
}

function ParentsStep({ step, parentAccess, links }) {
    const { t } = useLocale();
    const form = useForm({ enabled: parentAccess });

    return (
        <Card title={t('setup.parents.title')} help={t('setup.parents.help')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('setup.parents'), { preserveScroll: true });
                }}
            >
                <label className="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-teal focus:ring-teal" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} />
                    <span>{t('setup.parents.enable')}</span>
                </label>
                <p className="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{t('setup.parents.note')}</p>
                <StepActions step={step} links={links}>
                    <button type="submit" disabled={form.processing} className="btn-teal whitespace-nowrap text-sm">
                        {t('setup.saveContinue')}
                    </button>
                </StepActions>
            </form>
        </Card>
    );
}

function WebsiteStep({ step, website, links }) {
    const { t } = useLocale();
    const choose = (publish) => router.post(route('setup.website'), { publish }, { preserveScroll: true });

    return (
        <Card title={t('setup.website.title')} help={t('setup.website.help')}>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div className="rounded-xl bg-slate-50 p-4">
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('setup.website.state')}</dt>
                    <dd className="mt-1 font-semibold text-navy">{website.published ? t('setup.website.published') : t('setup.website.private')}</dd>
                </div>
                <div className="rounded-xl bg-slate-50 p-4">
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('setup.website.address')}</dt>
                    <dd className="mt-1 break-all font-mono text-xs text-navy" dir="ltr">
                        {website.url}
                    </dd>
                </div>
            </dl>
            <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">{t('setup.website.sampleNote')}</p>
            <StepActions step={step} links={links}>
                <button type="button" className="btn-teal whitespace-nowrap text-sm" onClick={() => choose(true)}>
                    {t('setup.website.publish')}
                </button>
                <button type="button" className="btn-outline whitespace-nowrap text-sm" onClick={() => choose(false)}>
                    {t('setup.website.keepPrivate')}
                </button>
                <Link href={links.websiteEdit} className="btn-outline whitespace-nowrap text-sm">
                    {t('setup.website.edit')}
                </Link>
            </StepActions>
        </Card>
    );
}

export default function SetupIndex(props) {
    const { t } = useLocale();
    const { steps, current, progress, links, nav, user, school } = props;
    const [navOpen, setNavOpen] = useState(false);
    const { errors = {} } = usePage().props;
    const banner = ['step', 'level', 'class', 'subject', 'invitation'].map((k) => errors[k]).filter(Boolean);
    const step = steps.find((s) => s.key === current) ?? steps[0];

    const panel = {
        profile: <ProfileStep step={step} profile={props.profile} links={links} />,
        branding: <BrandingStep step={step} branding={props.branding} links={links} />,
        school_type: <TypeStep step={step} schoolType={props.schoolType} links={links} />,
        structure: <StructureStep step={step} levels={props.levels} links={links} />,
        academic_year: <YearStep step={step} academicYear={props.academicYear} links={links} />,
        departments: <DepartmentsStep step={step} departments={props.departments} links={links} />,
        classes: <ClassesStep step={step} classes={props.classes} levels={props.levels} links={links} />,
        subjects: <SubjectsStep step={step} subjects={props.subjects} links={links} />,
        staff: <StaffStep step={step} invitations={props.invitations} invitableRoles={props.invitableRoles} links={links} />,
        students: <StudentsStep step={step} links={links} count={steps.find((s) => s.key === 'students')?.count} />,
        parents: <ParentsStep step={step} parentAccess={props.parentAccess} links={links} />,
        website: <WebsiteStep step={step} website={props.website} links={links} />,
    }[step.key];

    return (
        <AppShell title={t('setup.title')} nav={nav} user={user} school={school}>
            <Head title={t('setup.title')} />
            <PageHeader title={t('setup.title')} subtitle={t('setup.subtitle')} />

            {banner.length ? (
                <div role="alert" className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                    {banner.map((m) => (
                        <p key={m}>{m}</p>
                    ))}
                </div>
            ) : null}

            <div className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="font-semibold text-navy">{t('setup.progress', { done: progress.done + progress.skipped, total: progress.total })}</span>
                    <span className="text-slate-500">{progress.percent}%</span>
                </div>
                <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow={progress.percent} aria-valuemin={0} aria-valuemax={100}>
                    <div className="h-full rounded-full bg-teal" style={{ width: `${progress.percent}%` }} />
                </div>
                <p className="mt-2 text-xs text-slate-500">{t('setup.saved')}</p>
            </div>

            <div className="grid gap-6 lg:grid-cols-[17rem_1fr]">
                <nav aria-label={t('setup.stepsAria')} className="rounded-2xl bg-white p-3 shadow-sm">
                    <button type="button" className="btn-outline mb-2 w-full whitespace-nowrap text-sm lg:hidden" onClick={() => setNavOpen((o) => !o)} aria-expanded={navOpen}>
                        {t('setup.stepsToggle')}
                    </button>
                    <ol className={`${navOpen ? 'block' : 'hidden'} space-y-1 lg:block`}>
                        {steps.map((s, i) => (
                            <li key={s.key}>
                                <Link
                                    href={route('setup.index', { step: s.key })}
                                    preserveScroll
                                    className={`flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm ${s.key === current ? 'bg-navy text-white' : 'text-slate-700 hover:bg-slate-50'}`}
                                >
                                    <span className="min-w-0 truncate">
                                        {i + 1}. {t(`setup.step.${s.key}`)}
                                    </span>
                                    <span className={`shrink-0 text-xs font-semibold ${s.key === current ? 'text-white/80' : s.status === 'done' ? 'text-emerald-600' : s.status === 'skipped' ? 'text-blue-600' : 'text-slate-400'}`}>
                                        {t(`setup.status.${s.status}`)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ol>
                </nav>

                <div className="min-w-0 space-y-4">
                    <div className="flex items-center gap-3">
                        <StatusBadge status={STATUS_TONE[step.status]} label={t(`setup.status.${step.status}`)} />
                        {step.status !== 'todo' ? (
                            <button type="button" className="text-xs font-semibold text-teal hover:underline" onClick={() => router.post(route('setup.steps.reopen', step.key), {}, { preserveScroll: true })}>
                                {t('setup.reopen')}
                            </button>
                        ) : null}
                    </div>
                    {panel}
                    {progress.complete ? (
                        <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <h2 className="text-base font-semibold text-emerald-900">{t('setup.complete.title')}</h2>
                            <p className="mt-1 text-sm text-emerald-800">{t('setup.complete.body')}</p>
                            <button type="button" className="btn-teal mt-4 whitespace-nowrap text-sm" onClick={() => router.post(route('setup.complete'))}>
                                {t('setup.complete.cta')}
                            </button>
                        </section>
                    ) : null}
                </div>
            </div>
        </AppShell>
    );
}
