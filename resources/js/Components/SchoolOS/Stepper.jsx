export default function Stepper({ steps = [], currentStep = 0, className = '' }) {
    return (
        <nav aria-label="Progress" className={className}>
            <ol className="flex items-center justify-between gap-2">
                {steps.map((step, index) => {
                    const isComplete = index < currentStep;
                    const isCurrent = index === currentStep;
                    const label =
                        typeof step === 'string' ? step : step.label ?? step.name;

                    return (
                        <li
                            key={label}
                            className="relative flex flex-1 flex-col items-center text-center"
                        >
                            {index < steps.length - 1 ? (
                                <div
                                    className={`absolute left-[calc(50%+1.25rem)] right-[calc(-50%+1.25rem)] top-5 h-0.5 ${
                                        isComplete ? 'bg-teal' : 'bg-slate-200'
                                    }`}
                                    aria-hidden="true"
                                />
                            ) : null}

                            <div
                                className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold ${
                                    isComplete
                                        ? 'bg-teal text-white'
                                        : isCurrent
                                          ? 'bg-navy text-white ring-4 ring-teal/20'
                                          : 'bg-slate-100 text-slate-400'
                                }`}
                                aria-current={isCurrent ? 'step' : undefined}
                            >
                                {isComplete ? (
                                    <svg
                                        className="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                ) : (
                                    index + 1
                                )}
                            </div>
                            <span
                                className={`mt-2 hidden text-xs font-medium sm:block ${
                                    isCurrent || isComplete
                                        ? 'text-navy'
                                        : 'text-slate-400'
                                }`}
                            >
                                {label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
