<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ExchangeSummary { id: string; short_id: string; return_id: string; order_id: string; order_number: string; customer_name: string; customer_email: string | null; status: string; item_count: number; created_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: ExchangeSummary[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface ExchangeFilters { search: string; status: string; order_id: string }

const props = defineProps<{ exchanges: Paginator; filters: ExchangeFilters; statuses: string[] }>();
const { t, locale } = useI18n();
const filters = ref<ExchangeFilters>({ ...props.filters });
const isFiltering = ref(false);
const hasFilters = computed(() => Boolean(props.filters.search || props.filters.status || props.filters.order_id));
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('common.notAvailable');
const applyFilters = () => { isFiltering.value = true; router.get('/admin/exchanges', { ...filters.value }, { preserveState: true, replace: true, onFinish: () => { isFiltering.value = false; } }); };
const clearFilters = () => { filters.value = { search: '', status: '', order_id: '' }; applyFilters(); };
const clearOrderFilter = () => { filters.value.order_id = ''; applyFilters(); };
</script>

<template>
    <Head :title="t('exchanges.title')" />
    <AdminLayout><div class="space-y-6">
        <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('exchanges.title') }]" />
        <PageHeader :title="t('exchanges.title')" :eyebrow="t('exchanges.workQueue')" :description="t('exchanges.description')"><template #actions><Link href="/admin/returns" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold hover:bg-slate-50">{{ t('exchanges.openReturns') }}</Link></template></PageHeader>
        <form class="admin-card grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" @submit.prevent="applyFilters"><label class="grid gap-1.5" for="exchange-search"><span class="admin-label">{{ t('exchanges.search') }}</span><input id="exchange-search" v-model="filters.search" maxlength="120" class="admin-control min-w-0 px-3 text-sm" :placeholder="t('exchanges.searchPlaceholder')" /></label><label class="grid gap-1.5" for="exchange-status"><span class="admin-label">{{ t('common.status') }}</span><select id="exchange-status" v-model="filters.status" class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('exchanges.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label><div class="grid grid-cols-2 gap-2 sm:flex"><button type="button" class="min-h-10 rounded-md border border-admin-border-strong px-3 text-sm font-semibold" @click="clearFilters">{{ t('exchanges.clearFilters') }}</button><button :disabled="isFiltering" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-50">{{ isFiltering ? t('exchanges.filtering') : t('exchanges.filter') }}</button></div></form>
        <div v-if="filters.order_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs text-admin-text-secondary"><span>{{ t('exchanges.orderFilter', { id: filters.order_id.slice(0, 8) }) }}</span><button type="button" class="font-semibold text-admin-secondary" @click="clearOrderFilter">{{ t('exchanges.showAllOrders') }}</button></div>
        <template v-if="exchanges.data.length">
            <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[17%] p-3">{{ t('exchanges.exchangeRecord') }}</th><th class="w-[17%] p-3">{{ t('exchanges.order') }}</th><th class="w-[24%] p-3">{{ t('exchanges.customer') }}</th><th class="w-[14%] p-3">{{ t('common.status') }}</th><th class="w-[11%] p-3 text-right">{{ t('exchanges.items') }}</th><th class="w-[17%] p-3">{{ t('exchanges.created') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="exchange in exchanges.data" :key="exchange.id"><td class="p-3"><Link :href="`/admin/exchanges/${exchange.id}`" class="admin-link font-mono font-semibold">{{ exchange.short_id }}</Link><p class="mt-1"><Link :href="`/admin/returns/${exchange.return_id}`" class="admin-link text-[10px]">{{ t('exchanges.openSourceReturn') }}</Link></p></td><td class="p-3"><Link :href="`/admin/orders/${exchange.order_id}`" class="admin-link font-mono font-semibold">{{ exchange.order_number }}</Link></td><td class="p-3"><p class="truncate font-medium">{{ exchange.customer_name }}</p><p class="mt-0.5 truncate text-admin-neutral">{{ exchange.customer_email ?? t('exchanges.noEmail') }}</p></td><td class="p-3"><StatusBadge :status="exchange.status" /></td><td class="p-3 text-right font-mono font-semibold">{{ exchange.item_count }}</td><td class="p-3 text-admin-neutral">{{ formatDate(exchange.created_at) }}</td></tr></tbody></table></div>
            <div class="grid gap-2.5 md:hidden"><article v-for="exchange in exchanges.data" :key="`mobile-${exchange.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/exchanges/${exchange.id}`" class="admin-link font-mono text-xs font-semibold">{{ t('exchanges.exchangeNumber', { id: exchange.short_id }) }}</Link><p class="mt-1 truncate text-sm font-semibold">{{ exchange.order_number }}</p></div><StatusBadge :status="exchange.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('exchanges.customer') }}</dt><dd class="mt-1 truncate text-xs">{{ exchange.customer_name }}</dd><dd class="truncate text-[11px] text-admin-neutral">{{ exchange.customer_email ?? t('exchanges.noEmail') }}</dd></div><div><dt class="admin-label">{{ t('exchanges.items') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ t('exchanges.lineCount', { count: exchange.item_count }) }}</dd></div></dl><div class="mt-3 flex items-center justify-between gap-3"><time class="text-[11px] text-admin-neutral">{{ formatDate(exchange.created_at) }}</time><Link :href="`/admin/exchanges/${exchange.id}`" class="admin-link text-xs">{{ t('exchanges.openExchange') }}</Link></div></article></div>
        </template>
        <EmptyState v-else :title="hasFilters ? t('exchanges.noMatch') : t('exchanges.empty')" :description="hasFilters ? t('exchanges.noMatchHelp') : t('exchanges.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-xs" @click="clearFilters">{{ t('exchanges.clearFilters') }}</button></template></EmptyState>
        <Pagination :links="exchanges.links" :from="exchanges.from" :to="exchanges.to" :total="exchanges.total" preserve-state />
    </div></AdminLayout>
</template>
