import EmptyState from '@/Components/SchoolOS/EmptyState';
import SectionCard from '@/Components/SchoolOS/SectionCard';

export default function ChartCard({
    title,
    subtitle,
    actions,
    children,
    empty = false,
    emptyTitle,
    emptyDescription,
    className = '',
}) {
    return (
        <SectionCard title={title} subtitle={subtitle} actions={actions} className={className}>
            {empty ? <EmptyState title={emptyTitle} description={emptyDescription} className="border-0 py-10 shadow-none" /> : children}
        </SectionCard>
    );
}
