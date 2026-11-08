import { assetUrl } from '@/lib/assetUrl';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

const STEP_GRID = {
    1: 'lg:grid-cols-1',
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
};

function Pin() {
    return (
        <svg
            className="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.8}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" />
            <circle cx="12" cy="9.5" r="2.5" />
        </svg>
    );
}

export default function SchoolAdmissionsSection({
    school,
    name,
    title,
    body,
    steps = [],
    ctaPrimary,
    admissionsUrl,
    images = [],
    accent = '#1683F8',
}) {
    const { t } = useLocale();

    const [mainImage, topImage, bottomImage] = images;

    const address = [school?.address, school?.region].filter(Boolean).join(', ');
    const stepGrid = STEP_GRID[steps.length] ?? 'lg:grid-cols-3';

    return (
        <section id="admissions" className="relative overflow-hidden pb-12 pt-6 sm:pb-14 sm:pt-8" aria-labelledby="admissions-heading">
            <div
                className="pointer-events-none absolute -start-40 top-10 h-96 w-96 rounded-full opacity-[0.07] blur-3xl"
                style={{ backgroundColor: accent }}
                aria-hidden="true"
            />

            <div className="relative mx-auto w-full max-w-6xl px-4 sm:px-6">
                {/* Intro + photo mosaic */}
                <div className="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">
                    <div>
                        <span className="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-navy shadow-soft ring-1 ring-slate-200">
                            <span className="relative flex h-2 w-2">
                                <span
                                    className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60"
                                    style={{ backgroundColor: accent }}
                                />
                                <span className="relative inline-flex h-2 w-2 rounded-full" style={{ backgroundColor: accent }} />
                            </span>
                            {t('publicSite.admissions.badge')}
                        </span>

                        <h2
                            id="admissions-heading"
                            className="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-navy sm:text-[2.25rem]"
                        >
                            {title}
                        </h2>
                        <p className="mt-3 max-w-xl text-[0.95rem] leading-relaxed text-slate-600">{body}</p>

                        <div className="mt-5 flex flex-wrap gap-3">
                            <Link
                                href={admissionsUrl}
                                className="btn-cta rounded-xl px-6 py-3 text-sm text-white shadow-soft"
                                style={{ backgroundColor: accent }}
                            >
                                {ctaPrimary}
                            </Link>
                            <a
                                href="#contact"
                                className="btn-cta rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm text-navy hover:border-navy"
                            >
                                {t('publicSite.admissions.ask')}
                            </a>
                        </div>

                        {address ? (
                            <p className="mt-5 flex items-start gap-2 text-sm text-slate-500">
                                <span className="mt-0.5 shrink-0 text-brand-bright">
                                    <Pin />
                                </span>
                                <span className="min-w-0">
                                    <span className="font-semibold text-navy">{name}</span>
                                    <span aria-hidden="true"> · </span>
                                    {address}
                                </span>
                            </p>
                        ) : null}
                    </div>

                    {/* Photo mosaic */}
                    <div className="relative">
                        <div className="grid h-[14rem] grid-cols-5 grid-rows-[minmax(0,1fr)] gap-3 sm:h-[16rem]">
                            <div className="relative col-span-3 min-h-0 overflow-hidden rounded-3xl bg-slate-200 shadow-card">
                                {mainImage ? (
                                    <img src={assetUrl(mainImage)} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />
                                ) : null}
                            </div>
                            <div className="col-span-2 grid min-h-0 grid-rows-[repeat(2,minmax(0,1fr))] gap-3">
                                <div className="relative min-h-0 overflow-hidden rounded-3xl bg-slate-200 shadow-card">
                                    {topImage ? (
                                        <img src={assetUrl(topImage)} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />
                                    ) : null}
                                </div>
                                <div className="relative min-h-0 overflow-hidden rounded-3xl bg-slate-200 shadow-card">
                                    {bottomImage ? (
                                        <img src={assetUrl(bottomImage)} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Steps */}
                {steps.length ? (
                    <div className="mt-12 sm:mt-14">
                        <div className="mx-auto max-w-2xl text-center">
                            <h3 className="text-xl font-extrabold tracking-tight text-navy sm:text-2xl">
                                {t('publicSite.admissions.pathTitle')}
                            </h3>
                            <p className="mt-1 text-sm leading-relaxed text-slate-500">
                                {t('publicSite.admissions.pathIntro')}
                            </p>
                        </div>

                        <ol className={`mt-6 grid gap-5 sm:grid-cols-2 ${stepGrid}`}>
                            {steps.map((step, index) => (
                                <li
                                    key={`${step.title}-${index}`}
                                    className="relative flex items-start gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:shadow-card sm:p-5"
                                >
                                    {index < steps.length - 1 ? (
                                        <span
                                            className="pointer-events-none absolute -end-5 top-9 hidden w-5 border-t-2 border-dashed border-slate-300 lg:block"
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                    <span
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-base font-extrabold text-white shadow-soft"
                                        style={{ backgroundColor: accent }}
                                    >
                                        {index + 1}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-slate-400">
                                            {t('publicSite.admissions.stepLabel', { n: index + 1 })}
                                        </p>
                                        <h4 className="text-base font-bold leading-snug text-navy">{step.title}</h4>
                                        {step.body ? (
                                            <p className="mt-1 text-sm leading-relaxed text-slate-500">{step.body}</p>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                ) : null}
            </div>
        </section>
    );
}
