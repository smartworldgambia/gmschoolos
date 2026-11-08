import { useLocale } from '@/i18n/LocaleProvider';
import { mapDirectionsUrl, mapEmbedUrl, mapOpenUrl, mapQuery } from '@/lib/schoolMap';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

function Icon({ name }) {
    const common = {
        className: 'h-5 w-5',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.8,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': true,
    };

    switch (name) {
        case 'pin':
            return (
                <svg {...common}>
                    <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" />
                    <circle cx="12" cy="9.5" r="2.5" />
                </svg>
            );
        case 'phone':
            return (
                <svg {...common}>
                    <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z" />
                </svg>
            );
        case 'mail':
            return (
                <svg {...common}>
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="m3 7 9 6 9-6" />
                </svg>
            );
        case 'clock':
            return (
                <svg {...common}>
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            );
        case 'check':
            return (
                <svg {...common} strokeWidth={2.2}>
                    <path d="m5 12.5 4.5 4.5L19 7.5" />
                </svg>
            );
        default:
            return null;
    }
}

function DetailRow({ icon, label, accent, children }) {
    return (
        <div className="flex items-start gap-4">
            <span
                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white ring-1 ring-white/20"
                style={{ color: accent }}
            >
                <Icon name={icon} />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-white/60">{label}</p>
                <div className="mt-1 text-sm font-semibold leading-relaxed text-white">{children}</div>
            </div>
        </div>
    );
}

