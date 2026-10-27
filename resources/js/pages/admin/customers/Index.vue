<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import StatusNotice from '../../../components/StatusNotice.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

type CustomerStatus = 'ACTIVE' | 'INACTIVE' | 'BLOCKED' | 'ARCHIVED';
interface CustomerRow { id: string; display_name: string; email: string | null; phone: string | null; status: CustomerStatus; addresses_count: number; created_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: CustomerRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }

const props = defineProps<{ customers: Paginator; filters: { search: string; status: string }; statuses: CustomerStatus[] }>();
const page = usePage<PageProps>();
const { t, locale } = useI18n();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('customers.create'));
const canUpdate = computed(() => permissions.value.includes('customers.update'));
const canArchive = computed(() => permissions.value.includes('customers.archive'));
const search = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);
const filtering = ref(false);
const archiveForm = useForm({});
const archivingId = ref<string | null>(null);
const hasFilters = computed(() => props.filters.search !== '' || props.filters.status !== '');
const localizedStatus = computed(() => {
    const keys: Record<string, string> = { 'Customer archived.': 'customers.notices.archived' };
    return page.props.status ? t(keys[page.props.status] ?? 'customers.notices.completed') : undefined;
});

watch(() => props.filters, (filters) => { search.value = filters.search; selectedStatus.value = filters.status; }, { deep: true });
const visitIndex = (nextSearch: string, nextStatus: string) => router.get('/admin/customers', {
    ...(nextSearch.trim() ? { search: nextSearch.trim() } : {}),
    ...(nextStatus ? { status: nextStatus } : {}),
}, { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const applyFilters = () => visitIndex(search.value, selectedStatus.value);
const clearFilters = () => { search.value = ''; selectedStatus.value = ''; visitIndex('', ''); };
const archive = (customer: CustomerRow) => {
    if (archiveForm.processing || !window.confirm(t('customers.archiveConfirm', { name: customer.display_name }))) return;
    archivingId.value = customer.id;
    archiveForm.post(`/admin/customers/${customer.id}/archive`, { onFinish: () => { archivingId.value = null; } });
};
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value)) : '—';
</script>

<template>
    <Head :title="t('customers.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('customers.title') }]" />
            <PageHeader :title="t('customers.title')" :eyebrow="t('common.allCompany')" :description="t('customers.description')">
                <template #actions><Link v-if="canCreate" href="/admin/customers/create" class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>{{ t('customers.create') }}</Link></template>
            </PageHeader>
            <StatusNotice :message="localizedStatus" class="!mb-0 rounded-md !border-admin-success !bg-admin-success-bg !text-admin-success" />

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end sm:p-4" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5" for="customer-search"><span class="admin-label">{{ t('customers.search') }}</span><span class="relative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg><input id="customer-search" v-model="search" type="search" maxlength="120" autocomplete="off" :placeholder="t('customers.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" /></span></label>
                <label class="grid gap-1.5" for="customer-status"><span class="admin-label">{{ t('common.status') }}</span><select id="customer-status" v-model="selectedStatus" class="admin-control w-full px-3 text-sm"><option value="">{{ t('customers.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <div class="flex gap-2"><button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:opacity-60 sm:flex-none">{{ filtering ? t('customers.filtering') : t('customers.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary hover:bg-slate-50 disabled:opacity-60 sm:flex-none" @click="clearFilters">{{ t('common.clear') }}</button></div>
            </form>

            <template v-if="customers.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[22%] p-3">{{ t('customers.customer') }}</th><th class="w-[20%] p-3">{{ t('customers.email') }}</th><th class="w-[15%] p-3">{{ t('customers.phone') }}</th><th class="w-[12%] p-3">{{ t('common.status') }}</th><th class="w-[10%] p-3 text-right">{{ t('customers.addressCount') }}</th><th class="w-[12%] p-3">{{ t('customers.createdAt') }}</th><th class="w-[9%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border"><tr v-for="customer in customers.data" :key="customer.id"><td class="p-3"><Link :href="`/admin/customers/${customer.id}`" class="block truncate text-sm font-semibold text-admin-text hover:text-admin-secondary">{{ customer.display_name }}</Link></td><td class="truncate p-3 text-admin-text-secondary" :title="customer.email ?? undefined">{{ customer.email ?? t('common.none') }}</td><td class="p-3 font-mono text-[11px] text-admin-text-secondary">{{ customer.phone ?? t('common.none') }}</td><td class="p-3"><StatusBadge :status="customer.status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ customer.addresses_count }}</td><td class="p-3 text-admin-neutral">{{ formatDate(customer.created_at) }}</td><td class="p-3"><div class="flex flex-col items-end gap-2"><Link :href="`/admin/customers/${customer.id}`" class="admin-link">{{ t('common.view') }}</Link><Link v-if="canUpdate" :href="`/admin/customers/${customer.id}/edit`" class="admin-link">{{ t('common.edit') }}</Link><button v-if="canArchive && customer.status !== 'ARCHIVED'" type="button" :disabled="archiveForm.processing" class="font-semibold text-admin-danger disabled:opacity-50" @click="archive(customer)">{{ archivingId === customer.id ? t('common.archiving') : t('common.archive') }}</button></div></td></tr></tbody>
                    </table>
                </div>
                <div class="grid gap-2.5 md:hidden"><article v-for="customer in customers.data" :key="`mobile-${customer.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/customers/${customer.id}`" class="block truncate text-sm font-semibold text-admin-text">{{ customer.display_name }}</Link><p class="mt-1 truncate text-xs text-admin-text-secondary">{{ customer.email ?? t('common.none') }}</p></div><StatusBadge :status="customer.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('customers.phone') }}</dt><dd class="mt-1 font-mono text-xs">{{ customer.phone ?? t('common.none') }}</dd></div><div><dt class="admin-label">{{ t('customers.addressCount') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ customer.addresses_count }}</dd></div><div class="col-span-2"><dt class="admin-label">{{ t('customers.createdAt') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(customer.created_at) }}</dd></div></dl><div class="mt-3 flex flex-wrap items-center justify-end gap-4"><Link :href="`/admin/customers/${customer.id}`" class="admin-link text-xs">{{ t('common.view') }}</Link><Link v-if="canUpdate" :href="`/admin/customers/${customer.id}/edit`" class="admin-link text-xs">{{ t('common.edit') }}</Link><button v-if="canArchive && customer.status !== 'ARCHIVED'" type="button" :disabled="archiveForm.processing" class="text-xs font-semibold text-admin-danger disabled:opacity-50" @click="archive(customer)">{{ archivingId === customer.id ? t('common.archiving') : t('common.archive') }}</button></div></article></div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('customers.noMatch') : t('customers.noCustomers')" :description="hasFilters ? t('customers.noMatchHelp') : t('customers.noCustomersHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('customers.clearFilters') }}</button></template><template v-else-if="canCreate" #actions><Link href="/admin/customers/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('customers.create') }}</Link></template></EmptyState>
            <Pagination :links="customers.links" :from="customers.from" :to="customers.to" :total="customers.total" preserve-state />
        </section>
    </AdminLayout>
</template>
