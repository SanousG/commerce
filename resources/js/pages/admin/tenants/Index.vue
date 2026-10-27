<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface TenantOption {
    id: string;
    name: string;
    slug: string;
    role: 'OWNER' | 'ADMIN' | 'MEMBER';
}

defineProps<{ tenants: TenantOption[] }>();

const { t } = useI18n();
const selectingTenant = ref<string | null>(null);

const selectTenant = (tenant: TenantOption) => {
    if (selectingTenant.value !== null) return;
    selectingTenant.value = tenant.id;
    router.post(`/admin/tenants/${tenant.id}/select`, {}, {
        onFinish: () => { selectingTenant.value = null; },
    });
};
</script>

<template>
    <Head :title="t('tenancy.companies.title')" />

    <AdminLayout>
        <section class="mx-auto grid w-full max-w-6xl gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('tenancy.companies.title') }]" />
            <PageHeader :title="t('tenancy.companies.title')" :eyebrow="t('tenancy.companies.eyebrow')" :description="t('tenancy.companies.description')">
                <template #actions>
                    <Link href="/admin/tenants/create" class="inline-flex min-h-9 w-full items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white sm:w-auto">{{ t('tenancy.companies.create') }}</Link>
                </template>
            </PageHeader>

            <div v-if="tenants.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="tenant in tenants" :key="tenant.id" class="admin-card flex min-w-0 flex-col p-4 sm:p-5">
                    <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="admin-label">{{ t('tenancy.companies.company') }}</p>
                            <h2 class="mt-1.5 break-words text-lg font-semibold text-admin-primary">{{ tenant.name }}</h2>
                        </div>
                        <span class="shrink-0 rounded border border-admin-border bg-admin-canvas px-1.5 py-0.5 font-mono text-[10px] font-medium text-admin-text-secondary">{{ t(`tenancy.roles.${tenant.role}`) }}</span>
                    </div>
                    <dl class="mt-4 grid gap-1 border-t border-admin-border pt-3">
                        <dt class="admin-label">{{ t('tenancy.companies.identifier') }}</dt>
                        <dd class="break-all font-mono text-[11px] text-admin-neutral">{{ tenant.slug }}</dd>
                    </dl>
                    <button type="button" :disabled="selectingTenant !== null" class="mt-5 min-h-9 w-full rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text transition hover:bg-admin-primary hover:text-white disabled:cursor-not-allowed disabled:opacity-60" @click="selectTenant(tenant)">
                        {{ selectingTenant === tenant.id ? t('tenancy.companies.selecting') : t('tenancy.companies.select') }}
                    </button>
                </article>
            </div>

            <EmptyState v-else :title="t('tenancy.companies.empty')" :description="t('tenancy.companies.emptyHelp')">
                <template #actions><Link href="/admin/tenants/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('tenancy.companies.create') }}</Link></template>
            </EmptyState>

            <div class="rounded-md border border-admin-info-border bg-admin-info-bg px-4 py-3 text-xs leading-5 text-admin-info">
                <p class="font-semibold">{{ t('tenancy.companies.contextTitle') }}</p>
                <p class="mt-0.5">{{ t('tenancy.companies.contextHelp') }}</p>
            </div>
        </section>
    </AdminLayout>
</template>
