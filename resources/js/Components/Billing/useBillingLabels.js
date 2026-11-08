import { useLocale } from '@/i18n/LocaleProvider';

/**
 * Display helpers for the annual subscription UI.
 * Submitted values / API enums stay English slugs; only the labels shown to people are translated.
 */
export function useBillingLabels() {
    const { t, locale } = useLocale();

    const lookup = (prefix, value, fallback) => {
        if (value === null || value === undefined || value === '') return fallback ?? t('billing.none');
        const key = `${prefix}.${value}`;
        const translated = t(key);
        return translated === key ? String(value).replaceAll('_', ' ') : translated;
    };

    return {
        t,
        locale,
        schoolType: (v) => lookup('schoolType', v),
        status: (v) => lookup('billing.status', v),
        payStatus: (v) => lookup('billing.payStatus', v),
        method: (v) => lookup('billing.method', v),
        paymentStatus: (v) => lookup('billing.paymentStatus', v),
        priceState: (v) => lookup('platformPricing.state', v),
        payEvent: (v) => lookup('pay.event', v),
        payState: (v) => lookup('pay.state', v),
        dateTime: (iso) => {
            if (!iso) return t('billing.none');
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return String(iso);
            return d.toLocaleString(locale === 'ar' ? 'ar' : locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        date: (iso) => {
            if (!iso) return t('billing.none');
            const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`);
            if (Number.isNaN(d.getTime())) return String(iso);
            return d.toLocaleDateString(locale === 'ar' ? 'ar' : locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
        /** "{days} days left in your free trial" etc. */
        countdown: (billing) => {
            if (!billing) return '';
            const days = billing.days_remaining;
            const phase = billing.status === 'trial' ? 'trial' : 'active';
            if (days === null || days === undefined || days < 0 || !['trial', 'active'].includes(billing.status)) {
                return t('billing.countdown.lapsed');
            }
            if (days === 0) return t(`billing.countdown.${phase}Today`);
            if (days === 1) return t(`billing.countdown.${phase}One`);
            return t(`billing.countdown.${phase}`, { days });
        },
    };
}
