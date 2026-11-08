import { FeatureIcon } from '@/Components/Marketing/Icons';

const TONES = {
    blue: 'bg-sky-50 text-brand-blue',
    green: 'bg-emerald-50 text-brand-green',
    red: 'bg-rose-50 text-rose-500',
    yellow: 'bg-amber-50 text-amber-600',
    purple: 'bg-violet-50 text-violet-600',
    indigo: 'bg-indigo-50 text-indigo-600',
    teal: 'bg-teal-50 text-teal-600',
    slate: 'bg-slate-100 text-slate-600',
};

export default function FeatureCard({ title, description, icon, tone = 'blue', href = '#features' }) {
    return (
        <a
            href={href}
            className="group flex h-full flex-col rounded-2xl border border-slate-100 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-card focus-visible:ring-2 focus-visible:ring-brand-blue"
        >
            <span
                className={`flex h-11 w-11 items-center justify-center rounded-xl transition group-hover:scale-105 ${TONES[tone] ?? TONES.blue}`}
            >
                <FeatureIcon name={icon} className="h-5 w-5" />
            </span>
            <h3 className="mt-4 text-base font-bold text-navy">{title}</h3>
            <p className="mt-2 flex-1 text-sm leading-relaxed text-brand-mute">{description}</p>
        </a>
    );
}
