<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface CheckoutRow { id: string; token_hint: string; customer: string; status: string; grand_total: string; currency: string | null; expires_at: string | null; created_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: CheckoutRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
const checkoutStatuses = ['PENDING', 'READY', 'COMPLETED', 'EXPIRED', 'CANCELLED', 'FAILED'] as const;

const props = defineProps<{ checkouts: Paginator; filters: { search: string; status: string } }>();
const page = usePage<PageProps>();
const { t, locale } = useI18n();
const search = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);
const filtering = ref(false);
const canManage = computed(() => page.props.currentTenantMembership?.permissions.includes('checkouts.manage') ?? false);
const hasFilters = computed(() => props.filters.search !== '' || props.filters.status !== '');

watch(() => props.filters, (filters) => { search.value = filters.search; selectedStatus.value = filters.status; }, { deep: true });
const visitIndex = (nextSearch: string, nextStatus: string) => router.get('/admin/checkouts', {
    ...(nextSearch.trim() ? { search: nextSearch.trim() } : {}),
    ...(nextStatus ? { status: nextStatus } : {}),
}, { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const applyFilters = () => visitIndex(search.value, selectedStatus.value);
const clearFilters = () => { search.value = ''; selectedStatus.value = ''; visitIndex('', ''); };
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('checkout.notSet');
const money = (row: CheckoutRow) => row.currency ? `${row.currency} ${row.grand_total}` : row.grand_total;
</script>

<template>
    <Head :title="t('checkout.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('checkout.title') }]" />
            <PageHeader :title="t('checkout.title')" :eyebrow="t('common.currentStore')" :description="t('checkout.description')"><template #actions><Link v-if="canManage" href="/admin/checkouts/create" class="inline-flex min-h-9 w-full items-center justify-center gap-2 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover sm:w-auto"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 5v14M5 12h14" /></svg>{{ t('checkout.start') }}</Link></template></PageHeader>

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end sm:p-4" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5" for="checkout-search"><span class="admin-label">{{ t('checkout.search') }}</span><span class="relative"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-admin-neutral" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="1.7" /><path stroke-linecap="round" stroke-width="1.7" d="m16 16 4 4" /></svg><input id="checkout-search" v-model="search" type="search" autocomplete="off" :placeholder="t('checkout.searchPlaceholder')" class="admin-control w-full pl-9 pr-3 text-sm" /></span></label>
                <label class="grid gap-1.5" for="checkout-status"><span class="admin-label">{{ t('common.status') }}</span><select id="checkout-status" v-model="selectedStatus" class="admin-control w-full px-3 text-sm"><option value="">{{ t('checkout.allStatuses') }}</option><option v-for="value in checkoutStatuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <div class="flex gap-2"><button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60 sm:flex-none">{{ filtering ? t('checkout.filtering') : t('checkout.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text-secondary disabled:opacity-60 sm:flex-none" @click="clearFilters">{{ t('common.clear') }}</button></div>
            </form>

            <template v-if="checkouts.data.length">
                <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[17%] p-3">{{ t('checkout.checkout') }}</th><th class="w-[20%] p-3">{{ t('checkout.customer') }}</th><th class="w-[13%] p-3">{{ t('common.status') }}</th><th class="w-[16%] p-3 text-right">{{ t('checkout.total') }}</th><th class="w-[15%] p-3">{{ t('checkout.expires') }}</th><th class="w-[13%] p-3">{{ t('checkout.created') }}</th><th class="w-[6%] p-3 text-right">{{ t('common.actions') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="entry in checkouts.data" :key="entry.id"><td class="p-3"><Link :href="`/admin/checkouts/${entry.id}`" class="admin-link font-mono text-xs">{{ entry.token_hint }}</Link></td><td class="truncate p-3 text-admin-text-secondary" :title="entry.customer">{{ entry.customer }}</td><td class="p-3"><StatusBadge :status="entry.status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ money(entry) }}</td><td class="p-3 text-admin-neutral">{{ formatDate(entry.expires_at) }}</td><td class="p-3 text-admin-neutral">{{ formatDate(entry.created_at) }}</td><td class="p-3 text-right"><Link :href="`/admin/checkouts/${entry.id}`" class="admin-link">{{ t('common.view') }}</Link></td></tr></tbody></table></div>
                <div class="grid gap-2.5 md:hidden"><article v-for="entry in checkouts.data" :key="`mobile-${entry.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/checkouts/${entry.id}`" class="admin-link font-mono text-xs">{{ entry.token_hint }}</Link><p class="mt-1 truncate text-xs text-admin-text-secondary">{{ entry.customer }}</p></div><StatusBadge :status="entry.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('checkout.total') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ money(entry) }}</dd></div><div><dt class="admin-label">{{ t('checkout.expires') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(entry.expires_at) }}</dd></div><div class="col-span-2"><dt class="admin-label">{{ t('checkout.created') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(entry.created_at) }}</dd></div></dl><div class="mt-3 text-right"><Link :href="`/admin/checkouts/${entry.id}`" class="admin-link text-xs">{{ t('common.view') }}</Link></div></article></div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('checkout.noMatch') : t('checkout.empty')" :description="hasFilters ? t('checkout.noMatchHelp') : t('checkout.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('checkout.clearFilters') }}</button></template><template v-else-if="canManage" #actions><Link href="/admin/checkouts/create" class="inline-flex min-h-9 items-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('checkout.start') }}</Link></template></EmptyState>
            <Pagination :links="checkouts.links" :from="checkouts.from" :to="checkouts.to" :total="checkouts.total" preserve-state />
        </section>
    </AdminLayout>
</template>
