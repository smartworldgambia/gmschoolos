import ConversationList from '@/Components/Comm/ConversationList';
import { ComposeIcon, EmptyChatIllustration, MessagesActions, MessagesHeader } from '@/Components/Comm/MessagesChrome';
import { useLocale } from '@/i18n/LocaleProvider';
import { cacheInbox } from '@/lib/messagesInbox';
import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';

export default function Index({ conversations = [], canStart = false, canModerate = false, openReports = 0, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    cacheInbox(conversations);

    return (
        <AppShell title={t('msg.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('msg.title')} />
            <MessagesHeader
                title={t('msg.title')}
                subtitle={t('msg.subtitle')}
                actions={<MessagesActions canStart={canStart} canModerate={canModerate} openReports={openReports} />}
            />

            <div className="msg-workspace">
                <aside className="msg-panel min-h-[24rem] lg:min-h-0">
                    <ConversationList conversations={conversations} canStart={canStart} />
                </aside>
                <section className="msg-panel hidden items-center justify-center px-6 py-12 text-center lg:flex">
                    <div>
                        <EmptyChatIllustration />
                        <p className="mt-5 text-lg font-bold text-navy">{conversations.length === 0 && canStart ? t('msg.emptyStartTitle') : t('msg.pickConversation')}</p>
                        <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">
                            {conversations.length === 0 && canStart ? t('msg.emptyHelpStart') : t('msg.pickConversationHelp')}
                        </p>
                        {canStart ? (
                            <Link href={route('messages.create')} className="btn-teal mx-auto mt-5 gap-2">
                                <ComposeIcon />
                                {t('msg.composeCta')}
                            </Link>
                        ) : null}
                    </div>
                </section>
            </div>
        </AppShell>
    );
}
