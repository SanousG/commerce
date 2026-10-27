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

interface ReturnSummary { id: string; short_id: string; order_id: string; order_number: string; customer_name: string; customer_email: string | null; status: string; requested_at: string | null; item_count: number }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: ReturnSummary[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface ReturnFilters { search: string; status: string; order_id: string }

const props = defineProps<{ returns: Paginator; filters: ReturnFilters; statuses: string[] }>();
const { t, locale } = useI18n();
const filters = ref<ReturnFilters>({ ...props.filters });
const isFiltering = ref(false);
const hasFilters = computed(() => Boolean(props.filters.search || props.filters.status || props.filters.order_id));
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : t('common.notAvailable');
const applyFilters = () => {
    isFiltering.value = true;
    router.get('/admin/returns', { ...filters.value }, { preserveState: true, replace: true, onFinish: () => { isFiltering.value = false; } });
};
const clearFilters = () => { filters.value = { search: '', status: '', order_id: '' }; applyFilters(); };
const clearOrderFilter = () => { filters.value.order_id = ''; applyFilters(); };
</script>

<template>
    <Head :title="t('returns.title')" />
    <AdminLayout><div class="space-y-6">
        <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('returns.title') }]" />
        <PageHeader :title="t('returns.title')" :eyebrow="t('returns.workQueue')" :description="t('returns.description')">
            <template #actions><div class="grid grid-cols-2 gap-2 sm:flex"><Link href="/admin/exchanges" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold hover:bg-slate-50">{{ t('returns.openExchanges') }}</Link><Link href="/admin/orders" class="inline-flex min-h-10 items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white">{{ t('returns.openOrders') }}</Link></div></template>
        </PageHeader>

        <form class="admin-card grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" @submit.prevent="applyFilters">
            <label class="grid gap-1.5" for="return-search"><span class="admin-label">{{ t('returns.search') }}</span><input id="return-search" v-model="filters.search" maxlength="120" class="admin-control min-w-0 px-3 text-sm" :placeholder="t('returns.searchPlaceholder')" /></label>
            <label class="grid gap-1.5" for="return-status"><span class="admin-label">{{ t('common.status') }}</span><select id="return-status" v-model="filters.status" class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('returns.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
            <div class="grid grid-cols-2 gap-2 sm:flex"><button type="button" class="min-h-10 rounded-md border border-admin-border-strong px-3 text-sm font-semibold" @click="clearFilters">{{ t('returns.clearFilters') }}</button><button :disabled="isFiltering" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-50">{{ isFiltering ? t('returns.filtering') : t('returns.filter') }}</button></div>
        </form>
        <div v-if="filters.order_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs text-admin-text-secondary"><span>{{ t('returns.orderFilter', { id: filters.order_id.slice(0, 8) }) }}</span><button type="button" class="font-semibold text-admin-secondary" @click="clearOrderFilter">{{ t('returns.showAllOrders') }}</button></div>

        <template v-if="returns.data.length">
            <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[14%] p-3">{{ t('returns.returnRecord') }}</th><th class="w-[18%] p-3">{{ t('returns.order') }}</th><th class="w-[25%] p-3">{{ t('returns.customer') }}</th><th class="w-[14%] p-3">{{ t('common.status') }}</th><th class="w-[11%] p-3 text-right">{{ t('returns.items') }}</th><th class="w-[18%] p-3">{{ t('returns.requested') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="entry in returns.data" :key="entry.id"><td class="p-3"><Link :href="`/admin/returns/${entry.id}`" class="admin-link font-mono font-semibold">{{ entry.short_id }}</Link></td><td class="p-3"><Link :href="`/admin/orders/${entry.order_id}`" class="admin-link font-mono font-semibold">{{ entry.order_number }}</Link></td><td class="p-3"><p class="truncate font-medium" :title="entry.customer_name">{{ entry.customer_name }}</p><p class="mt-0.5 truncate text-admin-neutral" :title="entry.customer_email ?? undefined">{{ entry.customer_email ?? t('returns.noEmail') }}</p></td><td class="p-3"><StatusBadge :status="entry.status" /></td><td class="p-3 text-right font-mono font-semibold">{{ entry.item_count }}</td><td class="p-3 text-admin-neutral">{{ formatDate(entry.requested_at) }}</td></tr></tbody></table></div>
            <div class="grid gap-2.5 md:hidden"><article v-for="entry in returns.data" :key="`mobile-${entry.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/returns/${entry.id}`" class="admin-link font-mono text-xs font-semibold">{{ t('returns.returnNumber', { id: entry.short_id }) }}</Link><p class="mt-1 truncate text-sm font-semibold">{{ entry.order_number }}</p></div><StatusBadge :status="entry.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('returns.customer') }}</dt><dd class="mt-1 truncate text-xs">{{ entry.customer_name }}</dd><dd class="truncate text-[11px] text-admin-neutral">{{ entry.customer_email ?? t('returns.noEmail') }}</dd></div><div><dt class="admin-label">{{ t('returns.items') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ t('returns.lineCount', { count: entry.item_count }) }}</dd></div></dl><div class="mt-3 flex items-center justify-between gap-3"><time class="text-[11px] text-admin-neutral">{{ formatDate(entry.requested_at) }}</time><Link :href="`/admin/returns/${entry.id}`" class="admin-link text-xs">{{ t('returns.openReturn') }}</Link></div></article></div>
        </template>
        <EmptyState v-else :title="hasFilters ? t('returns.noMatch') : t('returns.empty')" :description="hasFilters ? t('returns.noMatchHelp') : t('returns.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-xs" @click="clearFilters">{{ t('returns.clearFilters') }}</button></template></EmptyState>
        <Pagination :links="returns.links" :from="returns.from" :to="returns.to" :total="returns.total" preserve-state />
    </div></AdminLayout>
</template>
