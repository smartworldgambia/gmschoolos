import AppShell from '@/Layouts/AppShell';
import { Head, usePage } from '@inertiajs/react';

export default function ProfileLayout({ title, children, className = '' }) {
    const page = usePage();

    return (
        <AppShell
            title={title}
            nav={page.props.nav ?? []}
            user={page.props.user}
            school={page.props.school}
            academicYear={page.props.academicYear}
        >
            <Head title={title} />
            <div className={`profile-shell ${className}`}>{children}</div>
        </AppShell>
    );
}
