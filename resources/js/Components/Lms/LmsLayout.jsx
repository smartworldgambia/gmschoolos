import { CourseHeroArt, SubjectGlyph, subjectHeroClass } from '@/Components/Lms/LmsChrome';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { tabCopy, subjectKind, useLmsTone } from '@/lib/lmsTone';
import AppShell from '@/Layouts/AppShell';
import { assetUrl } from '@/lib/assetUrl';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Page chrome for every LMS screen: light course banner, underline tabs, optional page header. */
export default function LmsLayout({ title, subtitle, course, author = false, active = '', actions = null, children, showStatus = true, chrome = 'auto' }) {
    const { t } = useLocale();
    const page = usePage();
    const { nav = [], user, school, academicYear } = page.props;
    const tone = useLmsTone();
    const [hash, setHash] = useState(() => (typeof window !== 'undefined' ? window.location.hash : ''));
    const kind = course ? subjectKind(course) : 'default';

    useEffect(() => {
        const sync = () => setHash(window.location.hash);
        sync();
        window.addEventListener('hashchange', sync);

        return () => window.removeEventListener('hashchange', sync);
    }, [page.url]);

    const showUrl = course ? route('lms.courses.show', course.id) : '';
    const onOverview = active === 'overview';
    const showPageHeader = chrome === 'page' || (chrome === 'auto' && !course);

    const tabs = course
        ? [
              { key: 'overview', href: showUrl, show: true },
              { key: 'content', href: onOverview ? '#content' : `${showUrl}#content`, hash: '#content', show: true },
              { key: 'assignments', href: onOverview ? '#assignments' : `${showUrl}#assignments`, hash: '#assignments', show: true },
              { key: 'quizzes', href: onOverview ? '#quizzes' : `${showUrl}#quizzes`, hash: '#quizzes', show: !tone.nursery },
              { key: 'forum', href: route('lms.courses.forum', course.id), show: true },
              { key: 'gradebook', href: route('lms.courses.gradebook', course.id), show: true },
              { key: 'announcements', href: route('lms.courses.announcements', course.id), show: true },
              { key: 'people', href: route('lms.courses.people', course.id), show: author },
              { key: 'progress', href: route('lms.courses.progress', course.id), show: author },
          ].filter((x) => x.show)
        : [];

    const tabActive = (tab) => {
        if (tab.hash) {
            return onOverview && hash === tab.hash;
        }
        if (tab.key === 'overview') {
            return onOverview && !hash;
        }

        return active === tab.key;
    };

    const goOverview = (e) => {
        if (!onOverview) {
            return;
        }
        e.preventDefault();
        const clean = window.location.pathname + window.location.search;
        window.history.replaceState(null, '', clean);
        setHash('');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <AppShell title={title} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={title} />
            <div className={tone.shellClass} style={{ '--lms-primary': tone.primaryColor, '--lms-accent': tone.accentColor }}>
                {course ? (
                    <div className="lms-course-banner mb-5">
                        <div className="lms-banner-art">
                            <CourseHeroArt kind={kind} />
                        </div>
                        <nav className="relative z-[1] mb-3 flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label={t('lms.crumb.label')}>
                            <Link href={route('lms.courses.index')} className="hover:text-navy hover:underline">
                                {t('lms.title')}
                            </Link>
                            <span aria-hidden="true">/</span>
                            <Link href={route('lms.courses.show', course.id)} className="font-medium text-navy hover:underline">
                                {course.title}
                            </Link>
                            {active && active !== 'overview' ? (
                                <>
                                    <span aria-hidden="true">/</span>
                                    <span>{tabCopy(t, active, tone.band, author)}</span>
                                </>
                            ) : null}
                            {showStatus && author ? <StatusBadge status={course.status} label={enumLabel(t, 'lms.status', course.status)} /> : null}
                        </nav>
                        <div className="relative z-[1] flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div className="flex min-w-0 items-start gap-3 sm:gap-4">
                                <span className={`lms-hero-icon ${subjectHeroClass(kind)}`}>
                                    {tone.logoUrl && !course.subject ? (
                                        <img src={assetUrl(tone.logoUrl)} alt="" className="h-8 w-8 rounded-md bg-white object-contain p-0.5" />
                                    ) : (
                                        <SubjectGlyph kind={kind} compact />
                                    )}
                                </span>
                                <div className="min-w-0 lg:pe-44">
                                    {course.code ? <span className="lms-code-badge">{course.code}</span> : null}
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                        {course.subject || tone.schoolName}
                                        {tone.className ? ` · ${tone.className}` : ''}
                                    </p>
                                    <h1 className={`lms-title mt-1 font-bold tracking-tight text-navy ${tone.nursery ? 'text-2xl sm:text-3xl' : 'text-[26px] sm:text-[30px]'}`}>{title}</h1>
                                    {subtitle ? <p className="mt-1 max-w-2xl text-sm text-slate-500">{subtitle}</p> : null}
                                </div>
                            </div>
                            {actions ? <div className="flex flex-wrap items-center gap-2 xl:max-w-xl xl:justify-end">{actions}</div> : null}
                        </div>
                    </div>
                ) : showPageHeader ? (
                    <PageHeader title={title} subtitle={subtitle} actions={actions} />
                ) : null}

                {tabs.length ? (
                    <nav className="lms-course-nav" aria-label={t('lms.tab.label')}>
                        {tabs.map((tab) => {
                            const className = `lms-nav-pill ${tabActive(tab) ? 'lms-nav-pill-active' : ''}`;
                            const label = tabCopy(t, tab.key, tone.band, author);

                            if (tab.hash && onOverview) {
                                return (
                                    <a key={tab.key} href={tab.href} className={className}>
                                        {label}
                                    </a>
                                );
                            }

                            return (
                                <Link key={tab.key} href={tab.href} className={className} onClick={tab.key === 'overview' ? goOverview : undefined}>
                                    {label}
                                </Link>
                            );
                        })}
                    </nav>
                ) : null}
                {children}
            </div>
        </AppShell>
    );
}
