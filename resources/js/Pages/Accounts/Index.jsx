import DataTable from '@/Components/SchoolOS/DataTable';
import { Card, ErrorSummary, Field, Input, Pager, Select } from '@/Components/SchoolOS/Form';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import StatusBadge from '@/Components/SchoolOS/StatusBadge';
import { useLocale } from '@/i18n/LocaleProvider';
import { enumLabel } from '@/lib/academic';
import { fmtDay, fmtWhen, roleLabel } from '@/lib/comm';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const TEACHING = ['teacher', 'class_teacher', 'head_of_department'];

function Tabs({ tab, setTab }) {
    const { t } = useLocale();
    const items = ['people', 'invite', 'invitations'];

    return (
        <div className="mb-5 flex flex-wrap gap-2" role="tablist">
            {items.map((k) => (
                <button
                    key={k}
                    type="button"
                    role="tab"
                    aria-selected={tab === k}
                    onClick={() => setTab(k)}
                    className={`rounded-lg px-4 py-2 text-sm font-semibold whitespace-nowrap ${tab === k ? 'bg-navy text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50'}`}
                >
                    {t(`acct.tab.${k}`)}
                </button>
            ))}
        </div>
    );
}

function StaffInvite({ roles, allocations }) {
    const { t } = useLocale();
    const form = useForm({ name: '', email: '', role: roles[0] ?? 'teacher', class_subject_ids: [] });
    const teaching = TEACHING.includes(form.data.role);

    const toggle = (id) =>
        form.setData('class_subject_ids', form.data.class_subject_ids.includes(id) ? form.data.class_subject_ids.filter((x) => x !== id) : [...form.data.class_subject_ids, id]);

    return (
        <Card title={t('acct.invite.staff.title')}>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route('accounts.invite.staff'), { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <ErrorSummary errors={form.errors} />
                <Field label={t('acct.field.name')}>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={255} />
                </Field>
                <Field label={t('acct.field.email')}>
                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required maxLength={255} />
                </Field>
                <Field label={t('acct.field.role')} hint={t('acct.invite.staff.roleHint')}>
                    <Select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                        {roles.map((r) => (
                            <option key={r} value={r}>
                                {roleLabel(t, r)}
                            </option>
                        ))}
                    </Select>
                </Field>
                {teaching && allocations.length > 0 ? (
                    <Field label={t('acct.field.allocations')} hint={t('acct.field.allocationsHint')}>
                        <div className="max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2">
                            {allocations.map((a) => (
                                <label key={a.id} className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" className="rounded border-gray-300" checked={form.data.class_subject_ids.includes(a.id)} onChange={() => toggle(a.id)} />
                                    {a.label}
                                </label>
                            ))}
                        </div>
                    </Field>
                ) : null}
                <button type="submit" className="btn-teal" disabled={form.processing}>
                    {t('acct.invite.send')}
                </button>
            </form>
        </Card>
    );
}

function FamilyInvite({ kind, candidates, filters, parentAccess }) {
    const { t } = useLocale();
    const [q, setQ] = useState(filters.kind === kind ? filters.cq ?? '' : '');
    const form = useForm({ student_id: '', guardian_id: '', email: '' });
    const field = kind === 'student' ? 'student_id' : 'guardian_id';
    const blocked = kind === 'guardian' && !parentAccess;

    const search = (e) => {
        e.preventDefault();
        router.get(route('accounts.index'), { kind, cq: q }, { preserveState: true, preserveScroll: true, replace: true, only: ['candidates', 'filters'] });
    };

    const pick = (c) => {
        form.setData({ student_id: kind === 'student' ? c.id : '', guardian_id: kind === 'guardian' ? c.id : '', email: c.email ?? '' });
    };

    const shown = filters.kind === kind ? candidates : [];

    return (
        <Card title={t(`acct.invite.${kind}.title`)}>
            {blocked ? <p className="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{t('acct.invite.guardian.off')}</p> : null}
            <form onSubmit={search} className="mb-3 flex gap-2">
                <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t(`acct.invite.${kind}.search`)} aria-label={t(`acct.invite.${kind}.search`)} />
                <button type="submit" className="btn-outline">
                    {t('acct.search')}
                </button>
            </form>

            {filters.kind === kind ? (
                shown.length === 0 ? (
                    <p className="mb-3 text-sm text-slate-500">{t(`acct.invite.${kind}.none`)}</p>
                ) : (
                    <ul className="mb-4 max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-md border border-slate-200">
                        {shown.map((c) => (
                            <li key={c.id}>
                                <button
                                    type="button"
                                    onClick={() => pick(c)}
                                    className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm hover:bg-slate-50 ${String(form.data[field]) === String(c.id) ? 'bg-teal-50' : ''}`}
                                >
                                    <span className="min-w-0 truncate font-medium text-navy">{c.name}</span>
                                    <span className="shrink-0 text-xs text-slate-500">{c.ref ?? ''}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )
            ) : (
                <p className="mb-3 text-sm text-slate-500">{t(`acct.invite.${kind}.hint`)}</p>
            )}

            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(route(`accounts.invite.${kind}`), { preserveScroll: true, onSuccess: () => form.reset() });
                }}
            >
                <ErrorSummary errors={form.errors} />
                <Field label={t('acct.field.email')} hint={kind === 'guardian' ? t('acct.invite.guardian.emailHint') : t('acct.invite.student.emailHint')}>
                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required={kind === 'student'} maxLength={255} />
                </Field>
                <button type="submit" className="btn-teal" disabled={form.processing || !form.data[field] || blocked}>
                    {t('acct.invite.send')}
                </button>
            </form>
        </Card>
    );
}

export default function Index({ users, invitations = [], filters = {}, roles = [], allocations = [], candidates = [], parentAccess = true, nav = [], user, school, academicYear }) {
    const { t, locale } = useLocale();
    const [tab, setTab] = useState(filters.kind ? 'invite' : 'people');
    const [restoreRole, setRestoreRole] = useState({});
    const [search, setSearch] = useState(filters.q ?? '');

    const act = (name, id, data = {}, confirmKey = null) => {
        if (confirmKey && !window.confirm(t(confirmKey))) {
            return;
        }
        router.post(route(name, id), data, { preserveScroll: true });
    };

    const reason = (name, id, promptKey) => {
        const text = window.prompt(t(promptKey));
        if (text && text.trim()) {
            router.post(route(name, id), { reason: text.trim() }, { preserveScroll: true });
        }
    };

    const filter = (patch) => router.get(route('accounts.index'), { ...filters, kind: undefined, cq: undefined, ...patch }, { preserveState: true, replace: true, only: ['users', 'filters'] });

    const columns = [
        {
            key: 'name',
            label: t('acct.col.person'),
            render: (u) => (
                <div className="min-w-0">
                    <p className="font-medium text-navy">{u.name}</p>
                    <p className="text-xs text-slate-500">{u.email}</p>
                </div>
            ),
        },
        { key: 'roles', label: t('acct.col.roles'), render: (u) => (u.roles.length ? u.roles.map((r) => roleLabel(t, r)).join(', ') : t('acct.noRole')) },
        {
            key: 'status',
            label: t('acct.col.status'),
            render: (u) => (
                <div className="flex flex-wrap items-center gap-1.5">
                    <StatusBadge status={u.status} label={enumLabel(t, 'acct.status', u.status)} />
                    {u.verified ? null : <StatusBadge status="pending" label={t('acct.unverified')} />}
                </div>
            ),
        },
        { key: 'last_login_at', label: t('acct.col.lastLogin'), render: (u) => (u.last_login_at ? fmtWhen(u.last_login_at.replace(' ', 'T'), locale) : t('acct.never')) },
        {
            key: 'actions',
            label: t('acct.col.actions'),
            render: (u) => {
                if (!u.manageable) {
                    return <span className="text-xs text-slate-400">{t('acct.protected')}</span>;
                }
                const small = 'btn-outline !px-3 !py-1.5 text-xs';

                return (
                    <div className="flex items-center gap-2">
                        {u.status === 'active' ? (
                            <>
                                <button type="button" className={small} onClick={() => act('accounts.reset', u.id, {}, 'acct.confirm.reset')}>
                                    {t('acct.action.reset')}
                                </button>
                                <button type="button" className={small} onClick={() => reason('accounts.suspend', u.id, 'acct.prompt.suspend')}>
                                    {t('acct.action.suspend')}
                                </button>
                            </>
                        ) : null}
                        {u.status === 'suspended' ? (
                            <>
                                <button type="button" className={small} onClick={() => act('accounts.reinstate', u.id)}>
                                    {t('acct.action.reinstate')}
                                </button>
                                <button type="button" className={`${small} !text-red-700`} onClick={() => reason('accounts.revoke', u.id, 'acct.prompt.revoke')}>
                                    {t('acct.action.revoke')}
                                </button>
                            </>
                        ) : null}
                        {u.status === 'active' ? (
                            <button type="button" className={`${small} !text-red-700`} onClick={() => reason('accounts.revoke', u.id, 'acct.prompt.revoke')}>
                                {t('acct.action.revoke')}
                            </button>
                        ) : null}
                        {u.status === 'revoked' ? (
                            <>
                                <Select
                                    aria-label={t('acct.field.role')}
                                    className="!w-auto !py-1.5 text-xs"
                                    value={restoreRole[u.id] ?? u.restorable[0] ?? ''}
                                    onChange={(e) => setRestoreRole({ ...restoreRole, [u.id]: e.target.value })}
                                >
                                    {u.restorable.map((r) => (
                                        <option key={r} value={r}>
                                            {roleLabel(t, r)}
                                        </option>
                                    ))}
                                </Select>
                                <button type="button" className={small} onClick={() => act('accounts.restore', u.id, { role: restoreRole[u.id] ?? u.restorable[0] }, 'acct.confirm.restore')}>
                                    {t('acct.action.restore')}
                                </button>
                            </>
                        ) : null}
                    </div>
                );
            },
        },
    ];

    const inviteColumns = [
        { key: 'name', label: t('acct.col.person'), render: (i) => (<div><p className="font-medium text-navy">{i.name}</p><p className="text-xs text-slate-500">{i.email}</p></div>) },
        { key: 'kind', label: t('acct.col.kind'), render: (i) => enumLabel(t, 'acct.kind', i.kind) },
        { key: 'role', label: t('acct.field.role'), render: (i) => roleLabel(t, i.role) },
        { key: 'state', label: t('acct.col.status'), render: (i) => <StatusBadge status={i.state} label={enumLabel(t, 'acct.inviteState', i.state)} /> },
        { key: 'expires_at', label: t('acct.col.expires'), render: (i) => fmtDay(i.expires_at?.replace(' ', 'T'), locale) },
        {
            key: 'actions',
            label: t('acct.col.actions'),
            render: (i) =>
                i.state === 'pending' || i.state === 'expired' ? (
                    <div className="flex items-center gap-2">
                        <button type="button" className="btn-outline !px-3 !py-1.5 text-xs" onClick={() => act('accounts.invitations.resend', i.id)}>
                            {t('acct.action.resend')}
                        </button>
                        <button type="button" className="btn-outline !px-3 !py-1.5 text-xs !text-red-700" onClick={() => router.delete(route('accounts.invitations.destroy', i.id), { preserveScroll: true })}>
                            {t('acct.action.revokeInvite')}
                        </button>
                    </div>
                ) : null,
        },
    ];

    return (
        <AppShell title={t('acct.title')} nav={nav} user={user} school={school} academicYear={academicYear}>
            <Head title={t('acct.title')} />
            <PageHeader title={t('acct.title')} subtitle={t('acct.subtitle')} />
            <Tabs tab={tab} setTab={setTab} />

            {tab === 'people' ? (
                <>
                    <form
                        className="mb-4 flex flex-wrap items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            filter({ q: search || undefined });
                        }}
                    >
                        <Field label={t('acct.search')} className="min-w-[14rem] flex-1">
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('acct.searchPlaceholder')} />
                        </Field>
                        <Field label={t('acct.col.status')}>
                            <Select value={filters.status ?? ''} onChange={(e) => filter({ status: e.target.value || undefined, q: search || undefined })}>
                                <option value="">{t('acct.status.all')}</option>
                                {['active', 'suspended', 'revoked'].map((s) => (
                                    <option key={s} value={s}>
                                        {enumLabel(t, 'acct.status', s)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <button type="submit" className="btn-teal">
                            {t('acct.search')}
                        </button>
                    </form>
                    <DataTable columns={columns} rows={users.data} emptyMessage={t('acct.empty')} />
                    <Pager links={users.links} />
                </>
            ) : null}

            {tab === 'invite' ? (
                <div className="grid gap-6 lg:grid-cols-3">
                    <StaffInvite roles={roles} allocations={allocations} />
                    <FamilyInvite kind="student" candidates={candidates} filters={filters} parentAccess={parentAccess} />
                    <FamilyInvite kind="guardian" candidates={candidates} filters={filters} parentAccess={parentAccess} />
                </div>
            ) : null}

            {tab === 'invitations' ? <DataTable columns={inviteColumns} rows={invitations} emptyMessage={t('acct.invitations.empty')} /> : null}
        </AppShell>
    );
}
