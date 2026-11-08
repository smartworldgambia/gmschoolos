import { assetUrl } from '@/lib/assetUrl';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

export default function TestimonialSection({ testimonial = null }) {
    const { t } = useMarketingLocale();
    const isPlaceholder = !testimonial?.quote;

    const data = isPlaceholder
        ? {
              quote: t('trust.placeholder'),
              name: '—',
              role: '',
              school: '',
              rating: 5,
          }
        : testimonial;

    return (
        <section className="bg-emerald-50/70 pb-16 pt-10 sm:pb-20 sm:pt-12" aria-labelledby="trust-heading">
            <div className="landing-container grid items-center gap-10 lg:grid-cols-[0.9fr_1.1fr]">
                <div id="resources" className="max-w-xl">
                    <p className="landing-section-label">{t('trust.label')}</p>
                    <h2 id="trust-heading" className="mt-3 text-2xl font-extrabold text-navy sm:text-3xl">
                        {t('trust.title')}
                    </h2>
                    {isPlaceholder ? (
                        <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            {t('trust.placeholder')}
                        </p>
                    ) : (
                        <p className="mt-3 text-sm text-brand-mute">{t('trust.subtitle')}</p>
                    )}
                </div>

                <figure className="rounded-2xl bg-white p-6 shadow-card sm:p-8">
                    <div className="flex gap-1 text-amber-400" aria-label={`${data.rating ?? 5} out of 5 stars`}>
                        {Array.from({ length: data.rating ?? 5 }).map((_, i) => (
                            <span key={i} aria-hidden="true">
                                ★
                            </span>
                        ))}
                    </div>
                    <blockquote className="mt-4 text-lg font-medium leading-relaxed text-navy sm:text-xl">
                        “{data.quote}”
                    </blockquote>
                    <figcaption className="mt-6 flex items-center gap-3">
                        {data.photo_url ? (
                            <img
                                src={assetUrl(data.photo_url)}
                                alt=""
                                className="h-12 w-12 rounded-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-navy text-sm font-bold text-white">
                                {(data.name ?? 'S').slice(0, 1)}
                            </span>
                        )}
                        <div>
                            <p className="font-bold text-navy">{data.name}</p>
                            <p className="text-sm text-brand-mute">
                                {data.role}
                                {data.school ? `, ${data.school}` : ''}
                            </p>
                        </div>
                    </figcaption>
                </figure>
            </div>
        </section>
    );
}
