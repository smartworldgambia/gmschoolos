import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { Link } from '@inertiajs/react';

/** The latest announcements addressed to the signed-in person (dashboard widget). */
export default function AnnouncementList({ items = [] }) {
    const { t } = useLocale();

    return (
        <div className="rounded-xl bg-white shadow-sm">
            {items.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-slate-500">{t('dash.noAnnouncements')}</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {items.map((a) => (
                        <li key={a.id} className="px-4 py-3">
                            <div className="flex items-center gap-2">
                                <p className="truncate text-sm font-medium text-navy">{a.title}</p>
                                {a.unread ? <StatusBadge status="info" label={t('ann.new.badge')} /> : null}
                            </div>
                            <p className="mt-1 line-clamp-2 text-sm text-slate-500">{a.body}</p>
                        </li>
                    ))}
                </ul>
            )}
            <div className="border-t border-slate-100 px-4 py-2 text-end">
                <Link href={route('announcements.index')} className="text-sm font-medium text-teal-700 underline">
                    {t('dash.allAnnouncements')}
                </Link>
            </div>
        </div>
    );
}
