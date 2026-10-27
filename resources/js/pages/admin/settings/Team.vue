<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '../../../components/FieldError.vue';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Role { id: string; key: 'OWNER' | 'ADMIN' | 'MEMBER'; name: string; permissions: string[] }
interface Member { id: string; user_id: number; name: string; email: string; role_id: string; role: Role['key']; status: string; joined_at: string; permissions: string[] }
interface Invitation { id: string; email: string; role: Role['key']; created_at: string; expires_at: string; expired: boolean }

const props = defineProps<{ tenant: { name: string }; currentUserId: number; canManage: boolean; members: Member[]; invitations: Invitation[]; roles: Role[] }>();
const page = usePage<PageProps>();
const { t, locale } = useI18n();
const activeAction = ref<string | null>(null);
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const invite = useForm({ email: '', role_id: props.roles.find((role) => role.key === 'MEMBER')?.id ?? '' });
const activeOwnerCount = computed(() => props.members.filter((member) => member.role === 'OWNER' && member.status === 'ACTIVE').length);
const date = (value: string) => new Intl.DateTimeFormat(locale.value === 'es-PE' ? 'es-PE' : 'en', { dateStyle: 'medium' }).format(new Date(value));
const isSelf = (member: Member) => member.user_id === props.currentUserId;
const isLastOwner = (member: Member) => member.role === 'OWNER' && member.status === 'ACTIVE' && activeOwnerCount.value === 1;
const isBusy = (key?: string) => activeAction.value !== null && (!key || activeAction.value === key);
const notice = computed(() => page.props.status === 'Invitation sent.' ? t('settings.team.notices.invited')
    : page.props.status === 'Invitation resent with a new secure link.' ? t('settings.team.notices.resent')
        : page.props.status === 'Invitation revoked.' ? t('settings.team.notices.revoked')
            : page.props.status === 'Member role updated.' ? t('settings.team.notices.roleUpdated')
                : page.props.status === 'Member deactivated.' ? t('settings.team.notices.deactivated')
                    : page.props.status === 'Member reactivated.' ? t('settings.team.notices.reactivated') : undefined);
const operationError = computed(() => {
    const error = errors.value.member ?? errors.value.invitation ?? errors.value.tenant;
    if (!error) return undefined;
    if (error === 'You cannot change your own role.') return t('settings.team.errors.selfRole');
    if (error === 'You cannot deactivate your own membership.') return t('settings.team.errors.selfDeactivate');
    if (error.includes('active owner')) return t('settings.team.errors.lastOwner');
    if (errors.value.tenant) return t('settings.team.errors.inactiveCompany');
    return t('settings.team.errors.operation');
});
const finishAction = () => { activeAction.value = null; };
const changeRole = (member: Member, event: Event) => {
    const select = event.target as HTMLSelectElement;
    const roleId = select.value;
    if (roleId === member.role_id) return;
    if (!window.confirm(t('settings.team.confirmRole', { name: member.name }))) {
        select.value = member.role_id;
        return;
    }
    activeAction.value = `role:${member.id}`;
    router.patch(`/admin/settings/team/members/${member.id}/role`, { role_id: roleId }, { preserveScroll: true, onFinish: finishAction });
};
const memberAction = (member: Member, action: 'deactivate' | 'reactivate') => {
    if (activeAction.value !== null || !window.confirm(t(`settings.team.confirm.${action}`, { name: member.name }))) return;
    activeAction.value = `${action}:${member.id}`;
    router.post(`/admin/settings/team/members/${member.id}/${action}`, {}, { preserveScroll: true, onFinish: finishAction });
};
const invitationAction = (invitation: Invitation, action: 'resend' | 'revoke') => {
    if (activeAction.value !== null || !window.confirm(t(`settings.team.confirm.${action}`, { email: invitation.email }))) return;
    activeAction.value = `${action}:${invitation.id}`;
    router.post(`/admin/settings/team/invitations/${invitation.id}/${action}`, {}, { preserveScroll: true, onFinish: finishAction });
};
const submitInvite = () => {
    if (invite.processing || activeAction.value !== null) return;
    invite.post('/admin/settings/team/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email') });
};
</script>

