import { avatarTone, initials, SearchIcon } from '@/Components/Comm/MessagesChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import { roleLabel } from '@/lib/comm';
import { useMemo, useState } from 'react';

function personMeta(t, person) {
    if (person.class) {
        return person.class;
    }
    if (person.kind === 'student' || person.role === 'student') {
        return t('msg.who.classmate');
    }
    if (person.role) {
        return roleLabel(t, person.role);
    }

    return t('msg.staff');
}

function PersonRow({ person, selected, onSelect, t }) {
    const meta = personMeta(t, person);

    return (
        <button
            type="button"
            onClick={() => onSelect(person)}
            className={`flex w-full items-center gap-3 px-3 py-2.5 text-start transition ${
                selected ? 'bg-teal/10' : 'hover:bg-surface'
            }`}
        >
            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ${avatarTone(person.name)}`}>
                {initials(person.name)}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-navy">{person.name}</span>
                {meta ? <span className="block truncate text-xs text-slate-500">{meta}</span> : null}
            </span>
            {selected ? <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-teal" aria-hidden="true" /> : null}
        </button>
    );
}

export default function RecipientPicker({ groups = [], value, onChange, error }) {
    const { t } = useLocale();
    const [q, setQ] = useState('');

    const people = useMemo(() => groups.flatMap((g) => g.people || []), [groups]);
    const selected = people.find((p) => String(p.id) === String(value)) ?? null;

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return groups
            .map((g) => ({
                ...g,
                people: (g.people || []).filter((p) => {
                    if (!needle) return true;
                    const hay = `${p.name ?? ''} ${p.role ?? ''} ${p.class ?? ''} ${p.kind ?? ''}`.toLowerCase();

                    return hay.includes(needle);
                }),
            }))
            .filter((g) => g.people.length > 0);
    }, [groups, q]);

    const total = people.length;

    return (
        <div>
            <div className={`rounded-2xl border bg-white ${error ? 'border-red-300' : 'border-surface-muted'} focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30`}>
                {selected ? (
                    <div className="flex items-center gap-2 px-3 py-2">
                        <span className="inline-flex min-w-0 items-center gap-2 rounded-full bg-teal/10 py-1 pe-1 ps-1">
                            <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ${avatarTone(selected.name)}`}>
                                {initials(selected.name)}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-navy">{selected.name}</span>
                                <span className="block truncate text-[11px] text-slate-500">{personMeta(t, selected)}</span>
                            </span>
                            <button
                                type="button"
                                className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-navy"
                                aria-label={t('msg.clearRecipient')}
                                onClick={() => onChange('')}
                            >
                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                ) : (
                    <label className="flex items-center gap-2 px-3.5 py-2.5">
                        <SearchIcon />
                        <span className="sr-only">{t('msg.searchPeople')}</span>
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('msg.searchPeople')}
                            className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-navy placeholder:text-slate-400 focus:ring-0"
                            autoComplete="off"
                        />
                    </label>
                )}
            </div>
            {error ? <p className="mt-1 text-xs text-red-600">{error}</p> : null}

            {selected ? null : (
                <div className="mt-2 max-h-72 overflow-y-auto rounded-2xl border border-surface-muted bg-white">
                    {total === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">{t('msg.noRecipients')}</p>
                    ) : filtered.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-500">{t('msg.noMatch')}</p>
                    ) : (
                        filtered.map((g) => (
                            <section key={g.id} className="border-b border-surface-muted last:border-b-0">
                                <h3 className="sticky top-0 bg-[#F7FAFC] px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                    {g.label}
                                    <span className="ms-1.5 font-semibold text-slate-400">{g.people.length}</span>
                                </h3>
                                <ul>
                                    {g.people.map((person) => (
                                        <li key={person.id}>
                                            <PersonRow person={person} selected={String(person.id) === String(value)} onSelect={(p) => onChange(String(p.id))} t={t} />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
