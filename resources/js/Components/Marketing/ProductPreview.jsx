import { IconCheck } from '@/Components/Marketing/Icons';
import { useMarketingLocale } from '@/i18n/MarketingLocale';

function LaptopMock() {
    return (
        <div className="relative mx-auto w-full max-w-xl" aria-hidden="true">
            <div className="rounded-t-xl border border-slate-700 bg-slate-800 px-3 pt-3 shadow-2xl">
                <div className="overflow-hidden rounded-t-lg bg-surface">
                    <div className="flex items-center gap-2 border-b border-slate-200 bg-white px-3 py-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-rose-400" />
                        <span className="h-2.5 w-2.5 rounded-full bg-amber-400" />
                        <span className="h-2.5 w-2.5 rounded-full bg-emerald-400" />
                        <span className="ml-2 flex-1 rounded-md bg-slate-100 px-2 py-1 text-[10px] text-slate-500">
                            gambiaschoolos.com/admin
                        </span>
                    </div>
                    <div className="grid gap-3 bg-surface p-3 sm:grid-cols-[140px_1fr]">
                        <div className="hidden rounded-lg bg-navy p-3 text-white sm:block">
                            <p className="text-[10px] font-bold">SchoolOS</p>
                            <ul className="mt-3 space-y-2 text-[9px] text-slate-300">
                                <li className="rounded bg-white/10 px-2 py-1">Dashboard</li>
                                <li className="px-2 py-1">Students</li>
                                <li className="px-2 py-1">Fees</li>
                                <li className="px-2 py-1">LMS</li>
                            </ul>
                        </div>
                        <div className="space-y-3">
                            <div className="grid grid-cols-3 gap-2">
                                {[
                                    { label: 'Students', value: '842', tone: 'text-brand-blue' },
                                    { label: 'Attendance', value: '94%', tone: 'text-brand-green' },
                                    { label: 'Fees collected', value: 'D 1.2M', tone: 'text-teal-600' },
                                ].map((s) => (
                                    <div key={s.label} className="rounded-lg bg-white p-2 shadow-soft">
                                        <p className="text-[9px] text-brand-mute">{s.label}</p>
                                        <p className={`text-sm font-bold ${s.tone}`}>{s.value}</p>
                                    </div>
                                ))}
                            </div>
                            <div className="rounded-lg bg-white p-3 shadow-soft">
                                <p className="text-[10px] font-semibold text-navy">Enrolment trend</p>
                                <div className="mt-2 flex h-16 items-end gap-1.5">
                                    {[40, 55, 48, 70, 62, 80, 74, 88].map((h, i) => (
                                        <div
                                            key={i}
                                            className="flex-1 rounded-t bg-gradient-to-t from-brand-blue to-teal"
                                            style={{ height: `${h}%` }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="mx-auto h-2 w-[70%] rounded-b-lg bg-slate-700" />
            <div className="mx-auto h-1.5 w-[40%] rounded-b bg-slate-600" />
        </div>
    );
}

function PhoneMock() {
    return (
        <div
            className="absolute -bottom-4 right-0 w-[38%] max-w-[150px] rounded-[1.4rem] border-[5px] border-slate-800 bg-white shadow-2xl sm:right-2 sm:w-[34%]"
            aria-hidden="true"
        >
            <div className="mx-auto mt-1.5 h-1 w-10 rounded-full bg-slate-300" />
            <div className="space-y-2 p-2.5 pb-3">
                <div className="rounded-lg bg-navy p-2 text-white">
                    <p className="text-[8px] opacity-70">Student portal</p>
                    <p className="text-[11px] font-bold">Amadou J.</p>
                </div>
                <div className="grid grid-cols-2 gap-1.5">
                    {['Courses', 'Fees', 'Results', 'Notices'].map((label) => (
                        <div key={label} className="rounded-md bg-surface px-1.5 py-2 text-center text-[8px] font-semibold text-navy">
                            {label}
                        </div>
                    ))}
                </div>
                <div className="rounded-md border border-slate-100 p-1.5">
                    <p className="text-[8px] font-semibold text-navy">Next class</p>
                    <p className="text-[7px] text-brand-mute">Mathematics · 10:00</p>
                </div>
            </div>
        </div>
    );
}

export default function ProductPreview() {
    const { t } = useMarketingLocale();
    const benefits = [
        t('solutions.b1'),
        t('solutions.b2'),
        t('solutions.b3'),
        t('solutions.b4'),
        t('solutions.b5'),
    ];

    return (
        <section className="bg-white pb-10 pt-10 sm:pb-12 sm:pt-12" aria-labelledby="solutions-heading">
            <div className="landing-container grid items-center gap-12 lg:grid-cols-2">
                <div className="relative order-2 lg:order-1">
                    <LaptopMock />
                    <PhoneMock />
                </div>

                <div id="solutions" className="order-1 lg:order-2">
                    <p className="landing-section-label">{t('solutions.label')}</p>
                    <h2 id="solutions-heading" className="mt-3 text-2xl font-extrabold text-navy sm:text-3xl">
                        {t('solutions.title')}
                    </h2>
                    <ul className="mt-6 space-y-3">
                        {benefits.map((item) => (
                            <li key={item} className="flex gap-3 text-sm text-brand-ink sm:text-base">
                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-green/15 text-brand-green">
                                    <IconCheck className="h-3.5 w-3.5" />
                                </span>
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}
