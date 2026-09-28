<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Role { id: string; key: 'OWNER' | 'ADMIN' | 'MEMBER'; name: string; permissions: string[] }
interface Member { id: string; user_id: number; name: string; email: string; role_id: string; role: Role['key']; status: string; joined_at: string; permissions: string[] }
interface Invitation { id: string; email: string; role: Role['key']; created_at: string; expires_at: string; expired: boolean }

const props = defineProps<{ tenant: { name: string }; currentUserId: number; canManage: boolean; members: Member[]; invitations: Invitation[]; roles: Role[] }>();
const page = usePage<PageProps>();
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const invite = useForm({ email: '', role_id: props.roles.find((role) => role.key === 'MEMBER')?.id ?? '' });
const date = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
const changeRole = (member: Member, roleId: string) => router.patch(`/admin/settings/team/members/${member.id}/role`, { role_id: roleId }, { preserveScroll: true });
const deactivate = (member: Member) => confirm(`Deactivate ${member.name}? They will lose company access immediately.`) && router.post(`/admin/settings/team/members/${member.id}/deactivate`, {}, { preserveScroll: true });
const reactivate = (member: Member) => router.post(`/admin/settings/team/members/${member.id}/reactivate`, {}, { preserveScroll: true });
const revoke = (invitation: Invitation) => confirm(`Revoke the invitation for ${invitation.email}?`) && router.post(`/admin/settings/team/invitations/${invitation.id}/revoke`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Team & Access" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <Link href="/admin/settings" class="text-sm font-semibold text-emerald-800 hover:underline">Settings</Link>
                    <h1 class="mt-5 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Team &amp; access.</h1>
                    <p class="mt-3 text-[#516057]">{{ tenant.name }}</p>
                </div>
                <Link href="/admin/access" class="border border-[#17211b] px-4 py-3 text-sm font-semibold hover:bg-[#17211b] hover:text-white">Role permission matrix</Link>
            </div>

            <p v-if="page.props.status" class="mt-6 border border-emerald-800/25 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ page.props.status }}</p>
            <p v-if="errors.member || errors.invitation || errors.tenant" class="mt-6 border border-red-800/25 bg-red-50 p-4 text-sm text-red-800">{{ errors.member || errors.invitation || errors.tenant }}</p>

            <form v-if="canManage" class="mt-10 grid gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-6 md:grid-cols-[1fr_14rem_auto] md:items-start" @submit.prevent="invite.post('/admin/settings/team/invitations', { preserveScroll: true, onSuccess: () => invite.reset('email') })">
                <div><label for="invite-email" class="mb-2 block text-sm font-semibold">Email</label><input id="invite-email" v-model="invite.email" type="email" required class="w-full border border-[#17211b]/25 bg-white px-4 py-3" /><FieldError :message="invite.errors.email" /></div>
                <div><label for="invite-role" class="mb-2 block text-sm font-semibold">Role</label><select id="invite-role" v-model="invite.role_id" class="w-full border border-[#17211b]/25 bg-white px-4 py-3"><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select><FieldError :message="invite.errors.role_id" /></div>
                <button type="submit" :disabled="invite.processing" class="mt-7 bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">Send invitation</button>
            </form>

            <div class="mt-12 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-[#17211b]/15 text-xs tracking-wide text-[#637067] uppercase"><tr><th class="p-4">Member</th><th class="p-4">Role</th><th class="p-4">Status</th><th class="p-4">Joined</th><th class="p-4">Effective permissions</th><th class="p-4">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="member in members" :key="member.id" class="border-b border-[#17211b]/10 align-top last:border-0">
                            <td class="p-4"><p class="font-semibold">{{ member.name }}</p><p class="mt-1 text-[#637067]">{{ member.email }}</p></td>
                            <td class="p-4"><select :value="member.role_id" :disabled="!canManage || member.user_id === currentUserId" class="border border-[#17211b]/20 bg-white px-3 py-2 disabled:bg-transparent" @change="changeRole(member, ($event.target as HTMLSelectElement).value)"><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.key }}</option></select></td>
                            <td class="p-4 font-semibold">{{ member.status }}</td>
                            <td class="p-4">{{ date(member.joined_at) }}</td>
                            <td class="p-4"><details><summary class="cursor-pointer font-semibold">{{ member.permissions.length }} permissions</summary><div class="mt-3 flex max-w-sm flex-wrap gap-1"><code v-for="permission in member.permissions" :key="permission" class="bg-black/5 px-2 py-1 text-xs">{{ permission }}</code></div></details></td>
                            <td class="p-4"><template v-if="canManage && member.user_id !== currentUserId"><button v-if="member.status === 'ACTIVE'" type="button" class="font-semibold text-red-800 hover:underline" @click="deactivate(member)">Deactivate</button><button v-else type="button" class="font-semibold text-emerald-800 hover:underline" @click="reactivate(member)">Reactivate</button></template><span v-else class="text-[#637067]">No actions</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <section class="mt-14">
                <h2 class="text-2xl font-semibold">Pending invitations</h2>
                <div v-if="invitations.length" class="mt-5 grid gap-3">
                    <article v-for="item in invitations" :key="item.id" class="flex flex-wrap items-center gap-4 border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                        <div class="min-w-0 flex-1"><p class="break-all font-semibold">{{ item.email }}</p><p class="mt-1 text-sm text-[#637067]">{{ item.role }} · invited {{ date(item.created_at) }} · expires {{ date(item.expires_at) }}</p></div>
                        <span v-if="item.expired" class="text-xs font-semibold text-amber-700">Expired</span>
                        <template v-if="canManage"><Link :href="`/admin/settings/team/invitations/${item.id}/resend`" method="post" as="button" class="text-sm font-semibold text-emerald-800 hover:underline">Resend</Link><button type="button" class="text-sm font-semibold text-red-800 hover:underline" @click="revoke(item)">Revoke</button></template>
                    </article>
                </div>
                <p v-else class="mt-5 border border-dashed border-[#17211b]/25 p-6 text-[#516057]">There are no pending invitations.</p>
            </section>
        </section>
    </AdminLayout>
</template>
