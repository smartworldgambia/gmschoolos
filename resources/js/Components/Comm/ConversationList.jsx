import { avatarTone, ComposeIcon, EmptyChatIllustration, initials, SearchIcon } from '@/Components/Comm/MessagesChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import { fmtAgo } from '@/lib/comm';
import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function ConversationList({ conversations = [], activeId = null, canStart = false }) {
    const { t, locale } = useLocale();
    const [q, setQ] = useState('');
    const [tab, setTab] = useState('all');

    const unreadCount = conversations.filter((c) => c.unread).length;

    const rows = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return conversations.filter((c) => {
            if (tab === 'unread' && !c.unread) return false;
            if (!needle) return true;
            const hay = `${c.title ?? ''} ${c.with?.names ?? ''} ${c.snippet ?? ''} ${c.class ?? ''} ${c.student ?? ''}`.toLowerCase();

            return hay.includes(needle);
        });
    }, [conversations, q, tab]);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="border-b border-surface-muted p-3 sm:p-4">
                <label className="flex items-center gap-2 rounded-full border border-surface-muted bg-white px-3.5 py-2.5 focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                    <SearchIcon />
                    <span className="sr-only">{t('msg.searchConversations')}</span>
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={t('msg.searchConversations')}
                        className="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-navy placeholder:text-slate-400 focus:ring-0"
                    />
                </label>
                <div className="mt-3 flex flex-wrap gap-1.5" role="tablist" aria-label={t('msg.title')}>
                    {[
                        { id: 'all', label: t('msg.filter.all') },
                        { id: 'unread', label: t('msg.filter.unread'), count: unreadCount },
                    ].map((item) => {
                        const on = tab === item.id;

                        return (
                            <button
                                key={item.id}
                                type="button"
                                role="tab"
                                aria-selected={on}
                                onClick={() => setTab(item.id)}
                                className={`inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold transition ${
                                    on ? 'bg-teal text-white shadow-sm' : 'bg-[#F0F4F9] text-navy hover:bg-slate-100'
                                }`}
                            >
                                {item.label}
                                {item.id === 'unread' && item.count > 0 ? (
                                    <span className={`inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold ${on ? 'bg-white/20 text-white' : 'bg-teal text-white'}`}>
                                        {item.count}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>
            </div>

            {rows.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center px-4 py-10 text-center">
                    <EmptyChatIllustration />
                    <p className="mt-4 text-sm font-semibold text-navy">{q || tab === 'unread' ? t('msg.emptyFilter') : t('msg.empty')}</p>
                    <p className="mt-1 max-w-xs text-xs leading-relaxed text-slate-500">
                        {q || tab === 'unread' ? t('msg.emptyFilterHelp') : canStart ? t('msg.emptyHelpStart') : t('msg.emptyHelp')}
                    </p>
                    {canStart && !q && tab !== 'unread' ? (
                        <Link href={route('messages.create')} className="btn-teal mt-4 gap-2">
                            <ComposeIcon />
                            {t('msg.composeCta')}
                        </Link>
                    ) : null}
                </div>
            ) : (
                <ul className="min-h-0 flex-1 overflow-y-auto">
                    {rows.map((c) => {
                        const active = Number(activeId) === Number(c.id);
                        const name = c.title || t('msg.untitled');

                        return (
                            <li key={c.id}>
                                <Link
                                    href={route('messages.show', c.id)}
                                    className={`flex items-start gap-3 border-b border-surface-muted px-3 py-3 sm:px-4 ${
                                        active ? 'border-s-4 border-s-teal bg-[#E7F8F4]' : 'border-s-4 border-s-transparent hover:bg-surface'
                                    }`}
                                >
                                    <span className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ${avatarTone(name)}`}>
                                        {initials(name)}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className={`truncate text-sm text-navy ${c.unread || active ? 'font-bold' : 'font-semibold'}`}>{name}</p>
                                            <span className="shrink-0 text-[11px] text-slate-400">{fmtAgo(c.at, locale)}</span>
                                        </div>
                                        <p className="mt-0.5 line-clamp-1 text-xs text-slate-500">
                                            {c.snippet_hidden ? t('msg.hiddenSnippet') : `${c.mine ? `${t('msg.you')}: ` : ''}${c.snippet ?? ''}`}
                                        </p>
                                        {c.muted || c.locked ? (
                                            <p className="mt-0.5 text-[11px] text-slate-400">
                                                {c.locked ? t('msg.locked') : t('msg.muted')}
                                            </p>
                                        ) : null}
                                    </div>
                                    {c.unread ? <span className="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-teal" aria-hidden="true" /> : null}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
