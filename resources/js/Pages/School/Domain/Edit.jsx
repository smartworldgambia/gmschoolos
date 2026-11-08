import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const POLL_MS = 20000;

function useCopy() {
    const [copiedKey, setCopiedKey] = useState(null);

    async function copy(key, value) {
        try {
            await navigator.clipboard.writeText(value);
            setCopiedKey(key);
            setTimeout(() => setCopiedKey((current) => (current === key ? null : current)), 1600);
        } catch {
            /* clipboard unavailable — ignore */
        }
    }

    return { copiedKey, copy };
}

function Pill({ tone = 'slate', children }) {
    const tones = {
        green: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        amber: 'bg-amber-50 text-amber-900 ring-amber-600/20',
        slate: 'bg-slate-100 text-slate-700 ring-slate-500/15',
        blue: 'bg-sky-50 text-sky-800 ring-sky-600/20',
        glass: 'bg-white/15 text-white ring-white/25',
    };

    return (
        <span
            className={`inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${tones[tone] ?? tones.slate}`}
        >
            {children}
        </span>
    );
}

function CheckIcon({ className = 'h-4 w-4' }) {
    return (
        <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path
                fillRule="evenodd"
                d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z"
                clipRule="evenodd"
            />
        </svg>
    );
}

function Stepper({ current, labels }) {
    return (
        <ol className="flex items-center gap-2 sm:gap-3" aria-label="progress">
            {labels.map((label, index) => {
                const done = index < current;
                const active = index === current;

                return (
                    <li
                        key={label}
                        className="flex min-w-0 flex-1 flex-col items-center gap-1.5 sm:flex-row sm:gap-3"
                    >
                        <span
                            className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-1 ring-inset transition ${
                                done
                                    ? 'bg-brand-green text-white ring-brand-green'
                                    : active
                                      ? 'bg-white text-navy ring-white'
                                      : 'bg-white/10 text-white/70 ring-white/25'
                            }`}
                            aria-current={active ? 'step' : undefined}
                        >
                            {done ? <CheckIcon className="h-3.5 w-3.5" /> : index + 1}
                        </span>
                        <span
                            className={`text-center text-[11px] font-semibold leading-tight sm:text-start sm:text-sm ${
                                active || done ? 'text-white' : 'text-white/60'
                            }`}
                        >
                            {label}
                        </span>
                        {index < labels.length - 1 ? (
                            <span
                                className={`hidden h-px min-w-4 flex-1 sm:block ${
                                    done ? 'bg-brand-green' : 'bg-white/20'
                                }`}
                                aria-hidden="true"
                            />
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

function CopyField({ id, label, value, copiedKey, onCopy, copyLabel, copiedLabel }) {
    const copied = copiedKey === id;

    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <div className="mt-1 flex items-stretch gap-2">
                <code
                    dir="ltr"
                    className="min-w-0 flex-1 select-all break-all rounded-lg border border-slate-200 bg-white px-3 py-2 text-start font-mono text-xs text-navy"
                >
                    {value}
                </code>
                <button
                    type="button"
                    className="btn-outline text-xs"
                    onClick={() => onCopy(id, value)}
                    aria-live="polite"
                >
                    {copied ? copiedLabel : copyLabel}
                </button>
            </div>
        </div>
    );
}

function CheckRow({ ok, label, t }) {
    return (
        <li className="flex items-center gap-2 text-sm">
            <span
                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${
                    ok ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                }`}
                aria-hidden="true"
            >
                {ok ? (
                    <CheckIcon className="h-3 w-3" />
                ) : (
                    <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500" />
                )}
            </span>
            <span className="font-medium text-navy">{label}</span>
            <span className={`ms-auto text-xs ${ok ? 'text-emerald-700' : 'text-amber-800'}`}>
                {ok ? t('domainAdmin.check.found') : t('domainAdmin.check.waiting')}
            </span>
        </li>
    );
}

function formatAgo(iso, locale) {
    if (!iso) return null;
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const abs = Math.abs(seconds);

    if (abs < 60) return rtf.format(Math.min(0, seconds), 'second');
    if (abs < 3600) return rtf.format(Math.round(seconds / 60), 'minute');
    if (abs < 86400) return rtf.format(Math.round(seconds / 3600), 'hour');

    return rtf.format(Math.round(seconds / 86400), 'day');
}

function PendingDomain({ domain, checking, onCheck, onRemove, copiedKey, onCopy, t, locale }) {
    const checked = Boolean(domain.last_checked_at);
    const routingOk = checked && !['dns_not_found', 'routing_missing'].includes(domain.error);
    const ownershipOk = checked && !['dns_not_found', 'ownership_missing'].includes(domain.error);
    const hasApexAlias = domain.records.some((record) => record.type === 'ALIAS');
    const errorKey = domain.error ? `domainAdmin.error.${domain.error}` : null;

    return (
        <li className="overflow-hidden rounded-2xl border border-amber-200/80 bg-white shadow-sm">
            <div className="flex flex-wrap items-center gap-2 border-b border-amber-100 bg-amber-50/60 px-5 py-3">
                <p dir="ltr" className="min-w-0 flex-1 truncate font-mono text-sm font-semibold text-navy">
                    {domain.host}
                </p>
                <Pill tone="amber">{t('domainAdmin.badgePending')}</Pill>
                <Pill tone="slate">
                    {domain.primary_on_verify
                        ? t('domainAdmin.willBePrimary')
                        : t('domainAdmin.keepsPlatform')}
                </Pill>
            </div>

            <div className="space-y-5 px-5 py-5">
                <div>
                    <h3 className="text-sm font-semibold text-navy">{t('domainAdmin.records.title')}</h3>
                    <p className="mt-1 text-xs leading-relaxed text-slate-500">
                        {t('domainAdmin.records.body')}
                    </p>
                </div>

                <ul className="space-y-3">
                    {domain.records.map((record) => (
                        <li
                            key={`${record.purpose}-${record.type}`}
                            className="rounded-xl border border-slate-200 bg-surface/50 p-4"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    dir="ltr"
                                    className="rounded-md bg-navy px-2 py-0.5 font-mono text-xs font-bold text-white"
                                >
                                    {record.type}
                                </span>
                                <span className="text-sm font-medium text-navy">
                                    {t(`domainAdmin.records.${record.purpose}`)}
                                </span>
                                <Pill tone={record.required ? 'blue' : 'slate'}>
                                    {record.required
                                        ? t('domainAdmin.records.required')
                                        : t('domainAdmin.records.optional')}
                                </Pill>
                            </div>
                            <div className="mt-3 space-y-3">
                                <CopyField
                                    id={`${domain.id}-${record.purpose}-name`}
                                    label={t('domainAdmin.records.name')}
                                    value={record.name}
                                    copiedKey={copiedKey}
                                    onCopy={onCopy}
                                    copyLabel={t('domainAdmin.records.copy')}
                                    copiedLabel={t('domainAdmin.copied')}
                                />
                                <CopyField
                                    id={`${domain.id}-${record.purpose}-value`}
                                    label={t('domainAdmin.records.value')}
                                    value={record.value}
                                    copiedKey={copiedKey}
                                    onCopy={onCopy}
                                    copyLabel={t('domainAdmin.records.copy')}
                                    copiedLabel={t('domainAdmin.copied')}
                                />
                            </div>
                        </li>
                    ))}
                </ul>

                {hasApexAlias ? (
                    <p className="rounded-lg bg-sky-50 px-3 py-2 text-xs leading-relaxed text-sky-900">
                        {t('domainAdmin.records.aliasNote')}
                    </p>
                ) : null}

                <div className="rounded-xl border border-slate-200 p-4">
                    <ul className="space-y-2.5" aria-live="polite">
                        <CheckRow ok={routingOk} label={t('domainAdmin.check.routing')} t={t} />
                        <CheckRow ok={ownershipOk} label={t('domainAdmin.check.ownership')} t={t} />
                    </ul>
                    {errorKey ? (
                        <p className="mt-3 text-xs leading-relaxed text-amber-900">{t(errorKey)}</p>
                    ) : null}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <button type="button" className="btn-navy" disabled={checking} onClick={onCheck}>
                        {checking ? t('domainAdmin.checking') : t('domainAdmin.checkNow')}
                    </button>
                    <button
                        type="button"
                        className="btn-outline text-red-700 hover:border-red-300"
                        onClick={onRemove}
                    >
                        {t('domainAdmin.remove')}
                    </button>
                    <p className="ms-auto flex items-center gap-2 text-xs text-slate-500">
                        <span className="relative flex h-2 w-2" aria-hidden="true">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-green/60" />
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-brand-green" />
                        </span>
                        <span>
                            {t('domainAdmin.autoCheck')} ·{' '}
                            {checked
                                ? t('domainAdmin.lastChecked', {
                                      time: formatAgo(domain.last_checked_at, locale),
                                  })
                                : t('domainAdmin.neverChecked')}
                        </span>
                    </p>
                </div>
            </div>
        </li>
    );
}

function ActiveDomain({ domain, onSetPrimary, onRemove, t }) {
    return (
        <li className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center gap-2">
                <p dir="ltr" className="min-w-0 flex-1 truncate font-mono text-sm font-semibold text-navy">
                    {domain.host}
                </p>
                {domain.is_primary ? <Pill tone="blue">{t('domainAdmin.badgePrimary')}</Pill> : null}
                <Pill tone="green">
                    <CheckIcon className="h-3 w-3" />
                    {t('domainAdmin.badgeVerified')}
                </Pill>
                <Pill tone={domain.ssl_active ? 'green' : 'slate'}>
                    {domain.ssl_active ? t('domainAdmin.sslActive') : t('domainAdmin.sslPending')}
                </Pill>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
                <a href={domain.url} target="_blank" rel="noreferrer" className="btn-outline text-xs">
                    {t('domainAdmin.open')}
                </a>
                {!domain.is_primary ? (
                    <button type="button" className="btn-teal text-xs" onClick={onSetPrimary}>
                        {t('domainAdmin.setPrimary')}
                    </button>
                ) : null}
                {domain.destroy_url ? (
                    <button
                        type="button"
                        className="btn-outline text-xs text-red-700 hover:border-red-300"
                        onClick={onRemove}
                    >
                        {t('domainAdmin.remove')}
                    </button>
                ) : null}
            </div>
        </li>
    );
}

export default function Edit({
    urls = {},
    baseDomain,
    domains = [],
    storeUrl,
    customAllowed = true,
    customLimit = 3,
    customCount = 0,
    pendingTtlDays = 14,
    isLocalInstall = false,
    nav = [],
    user,
    school,
}) {
    const { t, locale } = useLocale();
    const { copiedKey, copy } = useCopy();
    const [checkingId, setCheckingId] = useState(null);

    const form = useForm({ host: '', make_primary: true });

    const customDomains = domains.filter((d) => d.is_custom);
    const pending = customDomains.filter((d) => !d.verified);
    const verifiedCustom = customDomains.filter((d) => d.verified);
    const platformDomain = domains.find((d) => !d.is_custom);
    const atLimit = customLimit > 0 && customCount >= customLimit;
    const hasPending = pending.length > 0;

    const currentStep = hasPending ? 1 : verifiedCustom.length > 0 ? 3 : 0;
    const liveOnCustom = Boolean(urls.primary_is_custom);

    // Poll while DNS is pending so the page flips to "live" without a manual refresh.
    useEffect(() => {
        if (!hasPending) return undefined;

        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['domains', 'urls', 'customCount'], data: { check: 1 } });
            }
        }, POLL_MS);

        return () => clearInterval(timer);
    }, [hasPending]);

    function submit(e) {
        e.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('host'),
        });
    }

    function checkNow(domain) {
        router.post(
            domain.verify_url,
            {},
            {
                preserveScroll: true,
                onStart: () => setCheckingId(domain.id),
                onFinish: () => setCheckingId(null),
            },
        );
    }

    function remove(domain) {
        if (window.confirm(t('domainAdmin.removeConfirm'))) {
            router.delete(domain.destroy_url, { preserveScroll: true });
        }
    }

    return (
        <AppShell title={t('domainAdmin.title')} nav={nav} user={user} school={school}>
            <Head title={t('domainAdmin.title')} />

            <PageHeader title={t('domainAdmin.title')} subtitle={t('domainAdmin.subtitle')} />

            {/* Live address hero */}
            <section className="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-navy to-[#0f1f3d] text-white shadow-sm">
                <div className="flex flex-col gap-5 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-white/70">
                            {t('domainAdmin.liveEyebrow')}
                        </p>
                        <p
                            dir="ltr"
                            className="mt-2 break-all text-start font-mono text-xl font-semibold sm:text-2xl"
                        >
                            {urls.primary_host || urls.platform_host}
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Pill tone="glass">
                                {liveOnCustom
                                    ? t('domainAdmin.liveCustom')
                                    : t('domainAdmin.livePlatform')}
                            </Pill>
                            {liveOnCustom || !isLocalInstall ? (
                                <Pill tone="glass">
                                    {!liveOnCustom || urls.primary_ssl_active
                                        ? t('domainAdmin.sslActive')
                                        : t('domainAdmin.sslPending')}
                                </Pill>
                            ) : null}
                        </div>
                        {liveOnCustom ? (
                            <p className="mt-3 text-sm text-white/75">
                                {t('domainAdmin.fallbackNote', { host: urls.platform_host })}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {urls.primary_url ? (
                            <a
                                href={urls.primary_url}
                                target="_blank"
                                rel="noreferrer"
                                className="btn-teal"
                            >
                                {t('domainAdmin.viewSite')}
                            </a>
                        ) : null}
                        <button
                            type="button"
                            className="btn-outline"
                            onClick={() =>
                                copy('hero', urls.primary_host || urls.platform_host || '')
                            }
                        >
                            {copiedKey === 'hero' ? t('domainAdmin.copied') : t('domainAdmin.copyHost')}
                        </button>
                    </div>
                </div>
                <div className="border-t border-white/10 bg-black/10 px-6 py-4">
                    <Stepper
                        current={currentStep}
                        labels={[
                            t('domainAdmin.step.enter'),
                            t('domainAdmin.step.dns'),
                            t('domainAdmin.step.live'),
                        ]}
                    />
                </div>
            </section>

            <div className="grid gap-6 xl:grid-cols-5">
                <section className="space-y-6 xl:col-span-3">
                    {/* Connect form or plan gate */}
                    {customAllowed ? (
                        <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h2 className="text-lg font-semibold text-navy">
                                        {t('domainAdmin.connectTitle')}
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {t('domainAdmin.connectBody')}
                                    </p>
                                </div>
                                {customLimit > 0 ? (
                                    <Pill tone={atLimit ? 'amber' : 'slate'}>
                                        {t('domainAdmin.usage', {
                                            count: customCount,
                                            limit: customLimit,
                                        })}
                                    </Pill>
                                ) : null}
                            </div>

                            <form onSubmit={submit} className="mt-6 space-y-4">
                                <div>
                                    <InputLabel htmlFor="host" value={t('domainAdmin.hostLabel')} />
                                    <TextInput
                                        id="host"
                                        dir="ltr"
                                        inputMode="url"
                                        autoComplete="off"
                                        autoCapitalize="none"
                                        spellCheck={false}
                                        className="mt-1 block w-full text-start"
                                        value={form.data.host}
                                        placeholder={t('domainAdmin.hostPlaceholder')}
                                        onChange={(e) => form.setData('host', e.target.value)}
                                        disabled={atLimit}
                                        required
                                    />
                                    <InputError message={form.errors.host} className="mt-1" />
                                    <p className="mt-1 text-xs text-slate-500">
                                        {t('domainAdmin.hostHint', { base: baseDomain })}
                                    </p>
                                </div>

                                <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-surface/60 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        className="mt-1 rounded border-slate-300 text-teal focus:ring-teal"
                                        checked={form.data.make_primary}
                                        onChange={(e) => form.setData('make_primary', e.target.checked)}
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-navy">
                                            {t('domainAdmin.makePrimary')}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            {t('domainAdmin.makePrimaryHint')}
                                        </span>
                                    </span>
                                </label>

                                <button
                                    type="submit"
                                    className="btn-navy"
                                    disabled={form.processing || atLimit || !form.data.host.trim()}
                                >
                                    {form.processing
                                        ? t('domainAdmin.connecting')
                                        : t('domainAdmin.connect')}
                                </button>
                            </form>

                            {isLocalInstall ? (
                                <p className="mt-4 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900">
                                    {t('domainAdmin.localNote')}
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                            <h2 className="text-base font-semibold text-amber-950">
                                {t('domainAdmin.locked.title')}
                            </h2>
                            <p className="mt-1 text-sm text-amber-900">{t('domainAdmin.locked.body')}</p>
                        </div>
                    )}

                    {/* Connected domains */}
                    <div>
                        <h2 className="mb-3 text-base font-semibold text-navy">
                            {t('domainAdmin.listTitle')}
                        </h2>

                        {customDomains.length === 0 ? (
                            <p className="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-5 py-8 text-center text-sm text-slate-500">
                                {t('domainAdmin.empty')}
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {pending.map((domain) => (
                                    <PendingDomain
                                        key={domain.id}
                                        domain={domain}
                                        checking={checkingId === domain.id}
                                        onCheck={() => checkNow(domain)}
                                        onRemove={() => remove(domain)}
                                        copiedKey={copiedKey}
                                        onCopy={copy}
                                        t={t}
                                        locale={locale}
                                    />
                                ))}
                                {verifiedCustom.map((domain) => (
                                    <ActiveDomain
                                        key={domain.id}
                                        domain={domain}
                                        onSetPrimary={() =>
                                            router.post(domain.make_primary_url, {}, { preserveScroll: true })
                                        }
                                        onRemove={() => remove(domain)}
                                        t={t}
                                    />
                                ))}
                            </ul>
                        )}

                        {hasPending ? (
                            <p className="mt-3 text-xs text-slate-500">
                                {t('domainAdmin.pendingNote', { days: pendingTtlDays })}
                            </p>
                        ) : null}
                    </div>
                </section>

                <aside className="space-y-6 xl:col-span-2">
                    {/* Dedicated platform URL */}
                    <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div className="px-6 py-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('domainAdmin.platformTitle')}
                                </p>
                                {platformDomain?.is_primary ? (
                                    <Pill tone="blue">{t('domainAdmin.badgePrimary')}</Pill>
                                ) : null}
                                <Pill tone="green">{t('domainAdmin.badgePlatform')}</Pill>
                            </div>
                            <p
                                dir="ltr"
                                className="mt-2 break-all text-start font-mono text-base font-semibold text-navy"
                            >
                                {urls.platform_host}
                            </p>
                            <p className="mt-2 text-sm text-slate-500">{t('domainAdmin.assignedHint')}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 bg-surface/50 px-6 py-4">
                            <a
                                href={urls.platform_url}
                                target="_blank"
                                rel="noreferrer"
                                className="btn-teal text-sm"
                            >
                                {t('domainAdmin.openPlatform')}
                            </a>
                            <button
                                type="button"
                                className="btn-outline text-sm"
                                onClick={() => copy('platform', urls.platform_host || '')}
                            >
                                {copiedKey === 'platform'
                                    ? t('domainAdmin.copied')
                                    : t('domainAdmin.copyHost')}
                            </button>
                            {urls.path_url && urls.path_url !== urls.platform_url ? (
                                <a
                                    href={urls.path_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="btn-outline text-sm"
                                >
                                    {t('domainAdmin.pathPreview')}
                                </a>
                            ) : null}
                        </div>
                    </div>

                    {/* Registrar tips */}
                    <div className="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-navy">{t('domainAdmin.tips.title')}</h2>
                        <ul className="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                            {['www', 'apex', 'cloudflare'].map((tip) => (
                                <li key={tip} className="flex gap-3">
                                    <span
                                        className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-green"
                                        aria-hidden="true"
                                    />
                                    <span>{t(`domainAdmin.tips.${tip}`)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-4 text-xs leading-relaxed text-slate-500">{t('domainAdmin.dnsNote')}</p>
                    </div>
                </aside>
            </div>
        </AppShell>
    );
}
