import { ErrorSummary } from '@/Components/SchoolOS/Form';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { avatarTone, BackIcon, ClipIcon, DownloadIcon, initials, PlaneIcon } from '@/Components/Comm/MessagesChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import { fmtBytes, fmtWhen } from '@/lib/comm';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef } from 'react';

function dayKey(iso) {
    const d = iso ? new Date(iso) : null;
    if (!d || Number.isNaN(d.getTime())) return '';

    return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

function fmtTime(iso, locale) {
    const d = iso ? new Date(iso) : null;
    if (!d || Number.isNaN(d.getTime())) return '';
    try {
        return new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(d);
    } catch {
        return fmtWhen(iso, locale);
    }
}

function fmtDay(iso, locale) {
    const d = iso ? new Date(iso) : null;
    if (!d || Number.isNaN(d.getTime())) return '';
    try {
        return new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).format(d);
    } catch {
        return fmtWhen(iso, locale);
    }
}

function isPdf(name) {
    return String(name || '').toLowerCase().endsWith('.pdf');
}

export default function MessageThread({ conversation, messages = [], canReply, canLock, moderatorView, canModerate, limits }) {
    const { t, locale } = useLocale();
    const form = useForm({ body: '', attachments: [] });
    const scroller = useRef(null);
    const fileRef = useRef(null);
    const title = conversation.title || t('msg.untitled');
    const roleLine = conversation.class
        ? `${t('msg.group')}${conversation.class ? ` · ${conversation.class}` : ''}`
        : conversation.with?.names
          ? `${t('msg.with')}: ${conversation.with.names}${conversation.with.count > 3 ? ` +${conversation.with.count - 3}` : ''}`
          : conversation.student
            ? `${t('msg.about')}: ${conversation.student}`
            : null;

    const blocks = useMemo(() => {
        const out = [];
        let last = null;
        messages.forEach((m) => {
            const key = dayKey(m.at);
            if (key && key !== last) {
                out.push({ kind: 'day', id: `day-${key}`, label: fmtDay(m.at, locale) });
                last = key;
            }
            out.push({ kind: 'msg', ...m });
        });

        return out;
    }, [messages, locale]);

    useEffect(() => {
        const el = scroller.current;
        if (el) el.scrollTop = el.scrollHeight;
    }, [messages.length]);

    const report = (m) => {
        const reason = window.prompt(t('msg.prompt.report'));
        if (reason && reason.trim()) {
            router.post(route('messages.report', m.id), { reason: reason.trim() }, { preserveScroll: true });
        }
    };

    const hide = (m) => {
        const reason = window.prompt(t('msg.prompt.hide'));
        if (reason && reason.trim()) {
            router.post(route('messages.hide', m.id), { reason: reason.trim() }, { preserveScroll: true });
        }
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex items-start justify-between gap-3 border-b border-surface-muted px-4 py-3 sm:px-5">
                <div className="flex min-w-0 items-start gap-3">
                    <Link href={route('messages.index')} className="btn-outline !px-2 !py-2 lg:hidden" aria-label={t('msg.backToConversations')}>
                        <BackIcon />
                    </Link>
                    <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold ${avatarTone(title)}`}>{initials(title)}</span>
                    <div className="min-w-0">
                        <p className="truncate font-bold text-navy">{title}</p>
                        {roleLine ? <p className="truncate text-xs text-slate-500">{roleLine}</p> : null}
                        <div className="mt-1 flex flex-wrap items-center gap-1.5">
                            {conversation.type === 'class_group' ? <StatusBadge status="info" label={`${t('msg.group')}${conversation.class ? ` · ${conversation.class}` : ''}`} /> : null}
                            {conversation.locked ? <StatusBadge status="red" label={t('msg.locked')} /> : null}
                            {moderatorView ? <StatusBadge status="amber" label={t('msg.moderatorView')} /> : null}
                        </div>
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    {!moderatorView ? (
                        <button type="button" className="btn-outline !px-3 !py-1.5 text-xs" onClick={() => router.post(route('messages.mute', conversation.id), { muted: conversation.muted ? 0 : 1 }, { preserveScroll: true })}>
                            {conversation.muted ? t('msg.unmute') : t('msg.mute')}
                        </button>
                    ) : null}
                    {canLock ? (
                        <button type="button" className="btn-outline !px-3 !py-1.5 text-xs" onClick={() => router.post(route(conversation.locked ? 'messages.unlock' : 'messages.lock', conversation.id), {}, { preserveScroll: true })}>
                            {conversation.locked ? t('msg.unlock') : t('msg.lock')}
                        </button>
                    ) : null}
                    <Link href={route(moderatorView ? 'messages.moderation' : 'messages.index')} className="btn-outline !px-3 !py-1.5 text-xs hidden lg:inline-flex">
                        {t('msg.back')}
                    </Link>
                </div>
            </div>

            {moderatorView ? <p className="mx-4 mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 sm:mx-5">{t('msg.moderatorNotice')}</p> : null}

            <div ref={scroller} className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 sm:px-5">
                {blocks.map((item) => {
                    if (item.kind === 'day') {
                        return (
                            <p key={item.id} className="py-1 text-center text-[11px] font-semibold text-slate-400">
                                {item.label}
                            </p>
                        );
                    }
                    const m = item;

                    return (
                        <div key={m.id} className={`flex gap-2 ${m.mine ? 'justify-end' : 'justify-start'}`}>
                            {!m.mine ? (
                                <span className={`mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${avatarTone(m.author)}`}>
                                    {initials(m.author || t('msg.removedUser'))}
                                </span>
                            ) : null}
                            <div className={`max-w-[85%] rounded-2xl px-4 py-3 sm:max-w-[70%] ${m.mine ? 'rounded-se-md bg-[#DDF8EB]' : 'rounded-ss-md bg-[#F0F4F9]'} ${m.hidden ? 'opacity-80 ring-1 ring-red-200' : ''}`}>
                                {!m.mine && conversation.type === 'class_group' ? (
                                    <p className="mb-1 text-xs font-semibold text-slate-500">
                                        {m.author ?? t('msg.removedUser')}
                                        {m.author_kind === 'staff' ? ` · ${t('msg.staff')}` : ''}
                                    </p>
                                ) : null}
                                {m.hidden && m.body === null ? (
                                    <p className="text-sm italic text-slate-500">{t('msg.hiddenBody')}</p>
                                ) : (
                                    <>
                                        {m.hidden ? (
                                            <p className="mb-1 text-xs font-semibold text-red-700">
                                                {t('msg.hiddenByMod')}
                                                {m.hidden_reason ? `: ${m.hidden_reason}` : ''}
                                            </p>
                                        ) : null}
                                        {m.body ? <p className="whitespace-pre-wrap break-words text-sm text-navy">{m.body}</p> : null}
                                    </>
                                )}
                                {m.attachments?.length > 0 ? (
                                    <ul className="mt-2 space-y-2">
                                        {m.attachments.map((a) => (
                                            <li key={a.id}>
                                                <a
                                                    href={route('messages.attachments.show', a.id)}
                                                    className="flex items-center gap-3 rounded-xl border border-surface-muted bg-white px-3 py-2 text-sm shadow-soft"
                                                    download
                                                >
                                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold text-white ${isPdf(a.name) ? 'bg-red-500' : 'bg-brand-blue'}`}>
                                                        {isPdf(a.name) ? 'PDF' : String(a.name).split('.').pop()?.slice(0, 4).toUpperCase() || 'FILE'}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate font-medium text-navy">{a.name}</span>
                                                        <span className="text-xs text-slate-400">{fmtBytes(a.size)}</span>
                                                    </span>
                                                    <DownloadIcon />
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                                <div className="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-400">
                                    <span>{fmtTime(m.at, locale)}</span>
                                    <span className="flex items-center gap-3">
                                        {m.reported ? <span>{t('msg.reported')}</span> : null}
                                        {m.can_report ? (
                                            <button type="button" className="font-medium text-slate-500 underline" onClick={() => report(m)}>
                                                {t('msg.report')}
                                            </button>
                                        ) : null}
                                        {canModerate && moderatorView && m.report_open ? (
                                            <button type="button" className="font-medium text-red-700 underline" onClick={() => hide(m)}>
                                                {t('msg.hide')}
                                            </button>
                                        ) : null}
                                    </span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {canReply ? (
                <form
                    className="border-t border-surface-muted p-3 sm:p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('messages.reply', conversation.id), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <ErrorSummary errors={form.errors} />
                    {form.data.attachments.length > 0 ? (
                        <p className="mb-2 truncate text-xs text-slate-500">{form.data.attachments.map((f) => f.name).join(', ')}</p>
                    ) : null}
                    <div className="flex items-end gap-2 rounded-[1.6rem] border border-surface-muted bg-white px-2 py-1.5 shadow-soft focus-within:border-teal focus-within:ring-2 focus-within:ring-teal/30">
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            className="sr-only"
                            onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, limits.max_files))}
                        />
                        <button type="button" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-surface hover:text-navy" aria-label={t('msg.attachFile')} onClick={() => fileRef.current?.click()}>
                            <ClipIcon />
                        </button>
                        <label className="sr-only" htmlFor="msg-composer">
                            {t('msg.reply')}
                        </label>
                        <textarea
                            id="msg-composer"
                            rows={1}
                            value={form.data.body}
                            maxLength={limits.body_max}
                            placeholder={t('msg.typeMessage')}
                            onChange={(e) => form.setData('body', e.target.value)}
                            className="max-h-32 min-h-[2.5rem] min-w-0 flex-1 resize-none border-0 bg-transparent py-2 text-sm text-navy placeholder:text-slate-400 focus:ring-0"
                            required
                        />
                        <button type="submit" className="btn-teal !h-10 !w-10 !rounded-full !px-0 !py-0" disabled={form.processing} aria-label={t('msg.send')}>
                            <PlaneIcon />
                        </button>
                    </div>
                </form>
            ) : (
                <p className="border-t border-surface-muted px-4 py-4 text-center text-sm text-slate-500">
                    {moderatorView ? t('msg.noReplyModerator') : conversation.locked ? t('msg.noReplyLocked') : t('msg.noReply')}
                </p>
            )}
        </div>
    );
}