<template>
    <Head :title="t('settings.team.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-7xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('settings.title'), href: '/admin/settings' }, { label: t('settings.team.title') }]" />
            <PageHeader :title="t('settings.team.title')" :eyebrow="t('settings.team.eyebrow', { company: tenant.name })" :description="t('settings.team.description')">
                <template #actions><Link href="/admin/access" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold sm:w-auto">{{ t('settings.team.openMatrix') }}</Link></template>
            </PageHeader>

            <p v-if="notice" class="rounded-md border border-admin-success-border bg-admin-success-bg px-4 py-3 text-sm font-semibold text-admin-success" role="status">{{ notice }}</p>
            <p v-if="operationError" class="rounded-md border border-admin-danger-border bg-admin-danger-bg px-4 py-3 text-sm text-admin-danger" role="alert">{{ operationError }}</p>
            <div v-if="!canManage" class="rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3" role="status"><p class="text-sm font-semibold text-amber-800">{{ t('settings.team.readOnlyTitle') }}</p><p class="mt-0.5 text-xs leading-5 text-amber-800">{{ t('settings.team.readOnlyHelp') }}</p></div>

            <form v-if="canManage" class="admin-card overflow-hidden" @submit.prevent="submitInvite">
                <header class="border-b border-admin-border bg-admin-canvas px-4 py-3 sm:px-5"><p class="admin-label text-admin-secondary">{{ t('settings.team.inviteSection') }}</p><h2 class="mt-1 text-base font-semibold">{{ t('settings.team.inviteTitle') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('settings.team.inviteHelp') }}</p></header>
                <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-start">
                    <div><label for="invite-email" class="mb-1.5 block text-sm font-semibold">{{ t('settings.team.email') }}</label><input id="invite-email" v-model="invite.email" type="email" required maxlength="255" autocomplete="email" class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(invite.errors.email)" /><FieldError :message="invite.errors.email ? t('settings.team.errors.email') : undefined" /></div>
                    <div><label for="invite-role" class="mb-1.5 block text-sm font-semibold">{{ t('settings.team.role') }}</label><select id="invite-role" v-model="invite.role_id" required class="admin-control w-full px-3 text-sm" :aria-invalid="Boolean(invite.errors.role_id)"><option v-for="role in roles" :key="role.id" :value="role.id">{{ t(`tenancy.roles.${role.key}`) }}</option></select><FieldError :message="invite.errors.role_id ? t('settings.team.errors.role') : undefined" /></div>
                    <button type="submit" :disabled="invite.processing || activeAction !== null" class="min-h-10 rounded-md bg-admin-primary px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 md:mt-6">{{ invite.processing ? t('settings.team.sending') : t('settings.team.send') }}</button>
                </div>
            </form>

            <section aria-labelledby="members-title">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2"><div><p class="admin-label text-admin-secondary">{{ t('settings.team.membersSection') }}</p><h2 id="members-title" class="mt-1 text-xl font-semibold">{{ t('settings.team.membersTitle') }}</h2></div><p class="font-mono text-xs text-admin-neutral">{{ t('settings.team.memberCount', { count: members.length }) }}</p></div>

                <div class="grid gap-3 lg:hidden">
                    <article v-for="member in members" :key="member.id" class="admin-card min-w-0 p-4">
                        <div class="flex min-w-0 items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words text-sm font-semibold">{{ member.name }}</h3><p class="mt-0.5 break-all text-xs text-admin-neutral">{{ member.email }}</p></div><StatusBadge :status="member.status" /></div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-admin-border pt-3"><div><dt class="admin-label">{{ t('settings.team.role') }}</dt><dd class="mt-1 text-xs font-semibold">{{ t(`tenancy.roles.${member.role}`) }}</dd></div><div><dt class="admin-label">{{ t('settings.team.joined') }}</dt><dd class="mt-1 text-xs">{{ date(member.joined_at) }}</dd></div></dl>
                        <details class="mt-3 rounded-md border border-admin-border bg-admin-canvas px-3 py-2"><summary class="cursor-pointer text-xs font-semibold">{{ t('settings.team.permissionCount', { count: member.permissions.length }) }}</summary><div class="mt-2 flex flex-wrap gap-1"><code v-for="permission in member.permissions" :key="permission" class="max-w-full break-all rounded bg-white px-1.5 py-1 text-[10px] text-admin-neutral">{{ permission }}</code></div></details>
                        <div v-if="canManage && !isSelf(member)" class="mt-4 grid gap-2 border-t border-admin-border pt-3">
                            <label class="grid gap-1 text-xs font-semibold">{{ t('settings.team.changeRole') }}<select :value="member.role_id" :disabled="isBusy() || isLastOwner(member)" class="admin-control w-full px-3 text-sm font-normal disabled:bg-admin-canvas" @change="changeRole(member, $event)"><option v-for="role in roles" :key="role.id" :value="role.id">{{ t(`tenancy.roles.${role.key}`) }}</option></select></label>
                            <button v-if="member.status === 'ACTIVE'" type="button" :disabled="isBusy() || isLastOwner(member)" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-3 text-xs font-semibold text-admin-danger disabled:opacity-40" @click="memberAction(member, 'deactivate')">{{ isBusy(`deactivate:${member.id}`) ? t('settings.team.processing') : t('settings.team.deactivate') }}</button>
                            <button v-else type="button" :disabled="isBusy()" class="min-h-9 rounded-md border border-admin-success-border bg-white px-3 text-xs font-semibold text-admin-success disabled:opacity-40" @click="memberAction(member, 'reactivate')">{{ isBusy(`reactivate:${member.id}`) ? t('settings.team.processing') : t('settings.team.reactivate') }}</button>
                            <p v-if="isLastOwner(member)" class="text-[11px] leading-4 text-amber-700">{{ t('settings.team.lastOwnerHelp') }}</p>
                        </div>
                        <p v-else class="mt-4 border-t border-admin-border pt-3 text-xs text-admin-neutral">{{ isSelf(member) ? t('settings.team.selfHelp') : t('settings.team.noActions') }}</p>
                    </article>
                </div>

                <div class="admin-card hidden overflow-hidden lg:block">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-admin-border bg-admin-canvas"><tr><th class="px-4 py-3 admin-label">{{ t('settings.team.member') }}</th><th class="px-3 py-3 admin-label">{{ t('settings.team.role') }}</th><th class="px-3 py-3 admin-label">{{ t('common.status') }}</th><th class="px-3 py-3 admin-label">{{ t('settings.team.joined') }}</th><th class="px-3 py-3 admin-label">{{ t('settings.team.permissions') }}</th><th class="px-4 py-3 text-right admin-label">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border"><tr v-for="member in members" :key="member.id" class="align-top"><td class="max-w-64 px-4 py-3"><p class="break-words font-semibold">{{ member.name }}</p><p class="mt-0.5 break-all text-admin-neutral">{{ member.email }}</p><p v-if="isSelf(member)" class="mt-1 text-[10px] font-semibold text-admin-secondary">{{ t('settings.team.you') }}</p></td><td class="px-3 py-3"><select :value="member.role_id" :disabled="!canManage || isSelf(member) || isLastOwner(member) || isBusy()" class="admin-control max-w-40 px-2 text-xs disabled:bg-admin-canvas" @change="changeRole(member, $event)"><option v-for="role in roles" :key="role.id" :value="role.id">{{ t(`tenancy.roles.${role.key}`) }}</option></select><p v-if="isLastOwner(member)" class="mt-1 max-w-36 text-[10px] leading-4 text-amber-700">{{ t('settings.team.lastOwnerShort') }}</p></td><td class="px-3 py-3"><StatusBadge :status="member.status" /></td><td class="whitespace-nowrap px-3 py-3">{{ date(member.joined_at) }}</td><td class="px-3 py-3"><details><summary class="cursor-pointer font-semibold">{{ t('settings.team.permissionCount', { count: member.permissions.length }) }}</summary><div class="mt-2 flex max-w-sm flex-wrap gap-1"><code v-for="permission in member.permissions" :key="permission" class="break-all rounded bg-admin-canvas px-1.5 py-1 text-[10px]">{{ permission }}</code></div></details></td><td class="px-4 py-3 text-right"><template v-if="canManage && !isSelf(member)"><button v-if="member.status === 'ACTIVE'" type="button" :disabled="isBusy() || isLastOwner(member)" class="font-semibold text-admin-danger disabled:opacity-40" @click="memberAction(member, 'deactivate')">{{ isBusy(`deactivate:${member.id}`) ? t('settings.team.processing') : t('settings.team.deactivate') }}</button><button v-else type="button" :disabled="isBusy()" class="font-semibold text-admin-success disabled:opacity-40" @click="memberAction(member, 'reactivate')">{{ isBusy(`reactivate:${member.id}`) ? t('settings.team.processing') : t('settings.team.reactivate') }}</button></template><span v-else class="text-admin-neutral">{{ t('settings.team.noActions') }}</span></td></tr></tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="invitations-title">
                <div class="mb-3"><p class="admin-label text-admin-secondary">{{ t('settings.team.invitationsSection') }}</p><h2 id="invitations-title" class="mt-1 text-xl font-semibold">{{ t('settings.team.invitationsTitle') }}</h2><p class="mt-1 text-sm text-admin-neutral">{{ t('settings.team.invitationsHelp') }}</p></div>
                <div v-if="invitations.length" class="grid gap-3">
                    <article v-for="item in invitations" :key="item.id" class="admin-card flex min-w-0 flex-col gap-3 p-4 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1"><p class="break-all text-sm font-semibold">{{ item.email }}</p><p class="mt-1 text-xs text-admin-neutral">{{ t(`tenancy.roles.${item.role}`) }} · {{ t('settings.team.invitedOn', { date: date(item.created_at) }) }} · {{ t('settings.team.expiresOn', { date: date(item.expires_at) }) }}</p></div>
                        <span v-if="item.expired" class="w-fit rounded border border-admin-warning-border bg-admin-warning-bg px-1.5 py-0.5 font-mono text-[10px] text-amber-700">{{ t('status.EXPIRED') }}</span>
                        <div v-if="canManage" class="grid grid-cols-2 gap-2 sm:flex"><button type="button" :disabled="isBusy()" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-3 text-xs font-semibold disabled:opacity-40" @click="invitationAction(item, 'resend')">{{ isBusy(`resend:${item.id}`) ? t('settings.team.processing') : t('settings.team.resend') }}</button><button type="button" :disabled="isBusy()" class="min-h-9 rounded-md border border-admin-danger-border bg-white px-3 text-xs font-semibold text-admin-danger disabled:opacity-40" @click="invitationAction(item, 'revoke')">{{ isBusy(`revoke:${item.id}`) ? t('settings.team.processing') : t('settings.team.revoke') }}</button></div>
                    </article>
                </div>
                <EmptyState v-else :title="t('settings.team.noInvitations')" :description="t('settings.team.noInvitationsHelp')" />
            </section>
        </section>
    </AdminLayout>
</template>
