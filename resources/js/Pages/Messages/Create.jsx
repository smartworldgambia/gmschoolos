import RecipientPicker from '@/Components/Comm/RecipientPicker';
import { MessagesHeader, PlaneIcon } from '@/Components/Comm/MessagesChrome';
import { Card, ErrorSummary, Field, Input, Select, Textarea } from '@/Components/SchoolOS/Form';
import { useLocale } from '@/i18n/LocaleProvider';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function Attachments({ form, limits }) {
    const { t } = useLocale();

    return (
        <Field label={t('msg.attachments')} hint={t('msg.attachmentsHint', { n: limits.max_files, mb: Math.round(limits.max_kb / 1024), types: limits.mimes })}>
            <input
                type="file"
                multiple
                accept={limits.mimes
                    .split(',')
                    .map((m) => `.${m}`)
                    .join(',')}
                className="block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium"
                onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []).slice(0, limits.max_files))}
            />
        </Field>
    );
}

export default function Create({ kind, contacts, children = [], classes = [], limits, prefill = {}, nav = [], user, school, academicYear }) {
    const { t } = useLocale();
    const isStaff = kind === 'staff';
    const isStudent = kind === 'student';
    const [mode, setMode] = useState('direct');
    const [who, setWho] = useState(prefill.student_id ? 'family' : 'colleague');

    const direct = useForm({ recipient_id: prefill.recipient_id ?? '', student_id: prefill.student_id ?? '', subject: '', body: '', attachments: [] });
    const group = useForm({ class_id: classes[0]?.id ?? '', subject: '', audience: 'guardians', replies_allowed: true, body: '', attachments: [] });

    const family = contacts.families.find((f) => String(f.student_id) === String(direct.data.student_id));

    const pickerGroups = useMemo(() => {
        if (isStaff && who === 'family') {
            const people = [
                ...(family?.guardians ?? []).map((g) => ({ id: g.id, name: g.name, kind: 'guardian', role: 'parent' })),
                ...(family?.learner ? [{ id: family.learner.id, name: family.learner.name, kind: 'student', role: 'student' }] : []),
            ];

            return [{ id: 'family', label: t('msg.who.family'), people }];
        }

        if (isStaff) {
            return [
                {
                    id: 'colleagues',
                    label: t('msg.section.colleagues'),
                    people: contacts.colleagues || [],
                },
            ];
        }

        const groups = [];
        if ((contacts.staff || []).length > 0) {
            groups.push({ id: 'staff', label: t('msg.section.staff'), people: contacts.staff });
        }
        if ((contacts.classmates || []).length > 0) {
            groups.push({ id: 'classmates', label: t('msg.section.classmates'), people: contacts.classmates });
        }

        return groups;
    }, [isStaff, who, family, contacts, t]);

    const submitDirect = (e) => {
        e.preventDefault();
        direct.post(route('messages.store'), { forceFormData: true, preserveScroll: true });
    };

    const submitGroup = (e) => {
        e.preventDefault();
        group.transform((d) => ({ ...d, replies_allowed: d.replies_allowed ? 1 : 0 })).post(route('messages.group.store'), { forceFormData: true, preserveScroll: true });
    };

    const subtitle = isStudent ? t('msg.newSubtitleStudent') : isStaff ? t('msg.newSubtitle') : t('msg.newSubtitleFamily');

    return (
        <AppShell title={t('msg.new')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('msg.new')} />
            <MessagesHeader
                title={t('msg.new')}
                subtitle={subtitle}
                actions={
                    <Link href={route('messages.index')} className="btn-outline">
                        {t('msg.cancel')}
                    </Link>
                }
            />

            {isStaff && classes.length > 0 ? (
                <div className="mb-5 flex gap-2" role="tablist">
                    {['direct', 'group'].map((m) => (
                        <button
                            key={m}
                            type="button"
                            role="tab"
                            aria-selected={mode === m}
                            onClick={() => setMode(m)}
                            className={`rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap ${mode === m ? 'bg-navy text-white' : 'bg-white text-navy ring-1 ring-slate-200 hover:bg-slate-50'}`}
                        >
                            {t(`msg.mode.${m}`)}
                        </button>
                    ))}
                </div>
            ) : null}

            {mode === 'direct' ? (
                <Card className="max-w-3xl">
                    <form className="space-y-4" onSubmit={submitDirect}>
                        <ErrorSummary errors={direct.errors} />
                        <Field label={t('msg.to')}>
                            {isStaff ? (
                                <div className="mb-3 flex flex-wrap gap-2" role="tablist">
                                    {['colleague', 'family'].map((w) => (
                                        <button
                                            key={w}
                                            type="button"
                                            role="tab"
                                            aria-selected={who === w}
                                            onClick={() => {
                                                setWho(w);
                                                direct.setData({ ...direct.data, recipient_id: '', student_id: '' });
                                            }}
                                            className={`rounded-full px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap ${
                                                who === w ? 'bg-teal text-white' : 'bg-[#F0F4F9] text-navy hover:bg-slate-100'
                                            }`}
                                        >
                                            {t(`msg.who.${w}`)}
                                        </button>
                                    ))}
                                </div>
                            ) : null}
                            {isStaff && who === 'family' ? (
                                <div className="mb-3">
                                    <Select value={direct.data.student_id} onChange={(e) => direct.setData({ ...direct.data, student_id: e.target.value, recipient_id: '' })} aria-label={t('msg.aboutLearner')}>
                                        <option value="">{t('msg.chooseLearner')}</option>
                                        {contacts.families.map((f) => (
                                            <option key={f.student_id} value={f.student_id}>
                                                {f.name}
                                                {f.class ? ` — ${f.class}` : ''}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            ) : null}
                            {isStaff && who === 'family' && !direct.data.student_id ? (
                                <p className="text-sm text-slate-500">{t('msg.pickLearnerFirst')}</p>
                            ) : (
                                <RecipientPicker
                                    groups={pickerGroups}
                                    value={direct.data.recipient_id}
                                    onChange={(id) => direct.setData('recipient_id', id)}
                                    error={direct.errors.recipient_id}
                                />
                            )}
                        </Field>

                        {kind === 'guardian' && children.length > 0 ? (
                            <Field label={t('msg.aboutLearner')} hint={t('msg.aboutHint')}>
                                <Select value={direct.data.student_id} onChange={(e) => direct.setData('student_id', e.target.value)}>
                                    <option value="">{t('msg.noChild')}</option>
                                    {children.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        ) : null}

                        <Field label={t('msg.subject')}>
                            <Input value={direct.data.subject} onChange={(e) => direct.setData('subject', e.target.value)} maxLength={190} placeholder={t('msg.subjectOptional')} />
                        </Field>
                        <Field label={t('msg.message')}>
                            <Textarea rows={7} value={direct.data.body} onChange={(e) => direct.setData('body', e.target.value)} required maxLength={limits.body_max} placeholder={t('msg.typeMessage')} />
                        </Field>
                        <Attachments form={direct} limits={limits} />
                        <div className="flex flex-wrap items-center justify-end gap-2 pt-1">
                            <Link href={route('messages.index')} className="btn-outline">
                                {t('msg.cancel')}
                            </Link>
                            <button type="submit" className="btn-teal gap-2" disabled={direct.processing || !direct.data.recipient_id}>
                                <PlaneIcon />
                                {t('msg.send')}
                            </button>
                        </div>
                    </form>
                </Card>
            ) : (
                <Card className="max-w-3xl">
                    <form className="space-y-4" onSubmit={submitGroup}>
                        <ErrorSummary errors={group.errors} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('msg.class')}>
                                <Select value={group.data.class_id} onChange={(e) => group.setData('class_id', e.target.value)} required>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label={t('msg.audience')}>
                                <Select value={group.data.audience} onChange={(e) => group.setData('audience', e.target.value)}>
                                    {['guardians', 'students', 'both'].map((a) => (
                                        <option key={a} value={a}>
                                            {t(`msg.audience.${a}`)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                        <Field label={t('msg.subject')}>
                            <Input value={group.data.subject} onChange={(e) => group.setData('subject', e.target.value)} required maxLength={190} />
                        </Field>
                        <Field label={t('msg.message')}>
                            <Textarea rows={6} value={group.data.body} onChange={(e) => group.setData('body', e.target.value)} required maxLength={limits.body_max} />
                        </Field>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" className="rounded border-gray-300" checked={group.data.replies_allowed} onChange={(e) => group.setData('replies_allowed', e.target.checked)} />
                            {t('msg.repliesAllowed')}
                        </label>
                        <p className="text-xs text-slate-500">{t('msg.groupPrivacy')}</p>
                        <Attachments form={group} limits={limits} />
                        <div className="flex flex-wrap items-center justify-end gap-2 pt-1">
                            <Link href={route('messages.index')} className="btn-outline">
                                {t('msg.cancel')}
                            </Link>
                            <button type="submit" className="btn-teal gap-2" disabled={group.processing}>
                                <PlaneIcon />
                                {t('msg.send')}
                            </button>
                        </div>
                    </form>
                </Card>
            )}
        </AppShell>
    );
}
