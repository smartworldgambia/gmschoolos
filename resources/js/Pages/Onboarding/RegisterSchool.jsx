import LanguageSwitcher from '@/Components/LanguageSwitcher';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Stepper from '@/Components/SchoolOS/Stepper';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const BLOCKING_SLUG_STATES = ['taken', 'reserved', 'invalid', 'too_short', 'too_long'];

const DEFAULT_CATEGORIES = [
    'Nursery',
    'Primary',
    'Basic Secondary',
    'Senior Secondary',
    'Combined',
    'International',
    'Other',
];

const DEFAULT_REGIONS = [
    'Banjul',
    'Kanifing',
    'Brikama',
    'Kerewan',
    'Mansakonko',
    'Kuntaur',
    'Janjanbureh',
    'Basse',
];

export default function RegisterSchool({
    prices = {},
    preselectedCategory = '',
    submitUrl = '/register-school',
    subdomainCheckUrl = '/register-school/subdomain-check',
    baseDomain = 'localhost',
    trialDays = 30,
    categories = DEFAULT_CATEGORIES,
    regions = DEFAULT_REGIONS,
    legal = { terms: '/terms', privacy: '/privacy' },
}) {
    const { t } = useLocale();
    const { data, setData, post, processing, errors, reset, clearErrors, setError } = useForm({
        school_name: '',
        category: preselectedCategory || '',
        registration_number: '',
        school_email: '',
        school_phone: '',
        address: '',
        country: 'The Gambia',
        region: '',
        district: '',
        terms: false,
        subdomain: '',
        logo: null,
        academic_year_name: '',
        term_name: '',
        admin_name: '',
        admin_email: '',
        admin_phone: '',
        password: '',
        password_confirmation: '',
    });

    const [step, setStep] = useState(0);
    const [stepError, setStepError] = useState('');
    // idle | checking | available | taken | reserved | invalid | too_short | too_long
    const [slugState, setSlugState] = useState('idle');

    // Debounced live availability check for the dedicated school URL.
    useEffect(() => {
        const slug = data.subdomain.trim();
        if (slug.length < 3) {
            // Step validation reports "too short"; avoid red noise while the user is still typing.
            setSlugState('idle');
            return undefined;
        }

        setSlugState('checking');
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const res = await fetch(`${subdomainCheckUrl}?subdomain=${encodeURIComponent(slug)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!res.ok) throw new Error('check failed');
                const json = await res.json();
                setSlugState(json.status || 'idle');
            } catch (error) {
                // Network hiccup: do not block the user — the server re-validates on submit.
                if (error?.name !== 'AbortError') setSlugState('idle');
            }
        }, 350);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [data.subdomain, subdomainCheckUrl]);

    const steps = useMemo(
        () => [
            t('onboarding.step.school'),
            t('onboarding.step.fee'),
            t('onboarding.step.academic'),
            t('onboarding.step.admin'),
            t('onboarding.step.review'),
        ],
        [t],
    );

    function categoryLabel(value) {
        const key = `onboarding.category.${value}`;
        const translated = t(key);
        return translated === key ? value : translated;
    }

    function validateStep(current) {
        clearErrors();
        setStepError('');

        if (current === 0) {
            const missing = [];
            if (!data.school_name.trim()) missing.push('school_name');
            if (!data.category) missing.push('category');
            if (!data.region) missing.push('region');
            if (!data.district.trim()) missing.push('district');
            if (!data.school_email.trim()) missing.push('school_email');
            if (!data.school_phone.trim()) missing.push('school_phone');
            if (!data.address.trim()) missing.push('address');
            if (!data.subdomain.trim() || data.subdomain.trim().length < 3) missing.push('subdomain');
            if (missing.length) {
                missing.forEach((field) => setError(field, t('onboarding.stepInvalid')));
                setStepError(t('onboarding.stepInvalid'));
                return false;
            }
            if (BLOCKING_SLUG_STATES.includes(slugState)) {
                setError('subdomain', t(`onboarding.subdomain.${slugState}`));
                setStepError(t(`onboarding.subdomain.${slugState}`));
                return false;
            }
            return true;
        }

        if (current === 1) {
            // Informational step: the annual fee follows the school type; nothing is charged during the trial.
            return true;
        }

        if (current === 2) {
            const missing = [];
            if (!data.academic_year_name.trim()) missing.push('academic_year_name');
            if (!data.term_name.trim()) missing.push('term_name');
            if (missing.length) {
                missing.forEach((field) => setError(field, t('onboarding.stepInvalid')));
                setStepError(t('onboarding.stepInvalid'));
                return false;
            }
            return true;
        }

        if (current === 3) {
            const missing = [];
            if (!data.admin_name.trim()) missing.push('admin_name');
            if (!data.admin_email.trim()) missing.push('admin_email');
            if (!data.admin_phone.trim()) missing.push('admin_phone');
            if (!data.password) missing.push('password');
            if (!data.password_confirmation) missing.push('password_confirmation');
            if (data.password && data.password_confirmation && data.password !== data.password_confirmation) {
                setError('password_confirmation', t('onboarding.passwordMismatch'));
                setStepError(t('onboarding.passwordMismatch'));
                return false;
            }
            if (missing.length) {
                missing.forEach((field) => setError(field, t('onboarding.stepInvalid')));
                setStepError(t('onboarding.stepInvalid'));
                return false;
            }
            return true;
        }

        return true;
    }

    function next() {
        if (!validateStep(step)) return;
        setStep((s) => Math.min(s + 1, steps.length - 1));
    }

    function back() {
        setStepError('');
        clearErrors();
        setStep((s) => Math.max(s - 1, 0));
    }

    function submit(e) {
        e.preventDefault();
        if (!validateStep(3) || !validateStep(0) || !validateStep(1) || !validateStep(2)) {
            setStepError(t('onboarding.stepInvalid'));
            return;
        }
        if (!data.terms) {
            setError('terms', t('onboarding.terms.required'));
            setStepError(t('onboarding.terms.required'));
            return;
        }
        post(submitUrl, {
            forceFormData: true,
            onSuccess: () => reset('password', 'password_confirmation'),
        });
    }

    const selectedPrice = prices?.[data.category] ?? null;

    return (
        <>
            <Head title={t('onboarding.headTitle')} />

            <div className="relative min-h-screen bg-surface py-8 sm:py-12">
                <div className="absolute end-4 top-4 z-10 sm:end-6 sm:top-6">
                    <LanguageSwitcher />
                </div>

                <div className="mx-auto max-w-3xl px-4 sm:px-6">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-navy text-sm font-bold text-white">
                            GS
                        </div>
                        <h1 className="text-2xl font-bold text-navy sm:text-3xl">{t('onboarding.title')}</h1>
                        <p className="mt-2 text-sm text-slate-500">{t('onboarding.subtitle')}</p>
                        <p className="mt-2 text-xs font-medium text-brand-green">
                            {t('onboarding.trialNote', { days: trialDays })}
                        </p>
                    </div>

                    <div className="rounded-xl bg-white p-5 shadow-sm sm:p-8">
                        <Stepper steps={steps} currentStep={step} className="mb-8" />

                        <form onSubmit={submit} encType="multipart/form-data">
                            {stepError ? (
                                <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {stepError}
                                </div>
                            ) : null}

                            {step === 0 && (
                                <div className="space-y-4">
                                    <div>
                                        <InputLabel htmlFor="school_name" value={t('onboarding.schoolName')} />
                                        <TextInput
                                            id="school_name"
                                            className="mt-1 block w-full"
                                            value={data.school_name}
                                            onChange={(e) => setData('school_name', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.school_name} className="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="category" value={t('onboarding.category')} />
                                        <select
                                            id="category"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal focus:ring-teal"
                                            value={data.category}
                                            onChange={(e) => setData('category', e.target.value)}
                                            required
                                        >
                                            <option value="">{t('onboarding.selectCategory')}</option>
                                            {categories.map((c) => (
                                                <option key={c} value={c}>
                                                    {categoryLabel(c)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.category} className="mt-1" />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="country" value={t('onboarding.country')} />
                                            <TextInput
                                                id="country"
                                                className="mt-1 block w-full"
                                                value={data.country}
                                                onChange={(e) => setData('country', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.country} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="region" value={t('onboarding.region')} />
                                            <select
                                                id="region"
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal focus:ring-teal"
                                                value={data.region}
                                                onChange={(e) => setData('region', e.target.value)}
                                                required
                                            >
                                                <option value="">{t('onboarding.selectRegion')}</option>
                                                {regions.map((r) => (
                                                    <option key={r} value={r}>
                                                        {r}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.region} className="mt-1" />
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="district" value={t('onboarding.district')} />
                                            <TextInput
                                                id="district"
                                                className="mt-1 block w-full"
                                                value={data.district}
                                                onChange={(e) => setData('district', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.district} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel
                                                htmlFor="registration_number"
                                                value={t('onboarding.registrationNumber')}
                                            />
                                            <TextInput
                                                id="registration_number"
                                                className="mt-1 block w-full"
                                                value={data.registration_number}
                                                onChange={(e) => setData('registration_number', e.target.value)}
                                            />
                                            <p className="mt-1 text-xs text-slate-400">
                                                {t('onboarding.registrationNumberHelp')}
                                            </p>
                                            <InputError message={errors.registration_number} className="mt-1" />
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="school_email" value={t('onboarding.schoolEmail')} />
                                            <TextInput
                                                id="school_email"
                                                type="email"
                                                className="mt-1 block w-full"
                                                value={data.school_email}
                                                onChange={(e) => setData('school_email', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.school_email} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="school_phone" value={t('onboarding.schoolPhone')} />
                                            <TextInput
                                                id="school_phone"
                                                type="tel"
                                                className="mt-1 block w-full"
                                                value={data.school_phone}
                                                onChange={(e) => setData('school_phone', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.school_phone} className="mt-1" />
                                        </div>
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="address" value={t('onboarding.address')} />
                                        <TextInput
                                            id="address"
                                            className="mt-1 block w-full"
                                            value={data.address}
                                            onChange={(e) => setData('address', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.address} className="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="subdomain" value={t('onboarding.subdomain')} />
                                        <div className="mt-1 flex rounded-md shadow-sm">
                                            <TextInput
                                                id="subdomain"
                                                className="block w-full rounded-e-none"
                                                value={data.subdomain}
                                                onChange={(e) =>
                                                    setData(
                                                        'subdomain',
                                                        e.target.value
                                                            .toLowerCase()
                                                            .replace(/[^a-z0-9-]/g, ''),
                                                    )
                                                }
                                                required
                                                aria-describedby="subdomain-help"
                                            />
                                            <span className="inline-flex items-center rounded-e-md border border-s-0 border-gray-300 bg-slate-50 px-3 text-sm text-slate-500">
                                                .{baseDomain}
                                            </span>
                                        </div>
                                        <p id="subdomain-help" className="mt-1 text-xs text-slate-400">
                                            {t('onboarding.subdomainHelp')}
                                        </p>
                                        {slugState !== 'idle' ? (
                                            <p
                                                className={`mt-1.5 flex items-center gap-1.5 text-xs font-medium ${
                                                    slugState === 'available'
                                                        ? 'text-emerald-700'
                                                        : slugState === 'checking'
                                                          ? 'text-slate-500'
                                                          : 'text-red-700'
                                                }`}
                                                role="status"
                                                aria-live="polite"
                                            >
                                                <span
                                                    className={`h-2 w-2 shrink-0 rounded-full ${
                                                        slugState === 'available'
                                                            ? 'bg-emerald-500'
                                                            : slugState === 'checking'
                                                              ? 'animate-pulse bg-slate-400'
                                                              : 'bg-red-500'
                                                    }`}
                                                    aria-hidden="true"
                                                />
                                                {t(`onboarding.subdomain.${slugState}`)}
                                            </p>
                                        ) : null}
                                        {data.subdomain && !BLOCKING_SLUG_STATES.includes(slugState) ? (
                                            <div className="mt-2 rounded-lg border border-slate-200 bg-surface/70 px-3 py-2">
                                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                    {t('onboarding.subdomain.preview')}
                                                </p>
                                                <p
                                                    dir="ltr"
                                                    className="mt-0.5 break-all text-start font-mono text-sm font-semibold text-navy"
                                                >
                                                    {data.subdomain}.{baseDomain}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {t('onboarding.subdomain.customLater')}
                                                </p>
                                            </div>
                                        ) : null}
                                        <InputError message={errors.subdomain} className="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="logo" value={t('onboarding.logo')} />
                                        <input
                                            id="logo"
                                            type="file"
                                            accept="image/*"
                                            className="mt-1 block w-full text-sm text-slate-500 file:me-4 file:rounded-lg file:border-0 file:bg-teal-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-teal-700 hover:file:bg-teal-100"
                                            onChange={(e) =>
                                                setData('logo', e.target.files?.[0] ?? null)
                                            }
                                        />
                                        <InputError message={errors.logo} className="mt-1" />
                                    </div>
                                </div>
                            )}

                            {step === 1 && (
                                <div className="space-y-4">
                                    <p className="text-sm text-slate-500">{t('onboarding.fee.help')}</p>
                                    {!data.category ? (
                                        <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                            {t('onboarding.fee.needType')}
                                        </p>
                                    ) : selectedPrice ? (
                                        <div className="rounded-xl border border-teal bg-teal-50 p-5 ring-2 ring-teal/20">
                                            <p className="text-sm text-slate-600">
                                                {t('onboarding.fee.forType', { type: categoryLabel(data.category) })}
                                            </p>
                                            <p className="mt-1 text-2xl font-bold text-navy">
                                                {t('onboarding.fee.perYear', { amount: selectedPrice.label })}
                                            </p>
                                            <p className="mt-3 text-sm text-slate-600">
                                                {t('onboarding.fee.trialNote', { days: trialDays })}
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                            {t('onboarding.fee.notConfigured')}
                                        </p>
                                    )}
                                </div>
                            )}

                            {step === 2 && (
                                <div className="space-y-4">
                                    <div>
                                        <InputLabel
                                            htmlFor="academic_year_name"
                                            value={t('onboarding.academicYear')}
                                        />
                                        <TextInput
                                            id="academic_year_name"
                                            className="mt-1 block w-full"
                                            placeholder={t('onboarding.academicYearPh')}
                                            value={data.academic_year_name}
                                            onChange={(e) =>
                                                setData('academic_year_name', e.target.value)
                                            }
                                            required
                                        />
                                        <InputError
                                            message={errors.academic_year_name}
                                            className="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="term_name" value={t('onboarding.term')} />
                                        <TextInput
                                            id="term_name"
                                            className="mt-1 block w-full"
                                            placeholder={t('onboarding.termPh')}
                                            value={data.term_name}
                                            onChange={(e) => setData('term_name', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.term_name} className="mt-1" />
                                    </div>
                                </div>
                            )}

                            {step === 3 && (
                                <div className="space-y-4">
                                    <div>
                                        <InputLabel
                                            htmlFor="admin_name"
                                            value={t('onboarding.adminName')}
                                        />
                                        <TextInput
                                            id="admin_name"
                                            className="mt-1 block w-full"
                                            value={data.admin_name}
                                            onChange={(e) => setData('admin_name', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.admin_name} className="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel
                                            htmlFor="admin_email"
                                            value={t('onboarding.adminEmail')}
                                        />
                                        <TextInput
                                            id="admin_email"
                                            type="email"
                                            className="mt-1 block w-full"
                                            value={data.admin_email}
                                            onChange={(e) => setData('admin_email', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.admin_email} className="mt-1" />
                                    </div>
                                    <div>
                                        <InputLabel
                                            htmlFor="admin_phone"
                                            value={t('onboarding.adminPhone')}
                                        />
                                        <TextInput
                                            id="admin_phone"
                                            className="mt-1 block w-full"
                                            type="tel"
                                            value={data.admin_phone}
                                            onChange={(e) => setData('admin_phone', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.admin_phone} className="mt-1" />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel
                                                htmlFor="password"
                                                value={t('onboarding.password')}
                                            />
                                            <TextInput
                                                id="password"
                                                type="password"
                                                className="mt-1 block w-full"
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.password} className="mt-1" />
                                        </div>
                                        <div>
                                            <InputLabel
                                                htmlFor="password_confirmation"
                                                value={t('onboarding.confirmPassword')}
                                            />
                                            <TextInput
                                                id="password_confirmation"
                                                type="password"
                                                className="mt-1 block w-full"
                                                value={data.password_confirmation}
                                                onChange={(e) =>
                                                    setData('password_confirmation', e.target.value)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.password_confirmation}
                                                className="mt-1"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {step === 4 && (
                                <div className="space-y-4 text-sm">
                                    <h2 className="text-lg font-semibold text-navy">
                                        {t('onboarding.reviewTitle')}
                                    </h2>
                                    <dl className="grid gap-3 rounded-xl bg-surface p-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.school')}</dt>
                                            <dd className="font-medium text-navy">
                                                {data.school_name || '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">
                                                {t('onboarding.review.category')}
                                            </dt>
                                            <dd className="font-medium text-navy">
                                                {data.category ? categoryLabel(data.category) : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.region')}</dt>
                                            <dd className="font-medium text-navy">{data.region || '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">
                                                {t('onboarding.review.subdomain')}
                                            </dt>
                                            <dd className="font-medium text-navy">
                                                {data.subdomain
                                                    ? `${data.subdomain}.${baseDomain}`
                                                    : '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.fee')}</dt>
                                            <dd className="font-medium text-navy">
                                                {selectedPrice ? t('onboarding.fee.perYear', { amount: selectedPrice.label }) : t('billing.notSet')}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">
                                                {t('onboarding.review.academic')}
                                            </dt>
                                            <dd className="font-medium text-navy">
                                                {data.academic_year_name || '—'} / {data.term_name || '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.admin')}</dt>
                                            <dd className="font-medium text-navy">
                                                {data.admin_name || '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">
                                                {t('onboarding.review.adminEmail')}
                                            </dt>
                                            <dd className="font-medium text-navy">
                                                {data.admin_email || '—'}
                                            </dd>
                                        </div>
                                    </dl>
                                    <dl className="grid gap-3 rounded-xl bg-surface p-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.contact')}</dt>
                                            <dd className="break-all font-medium text-navy" dir="auto">
                                                {data.school_email || '-'} / {data.school_phone || '-'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-slate-500">{t('onboarding.review.address')}</dt>
                                            <dd className="font-medium text-navy" dir="auto">
                                                {data.address || '-'}, {data.district || '-'}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div>
                                        <label className="flex items-start gap-3 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-teal focus:ring-teal"
                                                checked={data.terms}
                                                onChange={(e) => setData('terms', e.target.checked)}
                                            />
                                            <span>
                                                {t('onboarding.terms.prefix')}{' '}
                                                <a href={legal.terms} target="_blank" rel="noreferrer" className="font-semibold text-teal underline">
                                                    {t('onboarding.terms.termsLink')}
                                                </a>{' '}
                                                {t('onboarding.terms.and')}{' '}
                                                <a href={legal.privacy} target="_blank" rel="noreferrer" className="font-semibold text-teal underline">
                                                    {t('onboarding.terms.privacyLink')}
                                                </a>
                                                .
                                            </span>
                                        </label>
                                        <InputError message={errors.terms} className="mt-1" />
                                    </div>
                                    {Object.keys(errors).length > 0 ? (
                                        <div className="rounded-lg bg-red-50 px-4 py-3 text-red-700">
                                            <p>{t('onboarding.formErrors')}</p>
                                            <ul className="mt-1 list-disc ps-5 text-xs">
                                                {Object.entries(errors).map(([field, message]) => (
                                                    <li key={field}>{message}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    ) : null}
                                </div>
                            )}

                            <div className="mt-8 flex items-center justify-between gap-3 border-t border-slate-100 pt-6">
                                <button
                                    type="button"
                                    onClick={back}
                                    disabled={step === 0}
                                    className="btn-outline disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    {t('onboarding.back')}
                                </button>
                                {step < steps.length - 1 ? (
                                    <button
                                        type="button"
                                        onClick={next}
                                        className="btn-teal"
                                    >
                                        {t('onboarding.continue')}
                                    </button>
                                ) : (
                                    <button type="submit" className="btn-teal" disabled={processing}>
                                        {processing ? t('onboarding.submitting') : t('onboarding.submit')}
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
