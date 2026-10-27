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

interface Fulfillment {
    id: string;
    order_id: string;
    order_number: string;
    location_name: string;
    location_code: string;
    status: string;
    item_count: number;
    total_quantity: number;
    shipment_statuses: string[];
    created_at: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: Fulfillment[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface FulfillmentFilters { search: string; status: string; order_id: string }

const props = defineProps<{ fulfillments: Paginator; filters: FulfillmentFilters; statuses: string[] }>();
const { t, locale } = useI18n();
const filters = ref<FulfillmentFilters>({ ...props.filters });
const isFiltering = ref(false);
const hasFilters = computed(() => Boolean(props.filters.search || props.filters.status || props.filters.order_id));
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const applyFilters = () => {
    isFiltering.value = true;
    router.get('/admin/fulfillments', { ...filters.value }, { preserveState: true, replace: true, onFinish: () => { isFiltering.value = false; } });
};
const clearFilters = () => { filters.value = { search: '', status: '', order_id: '' }; applyFilters(); };
const clearOrderFilter = () => { filters.value.order_id = ''; applyFilters(); };
</script>

<template>
    <Head :title="t('fulfillment.title')" />
    <AdminLayout>
        <div class="space-y-6">
            <Breadcrumbs :items="[{ label: t('nav.operations') }, { label: t('fulfillment.title') }]" />
            <PageHeader :title="t('fulfillment.title')" :eyebrow="t('fulfillment.workQueue')" :description="t('fulfillment.description')">
                <template #actions><Link href="/admin/orders" class="inline-flex min-h-10 items-center justify-center rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold text-admin-text hover:bg-slate-50">{{ t('fulfillment.openOrders') }}</Link></template>
            </PageHeader>

            <form class="admin-card grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" @submit.prevent="applyFilters">
                <label class="grid gap-1.5" for="fulfillment-search"><span class="admin-label">{{ t('fulfillment.search') }}</span><input id="fulfillment-search" v-model="filters.search" maxlength="120" class="admin-control min-w-0 px-3 text-sm" :placeholder="t('fulfillment.searchPlaceholder')" /></label>
                <label class="grid gap-1.5" for="fulfillment-status"><span class="admin-label">{{ t('common.status') }}</span><select id="fulfillment-status" v-model="filters.status" class="admin-control min-w-0 px-3 text-sm"><option value="">{{ t('fulfillment.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <div class="grid grid-cols-2 gap-2 sm:flex"><button type="button" class="min-h-10 rounded-md border border-admin-border-strong px-3 text-sm font-semibold" @click="clearFilters">{{ t('fulfillment.clearFilters') }}</button><button :disabled="isFiltering" class="min-h-10 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-50">{{ isFiltering ? t('fulfillment.filtering') : t('fulfillment.filter') }}</button></div>
            </form>

            <div v-if="filters.order_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs text-admin-text-secondary">
                <span>{{ t('fulfillment.orderFilter', { id: filters.order_id.slice(0, 8) }) }}</span><button type="button" class="font-semibold text-admin-secondary" @click="clearOrderFilter">{{ t('fulfillment.showAllOrders') }}</button>
            </div>

            <template v-if="fulfillments.data.length">
                <div class="admin-card hidden overflow-hidden md:block">
                    <table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[20%] p-3">{{ t('fulfillment.order') }}</th><th class="w-[19%] p-3">{{ t('fulfillment.location') }}</th><th class="w-[13%] p-3">{{ t('common.status') }}</th><th class="w-[15%] p-3">{{ t('fulfillment.work') }}</th><th class="w-[15%] p-3">{{ t('fulfillment.shipment') }}</th><th class="w-[18%] p-3">{{ t('fulfillment.created') }}</th></tr></thead>
                        <tbody class="divide-y divide-admin-border"><tr v-for="fulfillment in fulfillments.data" :key="fulfillment.id"><td class="p-3"><Link :href="`/admin/fulfillments/${fulfillment.id}`" class="admin-link font-mono font-semibold">{{ fulfillment.order_number }}</Link><p class="mt-1 truncate font-mono text-[10px] text-admin-neutral" :title="fulfillment.id">{{ fulfillment.id }}</p></td><td class="p-3"><p class="font-mono font-medium">{{ fulfillment.location_code }}</p><p class="mt-0.5 truncate text-admin-neutral" :title="fulfillment.location_name">{{ fulfillment.location_name }}</p></td><td class="p-3"><StatusBadge :status="fulfillment.status" /></td><td class="p-3"><p class="font-mono font-semibold">{{ t('fulfillment.unitCount', { count: fulfillment.total_quantity }) }}</p><p class="mt-0.5 text-admin-neutral">{{ t('fulfillment.lineCount', { count: fulfillment.item_count }) }}</p></td><td class="p-3"><StatusBadge v-if="fulfillment.shipment_statuses[0]" :status="fulfillment.shipment_statuses[0]" /><span v-else class="text-admin-neutral">{{ t('fulfillment.notCreated') }}</span></td><td class="p-3 text-admin-neutral">{{ formatDate(fulfillment.created_at) }}</td></tr></tbody>
                    </table>
                </div>
                <div class="grid gap-2.5 md:hidden">
                    <article v-for="fulfillment in fulfillments.data" :key="`mobile-${fulfillment.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/fulfillments/${fulfillment.id}`" class="admin-link font-mono text-xs font-semibold">{{ fulfillment.order_number }}</Link><p class="mt-1 truncate text-sm font-medium">{{ fulfillment.location_code }} · {{ fulfillment.location_name }}</p></div><StatusBadge :status="fulfillment.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('fulfillment.work') }}</dt><dd class="mt-1 text-xs">{{ t('fulfillment.workSummary', { lines: fulfillment.item_count, units: fulfillment.total_quantity }) }}</dd></div><div><dt class="admin-label">{{ t('fulfillment.shipment') }}</dt><dd class="mt-1"><StatusBadge v-if="fulfillment.shipment_statuses[0]" :status="fulfillment.shipment_statuses[0]" /><span v-else class="text-xs text-admin-neutral">{{ t('fulfillment.notCreated') }}</span></dd></div></dl><div class="mt-3 flex items-center justify-between gap-3"><time class="text-[11px] text-admin-neutral">{{ formatDate(fulfillment.created_at) }}</time><Link :href="`/admin/fulfillments/${fulfillment.id}`" class="admin-link text-xs">{{ t('fulfillment.openFulfillment') }}</Link></div></article>
                </div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('fulfillment.noMatch') : t('fulfillment.empty')" :description="hasFilters ? t('fulfillment.noMatchHelp') : t('fulfillment.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-xs" @click="clearFilters">{{ t('fulfillment.clearFilters') }}</button></template></EmptyState>
            <Pagination :links="fulfillments.links" :from="fulfillments.from" :to="fulfillments.to" :total="fulfillments.total" preserve-state />
        </div>
    </AdminLayout>
</template>
