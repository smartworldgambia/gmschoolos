import ActivityIcon from '@/Components/Lms/ActivityIcon';
import LmsEmptyState from '@/Components/Lms/LmsEmptyState';
import WorkStatus from '@/Components/Lms/WorkStatus';
import { fmt } from '@/lib/lms';
import { Link } from '@inertiajs/react';

export default function Timeline({ items = [], t, title }) {
    if (!items.length) {
        return (
            <section className="console-card">
                <h2 className="mb-3 text-base font-semibold text-navy">{title || t('lms.dash.timeline')}</h2>
                <LmsEmptyState title={t('lms.dash.noTimeline')} body={t('lms.dash.noTimelineHelp')} />
            </section>
        );
    }

    return (
        <section className="console-card">
            <h2 className="mb-3 text-base font-semibold text-navy">{title || t('lms.dash.timeline')}</h2>
            <ol className="divide-y divide-slate-100">
                {items.map((item) => (
                    <li key={`${item.kind}-${item.id}`}>
                        <Link href={item.url} className="lms-activity -mx-2">
                            <ActivityIcon type={item.kind} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium text-navy">{item.title}</p>
                                <p className="truncate text-xs text-slate-500">
                                    {item.course}
                                    {item.when ? ` · ${fmt(item.when)}` : ''}
                                    {item.to_mark ? ` · ${t('lms.assignment.toMark')}: ${item.to_mark}` : ''}
                                </p>
                            </div>
                            <WorkStatus state={item.state} t={t} />
                        </Link>
                    </li>
                ))}
            </ol>
        </section>
    );
}
