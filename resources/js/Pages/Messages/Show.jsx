import ConversationList from '@/Components/Comm/ConversationList';
import { MessagesActions, MessagesHeader } from '@/Components/Comm/MessagesChrome';
import MessageThread from '@/Components/Comm/MessageThread';
import { useLocale } from '@/i18n/LocaleProvider';
import { readInbox } from '@/lib/messagesInbox';
import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';

export default function Show({ conversation, messages = [], canReply, canLock, moderatorView, canModerate, canStart = false, limits, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const cached = readInbox();
    const inbox =
        cached.length > 0
            ? cached
            : [
                  {
                      id: conversation.id,
                      title: conversation.title,
                      type: conversation.type,
                      class: conversation.class,
                      student: conversation.student,
                      with: conversation.with,
                      snippet: '',
                      unread: false,
                      at: null,
                  },
              ];

    return (
        <AppShell title={conversation.title || t('msg.untitled')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={conversation.title || t('msg.untitled')} />
            <MessagesHeader
                className="hidden lg:block"
                title={t('msg.title')}
                subtitle={t('msg.subtitle')}
                actions={<MessagesActions canStart={canStart} canModerate={Boolean(canModerate)} />}
            />

            <div className="msg-workspace msg-workspace-thread">
                <aside className="msg-panel hidden lg:flex">
                    <ConversationList conversations={inbox} activeId={conversation.id} canStart={canStart} />
                </aside>
                <section className="msg-panel min-h-[70vh] max-lg:min-h-0 lg:min-h-0">
                    <MessageThread
                        conversation={conversation}
                        messages={messages}
                        canReply={canReply}
                        canLock={canLock}
                        moderatorView={moderatorView}
                        canModerate={canModerate}
                        limits={limits}
                    />
                </section>
            </div>
        </AppShell>
    );
}
