const ICONS = {
    lesson: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z',
    text: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V5h16v12H6.5A2.5 2.5 0 004 19.5z',
    assignment: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    quiz: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    forum: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    announcement: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
    video: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    document: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    file: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    course: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
};

const TONES = {
    lesson: 'bg-sky-100 text-sky-800',
    text: 'bg-sky-100 text-sky-800',
    assignment: 'bg-amber-100 text-amber-800',
    quiz: 'bg-violet-100 text-violet-800',
    forum: 'bg-teal/15 text-teal-800',
    announcement: 'bg-rose-100 text-rose-800',
    video: 'bg-indigo-100 text-indigo-800',
    document: 'bg-slate-100 text-slate-700',
    file: 'bg-slate-100 text-slate-700',
    link: 'bg-cyan-100 text-cyan-800',
    course: 'bg-navy/10 text-navy',
};

export default function ActivityIcon({ type = 'lesson', className = '', large = false }) {
    const key = ICONS[type] ? type : 'lesson';
    const size = large ? 'h-11 w-11' : 'h-9 w-9';
    const icon = large ? 'h-5 w-5' : 'h-4 w-4';

    return (
        <span className={`inline-flex shrink-0 items-center justify-center rounded-xl ${size} ${TONES[key]} ${className}`} aria-hidden="true">
            <svg className={icon} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                <path strokeLinecap="round" strokeLinejoin="round" d={ICONS[key]} />
            </svg>
        </span>
    );
}
