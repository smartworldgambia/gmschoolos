export default function ConsoleBanner({ children, tone = 'info', className = '' }) {
    const warn = tone === 'warn' || tone === 'warning';

    return (
        <div className={`${warn ? 'console-banner-warn' : 'console-banner'} ${className}`.trim()}>
            <span className={warn ? 'console-banner-icon-warn' : 'console-banner-icon'} aria-hidden="true">
                {warn ? (
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.75}
                            d="M12 9v4m0 4h.01M10.3 4.2l-7.4 12.8A2 2 0 004.6 20h14.8a2 2 0 001.7-3l-7.4-12.8a2 2 0 00-3.4 0z"
                        />
                    </svg>
                ) : (
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={1.75}
                            d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z"
                        />
                    </svg>
                )}
            </span>
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
