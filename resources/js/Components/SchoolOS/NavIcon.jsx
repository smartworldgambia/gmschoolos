const paths = {
    landing: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18',
    overview: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    dashboard: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
    schools: 'M3 21V8l9-5 9 5v13M9 21v-6h6v6',
    registrations: 'M9 12h6M9 16h6M8 4h8a2 2 0 012 2v14l-6-3-6 3V6a2 2 0 012-2z',
    billing: 'M4 7h16M4 12h16M4 17h10',
    payments: 'M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z',
    integrations: 'M8 9H5a2 2 0 00-2 2v6h5M16 15h3a2 2 0 002-2V7h-5M8 13h8',
    pricing: 'M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-14v2m0 14v2',
    marketing: 'M4 12l16-7-4 14-5-5-7-2z',
    monitoring: 'M3 12h4l3 8 4-16 3 8h4',
    ops: 'M12 8v4l3 3M12 22a10 10 0 110-20 10 10 0 010 20z',
    backups: 'M4 7a8 4 0 0116 0v10a8 4 0 01-16 0V7zm0 5a8 4 0 0016 0',
    scale: 'M4 19V5m0 14h16M8 15v4m4-8v8m4-5v5',
    capacity: 'M4 20h16M6 16V8m6 8V4m6 12v-6',
    students: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM6 20a6 6 0 0112 0',
    academics: 'M4 19V6l8-3 8 3v13M4 19l8 3 8-3',
    attendance: 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14H5V6a2 2 0 012-2z',
    timetable: 'M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    exams: 'M9 12h6M9 16h6M8 4h8v4H8V4zm-1 0H5a2 2 0 00-2 2v14h18V6a2 2 0 00-2-2h-2',
    fees: 'M12 8c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3m0-14v2m0 14v2',
    lms: 'M4 6h16v12H4zM8 10h8M8 14h5',
    hr: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 20a5 5 0 0110 0M16 20a4 4 0 014 0',
    library: 'M4 19a2 2 0 012-2h12V5H6a2 2 0 00-2 2v12zm0 0a2 2 0 002 2h12',
    inventory: 'M21 16V8l-9-5-9 5v8l9 5 9-5z',
    procurement: 'M6 6h15l-1.5 9h-12L6 6zm0 0L5 3H2m7 16a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z',
    transport: 'M4 16V8a2 2 0 012-2h9l5 5v5H4zm2 4a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm12 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
    boarding: 'M3 21V8l9-4 9 4v13M9 21v-6h6v6',
    safeguarding: 'M12 3l8 4v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z',
    events: 'M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    reports: 'M8 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5H3v14a2 2 0 002 2z',
    reportCards: 'M8 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5H3v14a2 2 0 002 2z',
    exports: 'M12 3v12m0 0l-4-4m4 4l4-4M5 21h14',
    messages: 'M21 12a8 8 0 11-16 0 8 8 0 0116 0zm-4 5l3 3v-5',
    announcements: 'M11 5L6 9H3v6h3l5 4V5zm7 1a6 6 0 010 12',
    accounts: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM6 20a6 6 0 0112 0',
    website: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18',
    domain: 'M12 3a9 9 0 100 18 9 9 0 000-18zM2 12h20',
    admissions: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 20v-1a5 5 0 0110 0v1M19 8v6m-3-3h6',
    setup: 'M12 8a4 4 0 100 8 4 4 0 000-8zm8.5 4a8.5 8.5 0 01-.4 2.2l2 1.5-2 3.5-2.4-.5a8.6 8.6 0 01-1.9 1.1L15 22h-6l-.8-2.2a8.6 8.6 0 01-1.9-1.1l-2.4.5-2-3.5 2-1.5A8.5 8.5 0 013.5 12c0-.8.1-1.5.4-2.2l-2-1.5 2-3.5 2.4.5a8.6 8.6 0 011.9-1.1L9 2h6l.8 2.2a8.6 8.6 0 011.9 1.1l2.4-.5 2 3.5-2 1.5c.3.7.4 1.4.4 2.2z',
};

export default function NavIcon({ name, className = 'h-5 w-5' }) {
    const d = paths[name] ?? 'M4 6h16M4 12h16M4 18h7';

    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.75} d={d} />
        </svg>
    );
}