function Field({ id, label, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="block text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {error ? (
                <p className="mt-1 text-xs font-medium text-red-600" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

const inputClass =
    'block w-full rounded-xl border-slate-200 bg-slate-50/60 px-3.5 py-2.5 text-sm text-navy placeholder:text-slate-400 shadow-none transition focus:border-brand-bright focus:bg-white focus:ring-2 focus:ring-brand-bright/25';

export default function SchoolContactSection({
    school,
    landing = {},
    contactUrl,
    blurb,
    name,
    primary = '#1A2B56',
    accent = '#1683F8',
}) {
    const { t, locale } = useLocale();
    const [sent, setSent] = useState(false);

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
        company_website: '', // honeypot
    });

    const address = [school?.address, school?.region].filter(Boolean).join(', ');
    const phone = school?.phone;
    const email = school?.email;
    const hours = String(landing?.contact_hours ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

    const query = mapQuery({
        lat: landing?.map_lat,
        lng: landing?.map_lng,
        address: school?.address,
        region: school?.region,
        country: school?.country,
    });
    const showMap = landing?.map_enabled !== false;
    const embedUrl = showMap ? mapEmbedUrl(query, locale) : null;
    const directionsUrl = mapDirectionsUrl(query);
    const openUrl = mapOpenUrl(query);

    const submit = (e) => {
        e.preventDefault();
        if (!contactUrl) return;
        form.post(contactUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSent(true);
            },
        });
    };

    const hasAnyDetail = Boolean(address || phone || email || hours.length);

    return (
        <section id="contact" className="bg-surface py-16 sm:py-20" aria-labelledby="contact-heading">
            <div className="mx-auto max-w-6xl px-4 sm:px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-brand-bright">
                        {t('publicSite.section.contact')}
                    </p>
                    <h2
                        id="contact-heading"
                        className="mt-3 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl"
                    >
                        {t('publicSite.contact.title')}
                    </h2>
                    <p className="mt-3 text-sm leading-relaxed text-slate-500 sm:text-base">{blurb}</p>
                </div>

                <div className="mt-10 grid gap-6 lg:grid-cols-5">
                    {/* Details card */}
                    <div
                        className="relative overflow-hidden rounded-3xl p-6 text-white shadow-soft sm:p-8 lg:col-span-2"
                        style={{ backgroundColor: primary }}
                    >
                        <div
                            className="pointer-events-none absolute -end-16 -top-16 h-56 w-56 rounded-full opacity-25 blur-2xl"
                            style={{ backgroundColor: accent }}
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <h3 className="text-lg font-bold">{t('publicSite.contact.detailsTitle')}</h3>
                            <p className="mt-1 text-sm text-white/75">{t('publicSite.contact.detailsIntro')}</p>

                            {hasAnyDetail ? (
                                <div className="mt-7 space-y-6">
                                    {address ? (
                                        <DetailRow icon="pin" label={t('publicSite.contact.address')} accent={accent}>
                                            <p>{address}</p>
                                            {directionsUrl ? (
                                                <a
                                                    href={directionsUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="mt-1.5 inline-block text-xs font-bold text-white/90 underline decoration-white/40 underline-offset-4 hover:text-white"
                                                >
                                                    {t('publicSite.contact.directions')} →
                                                </a>
                                            ) : null}
                                        </DetailRow>
                                    ) : null}
                                    {phone ? (
                                        <DetailRow icon="phone" label={t('publicSite.contact.phone')} accent={accent}>
                                            <a
                                                href={`tel:${String(phone).replace(/\s+/g, '')}`}
                                                dir="ltr"
                                                className="inline-block hover:underline"
                                            >
                                                {phone}
                                            </a>
                                        </DetailRow>
                                    ) : null}
                                    {email ? (
                                        <DetailRow icon="mail" label={t('publicSite.contact.email')} accent={accent}>
                                            <a href={`mailto:${email}`} className="break-all hover:underline">
                                                {email}
                                            </a>
                                        </DetailRow>
                                    ) : null}
                                    {hours.length ? (
                                        <DetailRow icon="clock" label={t('publicSite.contact.hours')} accent={accent}>
                                            {hours.map((line) => (
                                                <p key={line}>{line}</p>
                                            ))}
                                        </DetailRow>
                                    ) : null}
                                </div>
                            ) : (
                                <p className="mt-7 text-sm text-white/75">{t('publicSite.contact.empty')}</p>
                            )}

                            {phone || email ? (
                                <div className="mt-8 flex flex-wrap gap-3">
                                    {phone ? (
                                        <a
                                            href={`tel:${String(phone).replace(/\s+/g, '')}`}
                                            className="btn-cta rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-navy hover:bg-white/90"
                                        >
                                            {t('publicSite.contact.call')}
                                        </a>
                                    ) : null}
                                    {email ? (
                                        <a
                                            href={`mailto:${email}`}
                                            className="btn-cta rounded-xl border border-white/40 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/10"
                                        >
                                            {t('publicSite.contact.writeUs')}
                                        </a>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    </div>

                    {/* Form card */}
                    <div className="rounded-3xl bg-white p-6 shadow-soft ring-1 ring-slate-100 sm:p-8 lg:col-span-3">
                        {sent ? (
                            <div className="flex h-full min-h-[18rem] flex-col items-center justify-center text-center" role="status">
                                <span className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-green/15 text-brand-green">
                                    <Icon name="check" />
                                </span>
                                <h3 className="mt-4 text-lg font-bold text-navy">
                                    {t('publicSite.contact.form.successTitle')}
                                </h3>
                                <p className="mt-2 max-w-sm text-sm text-slate-500">
                                    {t('publicSite.contact.form.successBody')}
                                </p>
                                <button type="button" className="btn-outline mt-6" onClick={() => setSent(false)}>
                                    {t('publicSite.contact.form.another')}
                                </button>
                            </div>
                        ) : (
                            <form onSubmit={submit} noValidate>
                                <h3 className="text-lg font-bold text-navy">{t('publicSite.contact.form.title')}</h3>
                                <p className="mt-1 text-sm text-slate-500">{t('publicSite.contact.form.subtitle')}</p>

                                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                                    <Field id="contact-name" label={t('publicSite.contact.form.name')} error={form.errors.name}>
                                        <input
                                            id="contact-name"
                                            type="text"
                                            autoComplete="name"
                                            required
                                            className={inputClass}
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                        />
                                    </Field>
                                    <Field id="contact-email" label={t('publicSite.contact.form.email')} error={form.errors.email}>
                                        <input
                                            id="contact-email"
                                            type="email"
                                            autoComplete="email"
                                            required
                                            dir="ltr"
                                            className={inputClass}
                                            value={form.data.email}
                                            onChange={(e) => form.setData('email', e.target.value)}
                                        />
                                    </Field>
                                    <Field id="contact-phone" label={t('publicSite.contact.form.phone')} error={form.errors.phone}>
                                        <input
                                            id="contact-phone"
                                            type="tel"
                                            autoComplete="tel"
                                            dir="ltr"
                                            className={inputClass}
                                            value={form.data.phone}
                                            onChange={(e) => form.setData('phone', e.target.value)}
                                        />
                                    </Field>
                                    <Field id="contact-subject" label={t('publicSite.contact.form.subject')} error={form.errors.subject}>
                                        <input
                                            id="contact-subject"
                                            type="text"
                                            className={inputClass}
                                            value={form.data.subject}
                                            onChange={(e) => form.setData('subject', e.target.value)}
                                        />
                                    </Field>
                                    <div className="sm:col-span-2">
                                        <Field id="contact-message" label={t('publicSite.contact.form.message')} error={form.errors.message}>
                                            <textarea
                                                id="contact-message"
                                                rows={5}
                                                required
                                                className={inputClass}
                                                placeholder={t('publicSite.contact.form.messagePlaceholder')}
                                                value={form.data.message}
                                                onChange={(e) => form.setData('message', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </div>

                                {/* Honeypot — hidden from people and assistive tech */}
                                <div className="sr-only" aria-hidden="true">
                                    <label>
                                        Website
                                        <input
                                            type="text"
                                            tabIndex={-1}
                                            autoComplete="off"
                                            value={form.data.company_website}
                                            onChange={(e) => form.setData('company_website', e.target.value)}
                                        />
                                    </label>
                                </div>

                                <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
                                    <p className="max-w-xs text-xs text-slate-400">{t('publicSite.contact.form.privacy')}</p>
                                    <button type="submit" className="btn-teal" disabled={form.processing}>
                                        {form.processing
                                            ? t('publicSite.contact.form.sending')
                                            : t('publicSite.contact.form.send')}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>

                {/* Map */}
                {showMap ? (
                    <div className="mt-6 overflow-hidden rounded-3xl bg-white shadow-soft ring-1 ring-slate-100">
                        {embedUrl ? (
                            <div className="relative">
                                <iframe
                                    title={t('publicSite.contact.map.aria', { name })}
                                    src={embedUrl}
                                    loading="lazy"
                                    referrerPolicy="no-referrer-when-downgrade"
                                    allowFullScreen
                                    className="block h-72 w-full border-0 sm:h-[26rem]"
                                />
                            </div>
                        ) : (
                            <div className="flex h-48 items-center justify-center px-6 text-center text-sm text-slate-500">
                                {t('publicSite.contact.map.empty')}
                            </div>
                        )}
                        {embedUrl ? (
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3.5">
                                <div className="flex min-w-0 items-center gap-3">
                                    <span
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
                                        style={{ backgroundColor: primary }}
                                    >
                                        <Icon name="pin" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-slate-400">
                                            {t('publicSite.contact.map.title')}
                                        </p>
                                        <p className="truncate text-sm font-bold text-navy">{name}</p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    <a href={openUrl} target="_blank" rel="noopener noreferrer" className="btn-outline">
                                        {t('publicSite.contact.openMap')}
                                    </a>
                                    <a href={directionsUrl} target="_blank" rel="noopener noreferrer" className="btn-teal">
                                        {t('publicSite.contact.directions')}
                                    </a>
                                </div>
                            </div>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
