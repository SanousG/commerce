<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface RoleSummary {
    key: 'OWNER' | 'ADMIN' | 'MEMBER';
    name: string;
    permissions: string[];
}

const props = defineProps<{
    tenant: { name: string };
    currentRole: RoleSummary['key'];
    permissions: string[];
    roles: RoleSummary[];
}>();

const { t, te } = useI18n();
const allPermissions = computed(() => [...new Set(props.roles.flatMap((role) => role.permissions))].sort());
const roleHas = (role: RoleSummary, permission: string) => role.permissions.includes(permission);
const permissionLabel = (permission: string) => {
    const parts = permission.split('.');
    const action = parts.pop() ?? '';
    const resource = parts.join('.');
    const resourceKey = `access.resources.${resource}`;
    const actionKey = `access.actions.${action}`;

    return te(resourceKey) && te(actionKey)
        ? t('access.permissionLabel', { resource: t(resourceKey), action: t(actionKey) })
        : permission;
};
</script>

<template>
    <Head :title="t('access.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-7xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('access.title') }]" />
            <PageHeader :title="t('access.title')" :eyebrow="t('access.eyebrow', { company: tenant.name })" :description="t('access.description')">
                <template #actions>
                    <Link href="/admin/settings/team" class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text sm:w-auto">{{ t('access.openTeam') }}</Link>
                </template>
            </PageHeader>

            <section class="grid gap-4 lg:grid-cols-[17rem_minmax(0,1fr)]" aria-labelledby="effective-access-title">
                <article class="rounded-lg border border-admin-success-border bg-admin-success-bg p-4 sm:p-5">
                    <p class="admin-label text-admin-success">{{ t('access.currentRole') }}</p>
                    <h2 id="effective-access-title" class="mt-1 text-xl font-semibold text-admin-primary">{{ t(`tenancy.roles.${currentRole}`) }}</h2>
                    <p class="mt-1 text-xs leading-5 text-admin-text-secondary">{{ t('access.effectiveCount', { count: permissions.length }) }}</p>
                </article>
                <article class="admin-card p-4 sm:p-5">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div><p class="admin-label text-admin-secondary">{{ t('access.effectiveSection') }}</p><h2 class="mt-1 text-base font-semibold">{{ t('access.effectiveTitle') }}</h2></div>
                        <span class="rounded border border-admin-border bg-admin-canvas px-2 py-1 font-mono text-[10px] text-admin-neutral">{{ t('common.readOnly') }}</span>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="permission in permissions" :key="permission" class="min-w-0 rounded-md border border-admin-border bg-admin-canvas px-3 py-2">
                            <p class="break-words text-xs font-semibold text-admin-text">{{ permissionLabel(permission) }}</p>
                            <code class="mt-0.5 block break-all text-[10px] text-admin-neutral">{{ permission }}</code>
                        </div>
                    </div>
                </article>
            </section>

            <section aria-labelledby="role-matrix-title">
                <div class="mb-3">
                    <p class="admin-label text-admin-secondary">{{ t('access.matrixSection') }}</p>
                    <h2 id="role-matrix-title" class="mt-1 text-xl font-semibold">{{ t('access.matrixTitle') }}</h2>
                    <p class="mt-1 text-sm text-admin-neutral">{{ t('access.matrixHelp') }}</p>
                </div>

                <div class="grid gap-3 lg:hidden">
                    <article v-for="role in roles" :key="role.key" class="admin-card overflow-hidden">
                        <header class="flex items-start justify-between gap-3 border-b border-admin-border bg-admin-canvas px-4 py-3">
                            <div><p class="admin-label">{{ role.key }}</p><h3 class="mt-1 text-base font-semibold">{{ t(`tenancy.roles.${role.key}`) }}</h3></div>
                            <span class="font-mono text-xs text-admin-neutral">{{ t('access.permissionCount', { count: role.permissions.length }) }}</span>
                        </header>
                        <ul class="divide-y divide-admin-border px-4">
                            <li v-for="permission in role.permissions" :key="permission" class="py-2.5">
                                <p class="text-xs font-medium">{{ permissionLabel(permission) }}</p>
                                <code class="break-all text-[10px] text-admin-neutral">{{ permission }}</code>
                            </li>
                        </ul>
                    </article>
                </div>

                <div class="admin-card hidden overflow-hidden lg:block">
                    <table class="w-full table-fixed text-left text-xs">
                        <thead class="border-b border-admin-border bg-admin-canvas">
                            <tr><th class="w-[46%] px-4 py-3 admin-label">{{ t('access.permission') }}</th><th v-for="role in roles" :key="role.key" class="px-3 py-3 text-center admin-label">{{ t(`tenancy.roles.${role.key}`) }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-admin-border">
                            <tr v-for="permission in allPermissions" :key="permission">
                                <th class="px-4 py-2.5 font-normal"><span class="block font-medium text-admin-text">{{ permissionLabel(permission) }}</span><code class="text-[10px] text-admin-neutral">{{ permission }}</code></th>
                                <td v-for="role in roles" :key="role.key" class="px-3 py-2.5 text-center"><span :class="roleHas(role, permission) ? 'text-admin-success' : 'text-slate-300'" :aria-label="roleHas(role, permission) ? t('access.granted') : t('access.notGranted')">{{ roleHas(role, permission) ? '✓' : '—' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info">{{ t('access.readOnlyHelp') }}</p>
        </section>
    </AdminLayout>
</template>
