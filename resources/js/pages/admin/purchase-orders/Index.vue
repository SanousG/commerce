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

interface PurchaseOrder { id: string; number: string; supplier_name: string; status: string; currency: string; grand_total: string; ordered_quantity: number; received_quantity: number; remaining_quantity: number; ordered_at: string | null; expected_at: string | null; created_at: string }
interface Supplier { id: string; name: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: PurchaseOrder[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }

const props = defineProps<{ purchaseOrders: Paginator; filters: { search: string; supplier_id: string; status: string }; suppliers: Supplier[]; statuses: string[] }>();
const { t, te, locale } = useI18n();
const page = usePage<PageProps>();
const filters = ref({ ...props.filters });
const filtering = ref(false);
const canCreate = computed(() => page.props.currentTenantMembership?.permissions.includes('procurement.manage') ?? false);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));
watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });
const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value));
const applyFilters = () => router.get('/admin/purchase-orders', query(), { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const clearFilters = () => { filters.value = { search: '', supplier_id: '', status: '' }; applyFilters(); };
const date = (value: string | null) => value ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(new Date(value)) : t('procurement.notSet');
const decimal = (value: string) => { const [integer, fraction = ''] = value.split('.'); return `${BigInt(integer).toLocaleString(locale.value)}${fraction ? `${locale.value.startsWith('es') ? ',' : '.'}${fraction}` : ''}`; };
const money = (order: PurchaseOrder) => `${order.currency} ${decimal(order.grand_total)}`;
</script>

<template>
    <Head :title="t('procurement.orders.title')" />
    <AdminLayout><section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
        <Breadcrumbs :items="[{ label: t('nav.procurement') }, { label: t('procurement.orders.title') }]" />
        <PageHeader :title="t('procurement.orders.title')" :eyebrow="t('common.allCompany')" :description="t('procurement.orders.description')"><template #actions><Link v-if="canCreate" href="/admin/purchase-orders/create" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-admin-primary px-4 text-sm font-semibold text-white sm:w-auto">{{ t('procurement.orders.create') }}</Link></template></PageHeader>
        <form class="admin-card grid gap-3 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-[minmax(0,1fr)_14rem_13rem_auto]" role="search" @submit.prevent="applyFilters"><label class="grid gap-1.5"><span class="admin-label">{{ t('procurement.orders.search') }}</span><input v-model="filters.search" type="search" maxlength="160" autocomplete="off" :placeholder="t('procurement.orders.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label><label class="grid gap-1.5"><span class="admin-label">{{ t('procurement.supplier') }}</span><select v-model="filters.supplier_id" class="admin-control w-full px-3 text-sm"><option value="">{{ t('procurement.allSuppliers') }}</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select></label><label class="grid gap-1.5"><span class="admin-label">{{ t('common.status') }}</span><select v-model="filters.status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('procurement.allStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ te(`status.${value}`) ? t(`status.${value}`) : value }}</option></select></label><div class="flex items-end gap-2"><button :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-50">{{ filtering ? t('common.searching') : t('procurement.filter') }}</button><button v-if="hasFilters" type="button" class="min-h-9 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button></div></form>
        <template v-if="purchaseOrders.data.length"><div class="admin-card hidden overflow-hidden lg:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[14%] p-3">{{ t('procurement.purchaseOrder') }}</th><th class="w-[19%] p-3">{{ t('procurement.supplier') }}</th><th class="w-[13%] p-3">{{ t('common.status') }}</th><th class="w-[15%] p-3 text-right">{{ t('procurement.total') }}</th><th class="w-[10%] p-3 text-right">{{ t('procurement.orderedQty') }}</th><th class="w-[10%] p-3 text-right">{{ t('procurement.receivedQty') }}</th><th class="w-[9%] p-3 text-right">{{ t('procurement.remaining') }}</th><th class="w-[10%] p-3">{{ t('procurement.expected') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="order in purchaseOrders.data" :key="order.id"><td class="p-3"><Link :href="`/admin/purchase-orders/${order.id}`" class="admin-link font-mono font-semibold">{{ order.number }}</Link></td><td class="p-3 font-medium">{{ order.supplier_name }}</td><td class="p-3"><StatusBadge :status="order.status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ money(order) }}</td><td class="p-3 text-right font-mono tabular-nums">{{ order.ordered_quantity }}</td><td class="p-3 text-right font-mono tabular-nums">{{ order.received_quantity }}</td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ order.remaining_quantity }}</td><td class="p-3 text-admin-neutral">{{ date(order.expected_at) }}</td></tr></tbody></table></div>
            <div class="grid gap-2.5 lg:hidden"><article v-for="order in purchaseOrders.data" :key="`mobile-${order.id}`" class="admin-card min-w-0 p-4"><div class="flex items-start justify-between gap-3"><div><Link :href="`/admin/purchase-orders/${order.id}`" class="admin-link font-mono font-semibold">{{ order.number }}</Link><p class="mt-1 break-words text-sm font-medium">{{ order.supplier_name }}</p></div><StatusBadge :status="order.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-3 text-xs sm:grid-cols-4"><div><dt class="admin-label">{{ t('procurement.total') }}</dt><dd class="mt-1 font-mono font-semibold tabular-nums">{{ money(order) }}</dd></div><div><dt class="admin-label">{{ t('procurement.progress') }}</dt><dd class="mt-1 font-mono tabular-nums">{{ order.received_quantity }} / {{ order.ordered_quantity }}</dd></div><div><dt class="admin-label">{{ t('procurement.remaining') }}</dt><dd class="mt-1 font-mono font-semibold tabular-nums">{{ order.remaining_quantity }}</dd></div><div><dt class="admin-label">{{ t('procurement.expected') }}</dt><dd class="mt-1">{{ date(order.expected_at) }}</dd></div></dl><Link :href="`/admin/purchase-orders/${order.id}`" class="admin-link mt-3 inline-flex min-h-9 items-center text-sm">{{ t('procurement.openOrder') }}</Link></article></div></template>
        <EmptyState v-else :title="hasFilters ? t('procurement.orders.noMatch') : t('procurement.orders.empty')" :description="hasFilters ? t('procurement.orders.noMatchHelp') : t('procurement.orders.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('procurement.clearFilters') }}</button></template></EmptyState>
        <Pagination :links="purchaseOrders.links" :from="purchaseOrders.from" :to="purchaseOrders.to" :total="purchaseOrders.total" preserve-state />
    </section></AdminLayout>
</template>
