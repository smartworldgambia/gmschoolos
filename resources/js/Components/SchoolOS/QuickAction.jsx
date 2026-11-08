import { Link } from '@inertiajs/react';

const tones = {
    teal: 'bg-teal-50 text-teal-700 hover:bg-teal-100 focus-visible:ring-teal-500',
    blue: 'bg-blue-50 text-blue-700 hover:bg-blue-100 focus-visible:ring-blue-500',
    purple: 'bg-purple-50 text-purple-700 hover:bg-purple-100 focus-visible:ring-purple-500',
    orange: 'bg-orange-50 text-orange-700 hover:bg-orange-100 focus-visible:ring-orange-500',
    navy: 'bg-navy-50 text-navy hover:bg-navy-100 focus-visible:ring-navy-400',
};

export default function QuickAction({
    href,
    onClick,
    label,
    icon,
    tone = 'teal',
    className = '',
    ...props
}) {
    const palette = tones[tone] ?? tones.teal;
    const classes = `flex flex-col items-start gap-3 rounded-xl p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ${palette} ${className}`;

    const content = (
        <>
            {icon ? (
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-white/70" aria-hidden="true">
                    {icon}
                </span>
            ) : null}
            <span className="text-sm font-semibold">{label}</span>
        </>
    );

    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {content}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} className={classes} {...props}>
            {content}
        </button>
    );
}
