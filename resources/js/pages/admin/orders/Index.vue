<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Breadcrumbs from '../../../components/admin/Breadcrumbs.vue';
import EmptyState from '../../../components/admin/EmptyState.vue';
import PageHeader from '../../../components/admin/PageHeader.vue';
import Pagination from '../../../components/admin/Pagination.vue';
import StatusBadge from '../../../components/admin/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface OrderRow { id: string; order_number: string; customer_name: string; email: string | null; status: string; payment_status: string; fulfillment_status: string; currency: string; grand_total: string; placed_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: OrderRow[]; links: PageLink[]; from?: number | null; to?: number | null; total?: number }
interface OrderFilters { search: string; status: string; payment_status: string; fulfillment_status: string; customer_id: string; from: string; to: string }

const props = defineProps<{ orders: Paginator; filters: OrderFilters; statuses: string[]; paymentStatuses: string[]; fulfillmentStatuses: string[] }>();
const { t, locale } = useI18n();
const filters = ref<OrderFilters>({ ...props.filters });
const filtering = ref(false);
const hasFilters = computed(() => Object.values(props.filters).some(Boolean));

watch(() => props.filters, (value) => { filters.value = { ...value }; }, { deep: true });
const query = () => Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== ''));
const applyFilters = () => router.get('/admin/orders', query(), { preserveState: true, replace: true, onStart: () => { filtering.value = true; }, onFinish: () => { filtering.value = false; } });
const clearFilters = () => { filters.value = { search: '', status: '', payment_status: '', fulfillment_status: '', customer_id: '', from: '', to: '' }; applyFilters(); };
const clearCustomer = () => { filters.value.customer_id = ''; applyFilters(); };
const formatDate = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const formatAmount = (value: string) => {
    const [integer, decimal = ''] = value.split('.');
    return `${BigInt(integer).toLocaleString(locale.value)}${decimal ? `${locale.value.startsWith('es') ? ',' : '.'}${decimal}` : ''}`;
};
</script>

<template>
    <Head :title="t('orders.title')" />
    <AdminLayout>
        <section class="mx-auto grid w-full max-w-[90rem] gap-5 sm:gap-6">
            <Breadcrumbs :items="[{ label: t('nav.sales') }, { label: t('orders.title') }]" />
            <PageHeader :title="t('orders.title')" :eyebrow="t('common.currentStore')" :description="t('orders.description')" />

            <form class="admin-card grid gap-3 p-3 sm:grid-cols-2 sm:p-4 xl:grid-cols-4" role="search" @submit.prevent="applyFilters">
                <label class="grid min-w-0 gap-1.5 sm:col-span-2" for="order-search"><span class="admin-label">{{ t('orders.search') }}</span><input id="order-search" v-model="filters.search" type="search" autocomplete="off" :placeholder="t('orders.searchPlaceholder')" class="admin-control w-full px-3 text-sm" /></label>
                <label class="grid gap-1.5" for="order-status"><span class="admin-label">{{ t('orders.orderStatus') }}</span><select id="order-status" v-model="filters.status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('orders.allOrderStatuses') }}</option><option v-for="value in statuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <label class="grid gap-1.5" for="payment-status"><span class="admin-label">{{ t('orders.paymentStatus') }}</span><select id="payment-status" v-model="filters.payment_status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('orders.allPaymentStatuses') }}</option><option v-for="value in paymentStatuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <label class="grid gap-1.5" for="fulfillment-status"><span class="admin-label">{{ t('orders.fulfillmentStatus') }}</span><select id="fulfillment-status" v-model="filters.fulfillment_status" class="admin-control w-full px-3 text-sm"><option value="">{{ t('orders.allFulfillmentStatuses') }}</option><option v-for="value in fulfillmentStatuses" :key="value" :value="value">{{ t(`status.${value}`) }}</option></select></label>
                <label class="grid gap-1.5" for="order-from"><span class="admin-label">{{ t('orders.placedFrom') }}</span><input id="order-from" v-model="filters.from" type="date" class="admin-control w-full px-3 text-sm" /></label>
                <label class="grid gap-1.5" for="order-to"><span class="admin-label">{{ t('orders.placedTo') }}</span><input id="order-to" v-model="filters.to" type="date" class="admin-control w-full px-3 text-sm" /></label>
                <div class="flex items-end gap-2"><button type="submit" :disabled="filtering" class="min-h-9 flex-1 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white disabled:opacity-60">{{ filtering ? t('orders.filtering') : t('orders.filter') }}</button><button v-if="hasFilters" type="button" :disabled="filtering" class="min-h-9 flex-1 rounded-md border border-admin-border-strong bg-white px-4 text-sm font-semibold" @click="clearFilters">{{ t('common.clear') }}</button></div>
            </form>

            <div v-if="filters.customer_id" class="flex flex-wrap items-center gap-2 rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2 text-xs"><span>{{ t('orders.customerFilter', { id: filters.customer_id.slice(0, 8) }) }}</span><button type="button" class="admin-link font-semibold" @click="clearCustomer">{{ t('orders.showAllCustomers') }}</button></div>

            <template v-if="orders.data.length">
                <div class="admin-card hidden overflow-hidden md:block"><table class="admin-table w-full table-fixed text-left text-xs"><thead><tr><th class="w-[15%] p-3">{{ t('orders.order') }}</th><th class="w-[20%] p-3">{{ t('orders.customer') }}</th><th class="w-[12%] p-3">{{ t('orders.orderStatus') }}</th><th class="w-[12%] p-3">{{ t('orders.payment') }}</th><th class="w-[12%] p-3">{{ t('orders.fulfillment') }}</th><th class="w-[15%] p-3 text-right">{{ t('orders.total') }}</th><th class="w-[14%] p-3">{{ t('orders.placed') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="order in orders.data" :key="order.id"><td class="p-3"><Link :href="`/admin/orders/${order.id}`" class="admin-link font-mono font-semibold">{{ order.order_number }}</Link></td><td class="p-3"><p class="truncate font-medium" :title="order.customer_name">{{ order.customer_name }}</p><p class="truncate text-admin-neutral" :title="order.email ?? undefined">{{ order.email ?? t('orders.noEmail') }}</p></td><td class="p-3"><StatusBadge :status="order.status" /></td><td class="p-3"><StatusBadge :status="order.payment_status" /></td><td class="p-3"><StatusBadge :status="order.fulfillment_status" /></td><td class="p-3 text-right font-mono font-semibold tabular-nums">{{ order.currency }} {{ formatAmount(order.grand_total) }}</td><td class="p-3 text-admin-neutral">{{ formatDate(order.placed_at) }}</td></tr></tbody></table></div>
                <div class="grid gap-2.5 md:hidden"><article v-for="order in orders.data" :key="`mobile-${order.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><Link :href="`/admin/orders/${order.id}`" class="admin-link font-mono text-xs font-semibold">{{ order.order_number }}</Link><p class="mt-1 truncate text-sm font-medium">{{ order.customer_name }}</p><p class="truncate text-xs text-admin-neutral">{{ order.email ?? t('orders.noEmail') }}</p></div><StatusBadge :status="order.status" /></div><dl class="mt-3 grid grid-cols-2 gap-3 border-y border-admin-border py-2.5"><div><dt class="admin-label">{{ t('orders.payment') }}</dt><dd class="mt-1"><StatusBadge :status="order.payment_status" /></dd></div><div><dt class="admin-label">{{ t('orders.fulfillment') }}</dt><dd class="mt-1"><StatusBadge :status="order.fulfillment_status" /></dd></div><div><dt class="admin-label">{{ t('orders.total') }}</dt><dd class="mt-1 font-mono text-xs font-semibold">{{ order.currency }} {{ formatAmount(order.grand_total) }}</dd></div><div><dt class="admin-label">{{ t('orders.placed') }}</dt><dd class="mt-1 text-xs text-admin-neutral">{{ formatDate(order.placed_at) }}</dd></div></dl><div class="mt-3 text-right"><Link :href="`/admin/orders/${order.id}`" class="admin-link text-xs">{{ t('orders.openOrder') }}</Link></div></article></div>
            </template>
            <EmptyState v-else :title="hasFilters ? t('orders.noMatch') : t('orders.empty')" :description="hasFilters ? t('orders.noMatchHelp') : t('orders.emptyHelp')"><template v-if="hasFilters" #actions><button type="button" class="admin-link text-sm" @click="clearFilters">{{ t('orders.clearFilters') }}</button></template></EmptyState>
            <Pagination :links="orders.links" :from="orders.from" :to="orders.to" :total="orders.total" preserve-state />
        </section>
    </AdminLayout>
</template>
