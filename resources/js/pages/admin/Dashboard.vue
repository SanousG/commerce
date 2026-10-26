<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminLayout from '../../layouts/AdminLayout.vue';
import Breadcrumbs from '../../components/admin/Breadcrumbs.vue';
import PageHeader from '../../components/admin/PageHeader.vue';
import StatusBadge from '../../components/admin/StatusBadge.vue';
import DashboardTrendChart from '../../components/DashboardTrendChart.vue';
import MetricDelta from '../../components/MetricDelta.vue';
import FieldError from '../../components/FieldError.vue';
import type { PageProps } from '../../types';

interface TrendPoint { date: string; gross_sales: string; orders: number }
interface MetricComparison { current: string | number; previous: string | number; delta: string | number; percent: string | null }
interface RangeInfo { key: string; label: string; timezone: string; local_start: string; local_end_inclusive: string; local_end_exclusive: string; utc_start: string; utc_end_exclusive: string }
interface AttentionItem { key: string; label: string; count: number; href: string; scope: 'store' | 'tenant' }
interface SalesSection {
    gross_sales: string;
    orders: number;
    average_order_value: string;
    orders_by_status: Record<string, number>;
    trend: TrendPoint[];
    top_products: Array<{ product_id: string | null; product: string; units: number; revenue: string }>;
    excluded_currency_orders: number;
}
interface PaymentSection {
    captured_amount: string;
    net_paid: string;
    pending_amount: string;
    refunded_amount?: string;
    pending_refunds?: number;
    refunds_by_status?: Record<string, number>;
    unpaid_orders: number;
    orders_by_payment_status: Record<string, number>;
    currency_warning_transactions: number;
}
interface FulfillmentSection {
    orders_by_status: Record<string, number>;
    fulfillments_pending: number;
    shipments_pending_or_ready?: number;
    shipped_not_delivered?: number;
}
interface InventorySection {
    threshold: number;
    out_of_stock_variants: number;
    low_stock_variants: number;
    items: Array<{ variant_id: string; product: string; variant: string | null; sku: string; available: number; on_hand: number; reserved: number }>;
}
interface ProcurementSection {
    by_status: Record<string, number>;
    to_receive: number;
    overdue: number;
    incoming_units: number;
    purchase_orders: Array<{ id: string; number: string; supplier: string; expected_at: string | null; remaining_quantity: number; status: string }>;
}
interface ReturnsSection { by_status: Record<string, number>; open: number; awaiting_inspection: number; restock_pending: number; pending_exchanges?: number }
interface RecentOrder { id: string; number: string; customer: string | null; placed_at: string; total: string; status: string; payment_status?: string; fulfillment_status?: string }
interface FiscalSection {
    by_status: Record<string, number>;
    attention: number;
    unknown_submissions: number;
    configured: boolean;
    environment: string;
    production_enabled: boolean;
    rejected_documents: Array<{ id: string; number: string; order: string; customer: string | null; status: string; date: string | null }>;
}
interface ProfitabilitySection { fulfilled_revenue_ex_tax: string; cogs: string; gross_profit: string | null; gross_margin_percent: string | null; fulfilled_quantity: number; covered_quantity: number; cost_coverage_percent: string; status: 'COMPLETE' | 'INCOMPLETE' }

const props = defineProps<{
    dashboard: {
        range: RangeInfo;
        currency: string;
        sections: {
            sales?: SalesSection;
            payments?: PaymentSection;
            fulfillment?: FulfillmentSection;
            inventory?: InventorySection;
            procurement?: ProcurementSection;
            returns?: ReturnsSection;
            recent_orders?: RecentOrder[];
            fiscal?: FiscalSection;
            profitability?: ProfitabilitySection;
        };
        attention: AttentionItem[];
        comparison: {
            previous_range: RangeInfo;
            same_length: boolean;
            metrics: Record<string, MetricComparison>;
        };
    };
}>();

const page = usePage<PageProps>();
const permissions = computed(() => page.props.currentTenantMembership?.permissions ?? []);
const can = (permission: string) => permissions.value.includes(permission);
const { t, te } = useI18n();

const ranges = computed(() => [
    { value: 'today', label: t('dashboard.today') },
    { value: 'yesterday', label: t('dashboard.yesterday') },
    { value: '7d', label: t('dashboard.last7') },
    { value: '30d', label: t('dashboard.last30') },
    { value: 'this_month', label: t('dashboard.thisMonth') },
    { value: 'custom', label: t('dashboard.custom') },
]);

const rangeKey = ref(props.dashboard.range.key);
const from = ref(props.dashboard.range.local_start.slice(0, 10));
const to = ref(props.dashboard.range.local_end_inclusive);
const rangeSubmitting = ref(false);
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string>);

watch(() => props.dashboard.range, (value) => {
    rangeKey.value = value.key;
    from.value = value.local_start.slice(0, 10);
    to.value = value.local_end_inclusive;
});

const money = (value: string) => {
    const [whole = '0', fraction = ''] = value.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${props.dashboard.currency} ${grouped}.${fraction.padEnd(2, '0').slice(0, 2)}`;
};
const label = (value: string) => te(`status.${value}`) ? t(`status.${value}`) : t('common.unknownStatus');
const date = (value: string | null) => value ? value.slice(0, 10) : t('dashboard.notScheduled');
const rangeLabel = (key: string) => ranges.value.find((range) => range.value === key)?.label ?? key;
const attentionLabels: Record<string, string> = { 'unpaid-orders': 'pendingPayment', unfulfilled: 'openFulfillment', 'low-stock': 'stockAttention', 'purchase-orders': 'posReceive', returns: 'awaitingInspection', fiscal: 'fiscal' };
const attentionLabel = (item: AttentionItem) => attentionLabels[item.key] ? t(`dashboard.${attentionLabels[item.key]}`) : item.label;
const metric = (key: string) => props.dashboard.comparison?.metrics?.[key];

const changeRange = (event: Event) => {
    if (rangeSubmitting.value) return;
    const value = (event.target as HTMLSelectElement).value;
    rangeKey.value = value;
    if (value === 'custom') return;
    router.get('/admin/dashboard', { range: value }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { rangeSubmitting.value = true; },
        onFinish: () => { rangeSubmitting.value = false; },
    });
};
const applyCustom = () => {
    if (rangeSubmitting.value) return;
    router.get('/admin/dashboard', { range: 'custom', from: from.value, to: to.value }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { rangeSubmitting.value = true; },
        onFinish: () => { rangeSubmitting.value = false; },
    });
};
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout v-slot="{ currentStore }">
        <div class="mx-auto w-full max-w-[90rem] space-y-5 sm:space-y-6 lg:space-y-8">
            <Breadcrumbs :items="[{ label: t('dashboard.overview') }, { label: t('nav.dashboard') }]" />

            <PageHeader :title="t('nav.dashboard')" :eyebrow="t('dashboard.eyebrow', { store: currentStore?.name ?? t('dashboard.noStore') })" :description="t('dashboard.description', { range: rangeLabel(dashboard.range.key), timezone: dashboard.range.timezone, currency: dashboard.currency })">
                <template #actions>
                    <div class="grid gap-2 sm:min-w-56">
                        <label class="admin-label" for="dashboard-range">{{ t('dashboard.dateRange') }}</label>
                        <select id="dashboard-range" :value="rangeKey" :disabled="rangeSubmitting" :aria-invalid="Boolean(errors.range)" :aria-describedby="errors.range ? 'dashboard-range-error' : undefined" class="admin-control px-3 text-sm font-medium disabled:cursor-wait disabled:opacity-60" @change="changeRange">
                            <option v-for="range in ranges" :key="range.value" :value="range.value">{{ range.label }}</option>
                        </select>
                        <FieldError id="dashboard-range-error" :message="errors.range" />
                    </div>
                </template>
            </PageHeader>

            <div v-if="rangeKey === 'custom'" class="admin-card flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
                <div class="grid flex-1 gap-1.5 text-xs font-semibold"><label for="dashboard-from">{{ t('common.from') }}</label><input id="dashboard-from" v-model="from" type="date" :aria-invalid="Boolean(errors.from)" :aria-describedby="errors.from ? 'dashboard-from-error' : undefined" class="admin-control px-3 text-sm font-normal" /><FieldError id="dashboard-from-error" :message="errors.from" /></div>
                <div class="grid flex-1 gap-1.5 text-xs font-semibold"><label for="dashboard-to">{{ t('common.to') }}</label><input id="dashboard-to" v-model="to" type="date" :aria-invalid="Boolean(errors.to)" :aria-describedby="errors.to ? 'dashboard-to-error' : undefined" class="admin-control px-3 text-sm font-normal" /><FieldError id="dashboard-to-error" :message="errors.to" /></div>
                <button type="button" :disabled="rangeSubmitting" class="min-h-9 rounded-md bg-admin-primary px-4 text-sm font-semibold text-white hover:bg-admin-primary-hover disabled:cursor-wait disabled:opacity-60" @click="applyCustom">{{ t('common.applyRange') }}</button>
            </div>

            <p v-if="Object.keys(dashboard.comparison.metrics).length" class="rounded-md border border-admin-info-border bg-admin-info-bg px-3 py-2.5 text-xs leading-5 text-admin-text-secondary sm:px-4 sm:py-3">
                {{ t('dashboard.compared', { range: rangeLabel(dashboard.comparison.previous_range.key), from: dashboard.comparison.previous_range.local_start.slice(0, 10), to: dashboard.comparison.previous_range.local_end_inclusive }) }}
            </p>

            <section v-if="dashboard.attention.length" aria-labelledby="attention-heading">
                <div class="flex items-baseline justify-between gap-4"><h2 id="attention-heading" class="text-lg font-semibold">{{ t('dashboard.needsAttention') }}</h2><span class="text-xs text-admin-neutral">{{ t('dashboard.operationalState') }}</span></div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="item in dashboard.attention" :key="item.key" :href="item.href" class="admin-card group flex min-h-16 items-center justify-between gap-3 p-3 transition hover:border-admin-border-strong hover:bg-slate-50 sm:p-4">
                        <span><span class="block text-sm font-medium group-hover:text-admin-secondary">{{ attentionLabel(item) }}</span><span class="admin-label mt-1 block">{{ item.scope === 'tenant' ? t('dashboard.tenantScope') : t('common.currentStore') }}</span></span>
                        <strong class="font-mono text-2xl tabular-nums">{{ item.count }}</strong>
                    </Link>
                </div>
            </section>

            <template v-if="dashboard.sections.sales">
                <section aria-labelledby="sales-heading">
                    <div class="flex items-end justify-between gap-4"><div><p class="admin-label text-admin-secondary">{{ t('dashboard.storeScope') }}</p><h2 id="sales-heading" class="mt-1 text-lg font-semibold">{{ t('dashboard.salesPayments') }}</h2></div><span class="text-xs text-admin-neutral">{{ currentStore?.name }}</span></div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-lg border border-admin-primary bg-admin-primary p-3 text-white sm:p-4"><p class="admin-label text-white/60">{{ t('dashboard.grossSales') }}</p><p class="mt-2 font-mono text-2xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.sales.gross_sales) }}</p><MetricDelta inverse :metric="metric('gross_sales')" /></article>
                        <article class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.ordersPlaced') }}</p><p class="mt-2 font-mono text-2xl font-semibold tabular-nums sm:mt-3">{{ dashboard.sections.sales.orders }}</p><MetricDelta :metric="metric('orders')" /></article>
                        <article class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.averageOrder') }}</p><p class="mt-2 font-mono text-2xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.sales.average_order_value) }}</p><MetricDelta :metric="metric('average_order_value')" /></article>
                        <article class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.pendingOrders') }}</p><p class="mt-2 font-mono text-2xl font-semibold tabular-nums sm:mt-3">{{ dashboard.sections.sales.orders_by_status.PENDING ?? 0 }}</p><span class="mt-2 block text-[11px] text-admin-neutral">{{ t('dashboard.commercialStatus') }}</span></article>
                    </div>
                    <div v-if="dashboard.sections.payments" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.confirmedCollections') }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.payments.captured_amount) }}</p><MetricDelta :metric="metric('captured_amount')" /></article>
                        <article class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.netCollections') }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.payments.net_paid) }}</p><MetricDelta :metric="metric('net_paid')" /></article>
                        <article class="rounded-lg border border-admin-warning-border bg-admin-warning-bg p-3 sm:p-4"><p class="admin-label text-amber-700">{{ t('dashboard.pendingPayment') }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.payments.pending_amount) }}</p><span class="mt-2 block text-[11px] text-amber-700">{{ t('dashboard.pendingPaymentHelp') }}</span></article>
                        <article v-if="dashboard.sections.payments.refunded_amount !== undefined" class="admin-card p-3 sm:p-4"><p class="admin-label">{{ t('dashboard.refundedRange') }}</p><p class="mt-2 font-mono text-xl font-semibold tabular-nums sm:mt-3">{{ money(dashboard.sections.payments.refunded_amount) }}</p><MetricDelta :metric="metric('refunded_amount')" /></article>
                    </div>
                    <p v-if="dashboard.sections.sales.excluded_currency_orders" class="mt-3 rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3 text-xs text-amber-800">{{ t('dashboard.excludedCurrency', { count: dashboard.sections.sales.excluded_currency_orders }) }}</p>
                </section>

                <section class="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(18rem,1fr)]">
                    <article class="admin-card min-w-0 p-4 sm:p-5"><h2 class="text-base font-semibold">{{ t('dashboard.salesTrend') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.trendHelp') }}</p><div class="mt-4"><DashboardTrendChart :points="dashboard.sections.sales.trend" :currency="dashboard.currency" /></div></article>
                    <article class="admin-card p-4 sm:p-5"><h2 class="text-base font-semibold">{{ t('dashboard.ordersByStatus') }}</h2><dl class="mt-3 divide-y divide-admin-border"><div v-for="(count, status) in dashboard.sections.sales.orders_by_status" :key="status" class="flex items-center justify-between gap-4 py-3"><dt><StatusBadge :status="String(status)" /></dt><dd class="font-mono text-sm font-semibold tabular-nums">{{ count }}</dd></div></dl></article>
                </section>
            </template>

            <section v-if="dashboard.sections.profitability" aria-labelledby="profitability-heading">
                <p class="admin-label text-admin-secondary">{{ t('dashboard.fulfillmentRecognition') }}</p><h2 id="profitability-heading" class="mt-1 text-lg font-semibold">{{ t('dashboard.profitability') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="admin-card p-4"><p class="admin-label">{{ t('dashboard.cogs') }}</p><p class="mt-3 font-mono text-xl font-semibold">{{ money(dashboard.sections.profitability.cogs) }}</p></article>
                    <article class="rounded-lg border border-admin-primary bg-admin-primary p-4 text-white"><p class="admin-label text-white/60">{{ t('dashboard.grossProfit') }}</p><p class="mt-3 font-mono text-xl font-semibold">{{ dashboard.sections.profitability.gross_profit === null ? t('dashboard.incomplete') : money(dashboard.sections.profitability.gross_profit) }}</p></article>
                    <article class="admin-card p-4"><p class="admin-label">{{ t('dashboard.grossMargin') }}</p><p class="mt-3 font-mono text-xl font-semibold">{{ dashboard.sections.profitability.gross_margin_percent === null ? t('dashboard.incomplete') : `${dashboard.sections.profitability.gross_margin_percent}%` }}</p></article>
                    <article class="rounded-lg border p-4" :class="dashboard.sections.profitability.status === 'COMPLETE' ? 'border-admin-success-border bg-admin-success-bg' : 'border-admin-warning-border bg-admin-warning-bg'"><p class="admin-label">{{ t('dashboard.costCoverage') }}</p><p class="mt-3 font-mono text-xl font-semibold">{{ dashboard.sections.profitability.cost_coverage_percent }}%</p><p class="mt-1 text-xs text-admin-text-secondary">{{ t('dashboard.units', { covered: dashboard.sections.profitability.covered_quantity, total: dashboard.sections.profitability.fulfilled_quantity }) }}</p></article>
                </div>
                <p v-if="dashboard.sections.profitability.status === 'INCOMPLETE'" class="mt-3 rounded-md border border-admin-warning-border bg-admin-warning-bg px-4 py-3 text-xs text-amber-800">{{ t('dashboard.incompleteCosts') }}</p>
            </section>

            <section v-if="dashboard.sections.payments || dashboard.sections.fulfillment" class="grid gap-4 lg:grid-cols-2">
                <article v-if="dashboard.sections.payments" class="admin-card p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-3"><h2 class="text-base font-semibold">{{ t('dashboard.paymentRefunds') }}</h2><Link href="/admin/payments" class="admin-link text-xs">{{ t('dashboard.openPayments') }}</Link></div>
                    <div v-if="dashboard.sections.payments.refunded_amount !== undefined" class="mt-4 grid grid-cols-2 gap-3"><div><p class="admin-label">{{ t('dashboard.refundedRange') }}</p><p class="mt-1 font-mono text-base font-semibold">{{ money(dashboard.sections.payments.refunded_amount) }}</p></div><div><p class="admin-label">{{ t('dashboard.pendingRefunds') }}</p><p class="mt-1 font-mono text-base font-semibold">{{ dashboard.sections.payments.pending_refunds }}</p></div></div>
                    <div v-if="dashboard.sections.payments.refunds_by_status" class="mt-4"><p class="admin-label">{{ t('dashboard.refundStatus') }}</p><dl class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4"><div v-for="(count, status) in dashboard.sections.payments.refunds_by_status" :key="status" class="border-t border-admin-border pt-2"><dt class="text-xs text-admin-neutral">{{ label(status) }}</dt><dd class="font-mono text-sm font-semibold">{{ count }}</dd></div></dl></div>
                    <dl class="mt-4 grid gap-x-4 sm:grid-cols-2"><div v-for="(count, status) in dashboard.sections.payments.orders_by_payment_status" :key="status" class="flex justify-between border-t border-admin-border py-2 text-xs"><dt>{{ label(status) }}</dt><dd class="font-mono font-semibold">{{ count }}</dd></div></dl>
                </article>
                <article v-if="dashboard.sections.fulfillment" class="admin-card p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-3"><h2 class="text-base font-semibold">{{ t('nav.fulfillment') }}</h2><Link href="/admin/fulfillments" class="admin-link text-xs">{{ t('dashboard.openFulfillment') }}</Link></div>
                    <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3"><div v-for="(count, status) in dashboard.sections.fulfillment.orders_by_status" :key="status" class="rounded-md border border-admin-border p-3"><dt class="admin-label truncate">{{ label(status) }}</dt><dd class="mt-2 font-mono text-lg font-semibold">{{ count }}</dd></div></dl>
                    <p v-if="dashboard.sections.fulfillment.shipments_pending_or_ready !== undefined" class="mt-4 text-xs text-admin-text-secondary">{{ t('dashboard.shipmentSummary', { pending: dashboard.sections.fulfillment.shipments_pending_or_ready, shipped: dashboard.sections.fulfillment.shipped_not_delivered }) }}</p>
                </article>
            </section>

            <section v-if="dashboard.sections.recent_orders" aria-labelledby="recent-orders-heading">
                <div class="flex items-end justify-between gap-4"><div><p class="admin-label text-admin-secondary">{{ t('common.currentStore') }}</p><h2 id="recent-orders-heading" class="mt-1 text-lg font-semibold">{{ t('dashboard.recentOrders') }}</h2></div><Link v-if="can('orders.view')" href="/admin/orders" class="admin-link text-xs">{{ t('dashboard.openOrders') }}</Link></div>
                <p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.recentHelp', { currency: dashboard.currency }) }}</p>
                <div class="admin-card mt-3 hidden overflow-hidden md:block"><table class="admin-table w-full text-left text-xs"><thead><tr><th class="p-3">{{ t('dashboard.order') }}</th><th class="p-3">{{ t('dashboard.customer') }}</th><th class="p-3">{{ t('dashboard.placed') }}</th><th class="p-3 text-right">{{ t('dashboard.total') }}</th><th class="p-3">{{ t('common.status') }}</th><th v-if="dashboard.sections.payments" class="p-3">{{ t('dashboard.payment') }}</th><th v-if="dashboard.sections.fulfillment" class="p-3">{{ t('nav.fulfillment') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="order in dashboard.sections.recent_orders" :key="order.id"><td class="p-3"><Link v-if="can('orders.view')" :href="`/admin/orders/${order.id}`" class="admin-link">{{ order.number }}</Link><span v-else class="font-semibold">{{ order.number }}</span></td><td class="p-3">{{ order.customer || t('common.notAvailable') }}</td><td class="p-3 font-mono text-[11px]">{{ order.placed_at }}</td><td class="p-3 text-right font-mono">{{ money(order.total) }}</td><td class="p-3"><StatusBadge :status="order.status" /></td><td v-if="dashboard.sections.payments" class="p-3"><StatusBadge v-if="order.payment_status" :status="order.payment_status" /><span v-else>—</span></td><td v-if="dashboard.sections.fulfillment" class="p-3"><StatusBadge v-if="order.fulfillment_status" :status="order.fulfillment_status" /><span v-else>—</span></td></tr><tr v-if="!dashboard.sections.recent_orders.length"><td colspan="7" class="p-6 text-center text-admin-neutral">{{ t('dashboard.noOrders') }}</td></tr></tbody></table></div>
                <div class="mt-3 grid gap-2.5 md:hidden"><article v-for="order in dashboard.sections.recent_orders" :key="`mobile-${order.id}`" class="admin-card p-3.5"><div class="flex items-start justify-between gap-3"><div><Link v-if="can('orders.view')" :href="`/admin/orders/${order.id}`" class="admin-link text-sm">{{ order.number }}</Link><p class="mt-1 text-xs text-admin-neutral">{{ order.customer || t('common.notAvailable') }}</p></div><strong class="font-mono text-sm">{{ money(order.total) }}</strong></div><p class="mt-2.5 font-mono text-[10px] text-admin-neutral">{{ order.placed_at }}</p><div class="mt-2.5 flex flex-wrap gap-1.5"><StatusBadge :status="order.status" /><StatusBadge v-if="order.payment_status" :status="order.payment_status" /><StatusBadge v-if="order.fulfillment_status" :status="order.fulfillment_status" /></div></article><p v-if="!dashboard.sections.recent_orders.length" class="admin-card p-5 text-center text-sm text-admin-neutral">{{ t('dashboard.noOrders') }}</p></div>
            </section>

            <section v-if="dashboard.sections.inventory" aria-labelledby="inventory-heading">
                <div class="flex items-end justify-between gap-4"><div><p class="admin-label text-admin-secondary">{{ t('dashboard.inventoryScope') }}</p><h2 id="inventory-heading" class="mt-1 text-lg font-semibold">{{ t('dashboard.stockAttention') }}</h2></div><Link href="/admin/inventory" class="admin-link text-xs">{{ t('dashboard.openInventory') }}</Link></div>
                <p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.inventoryHelp', { threshold: dashboard.sections.inventory.threshold, count: dashboard.sections.inventory.out_of_stock_variants }) }}</p>
                <div class="admin-card mt-3 hidden overflow-hidden md:block"><table class="admin-table w-full text-left text-xs"><thead><tr><th class="p-3">{{ t('dashboard.productVariant') }}</th><th class="p-3">{{ t('dashboard.sku') }}</th><th class="p-3 text-right">{{ t('dashboard.available') }}</th><th class="p-3 text-right">{{ t('dashboard.onHand') }}</th><th class="p-3 text-right">{{ t('dashboard.reserved') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="item in dashboard.sections.inventory.items" :key="item.variant_id"><td class="p-3"><Link :href="`/admin/inventory/variants/${item.variant_id}`" class="admin-link">{{ item.product }}</Link><span class="block text-[11px] text-admin-neutral">{{ item.variant || t('dashboard.defaultVariant') }}</span></td><td class="p-3 font-mono">{{ item.sku }}</td><td class="p-3 text-right font-mono font-semibold">{{ item.available }}</td><td class="p-3 text-right font-mono">{{ item.on_hand }}</td><td class="p-3 text-right font-mono">{{ item.reserved }}</td></tr><tr v-if="!dashboard.sections.inventory.items.length"><td colspan="5" class="p-6 text-center text-admin-neutral">{{ t('dashboard.noLowStock') }}</td></tr></tbody></table></div>
                <div class="mt-3 grid gap-2.5 md:hidden"><article v-for="item in dashboard.sections.inventory.items" :key="`mobile-${item.variant_id}`" class="admin-card p-3.5"><Link :href="`/admin/inventory/variants/${item.variant_id}`" class="admin-link text-sm">{{ item.product }}</Link><p class="mt-1 text-xs text-admin-neutral">{{ item.variant || t('dashboard.defaultVariant') }} · <span class="font-mono">{{ item.sku }}</span></p><dl class="mt-2.5 grid grid-cols-3 gap-2 text-center"><div><dt class="admin-label">{{ t('dashboard.available') }}</dt><dd class="mt-1 font-mono font-semibold">{{ item.available }}</dd></div><div><dt class="admin-label">{{ t('dashboard.onHand') }}</dt><dd class="mt-1 font-mono">{{ item.on_hand }}</dd></div><div><dt class="admin-label">{{ t('dashboard.reserved') }}</dt><dd class="mt-1 font-mono">{{ item.reserved }}</dd></div></dl></article><p v-if="!dashboard.sections.inventory.items.length" class="admin-card p-5 text-center text-sm text-admin-neutral">{{ t('dashboard.noLowStock') }}</p></div>
            </section>

            <section v-if="dashboard.sections.procurement" aria-labelledby="procurement-heading">
                <div class="flex items-end justify-between gap-4"><div><p class="admin-label text-admin-secondary">{{ t('dashboard.procurementScope') }}</p><h2 id="procurement-heading" class="mt-1 text-lg font-semibold">{{ t('dashboard.incomingStock') }}</h2></div><Link href="/admin/purchase-orders" class="admin-link text-xs">{{ t('dashboard.openPurchaseOrders') }}</Link></div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3"><div class="admin-card p-4"><span class="admin-label">{{ t('dashboard.posReceive') }}</span><strong class="mt-2 block font-mono text-xl">{{ dashboard.sections.procurement.to_receive }}</strong></div><div class="admin-card p-4"><span class="admin-label">{{ t('dashboard.incomingUnits') }}</span><strong class="mt-2 block font-mono text-xl">{{ dashboard.sections.procurement.incoming_units }}</strong></div><div class="admin-card p-4"><span class="admin-label">{{ t('dashboard.overdue') }}</span><strong class="mt-2 block font-mono text-xl">{{ dashboard.sections.procurement.overdue }}</strong></div></div>
                <div class="admin-card mt-3 hidden overflow-hidden md:block"><table class="admin-table w-full text-left text-xs"><thead><tr><th class="p-3">{{ t('dashboard.po') }}</th><th class="p-3">{{ t('dashboard.supplier') }}</th><th class="p-3">{{ t('dashboard.expected') }}</th><th class="p-3">{{ t('common.status') }}</th><th class="p-3 text-right">{{ t('dashboard.remaining') }}</th></tr></thead><tbody class="divide-y divide-admin-border"><tr v-for="purchaseOrder in dashboard.sections.procurement.purchase_orders" :key="purchaseOrder.id"><td class="p-3"><Link :href="`/admin/purchase-orders/${purchaseOrder.id}`" class="admin-link">{{ purchaseOrder.number }}</Link></td><td class="p-3">{{ purchaseOrder.supplier }}</td><td class="p-3">{{ date(purchaseOrder.expected_at) }}</td><td class="p-3"><StatusBadge :status="purchaseOrder.status" /></td><td class="p-3 text-right font-mono font-semibold">{{ purchaseOrder.remaining_quantity }}</td></tr></tbody></table></div>
                <div class="mt-3 grid gap-2.5 md:hidden"><article v-for="purchaseOrder in dashboard.sections.procurement.purchase_orders" :key="`mobile-${purchaseOrder.id}`" class="admin-card p-3.5"><div class="flex justify-between gap-3"><Link :href="`/admin/purchase-orders/${purchaseOrder.id}`" class="admin-link text-sm">{{ purchaseOrder.number }}</Link><StatusBadge :status="purchaseOrder.status" /></div><p class="mt-2 text-xs text-admin-text-secondary">{{ purchaseOrder.supplier }}</p><p class="mt-2.5 text-xs text-admin-neutral">{{ t('dashboard.expectedRemaining', { date: date(purchaseOrder.expected_at), count: purchaseOrder.remaining_quantity }) }}</p></article></div>
            </section>

            <section v-if="dashboard.sections.returns || dashboard.sections.fiscal" class="grid gap-4 lg:grid-cols-2">
                <article v-if="dashboard.sections.returns" class="admin-card p-4 sm:p-5"><div class="flex justify-between gap-3"><h2 class="text-base font-semibold">{{ t('nav.returns') }}</h2><Link href="/admin/returns" class="admin-link text-xs">{{ t('dashboard.openReturns') }}</Link></div><dl class="mt-4 grid grid-cols-3 gap-3"><div><dt class="admin-label">{{ t('dashboard.open') }}</dt><dd class="mt-1 font-mono text-xl font-semibold">{{ dashboard.sections.returns.open }}</dd></div><div><dt class="admin-label">{{ t('dashboard.awaitingInspection') }}</dt><dd class="mt-1 font-mono text-xl font-semibold">{{ dashboard.sections.returns.awaiting_inspection }}</dd></div><div><dt class="admin-label">{{ t('dashboard.restockPending') }}</dt><dd class="mt-1 font-mono text-xl font-semibold">{{ dashboard.sections.returns.restock_pending }}</dd></div></dl><p class="admin-label mt-4">{{ t('dashboard.requestsByStatus') }}</p><dl class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4"><div v-for="(count, status) in dashboard.sections.returns.by_status" :key="status" class="border-t border-admin-border pt-2"><dt class="text-xs text-admin-neutral">{{ label(status) }}</dt><dd class="font-mono text-sm font-semibold">{{ count }}</dd></div></dl><p v-if="dashboard.sections.returns.pending_exchanges !== undefined" class="mt-4 text-xs text-admin-text-secondary">{{ t('dashboard.pendingExchanges', { count: dashboard.sections.returns.pending_exchanges }) }}</p></article>
                <article v-if="dashboard.sections.fiscal" class="admin-card p-4 sm:p-5"><div class="flex justify-between gap-3"><div><h2 class="text-base font-semibold">{{ t('dashboard.fiscal') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.environment', { environment: dashboard.sections.fiscal.environment }) }}</p></div><Link href="/admin/fiscal-documents" class="admin-link text-xs">{{ t('dashboard.openDocuments') }}</Link></div><p v-if="!dashboard.sections.fiscal.configured" class="mt-4 rounded-md border border-admin-warning-border bg-admin-warning-bg p-3 text-xs text-amber-800">{{ t('dashboard.fiscalMissing') }}</p><p v-else-if="dashboard.sections.fiscal.environment === 'BETA'" class="mt-4 rounded-md border border-admin-info-border bg-admin-info-bg p-3 text-xs text-sky-800">{{ t('dashboard.beta') }}</p><dl class="mt-4 grid grid-cols-2 gap-x-4"><div v-for="(count, status) in dashboard.sections.fiscal.by_status" :key="status" class="flex justify-between border-t border-admin-border py-2 text-xs"><dt>{{ label(status) }}</dt><dd class="font-mono font-semibold">{{ count }}</dd></div></dl><div v-if="dashboard.sections.fiscal.rejected_documents.length" class="mt-4 grid gap-2"><p class="admin-label">{{ t('dashboard.rejectedDocuments') }}</p><Link v-for="document in dashboard.sections.fiscal.rejected_documents" :key="document.id" :href="`/admin/fiscal-documents/${document.id}`" class="rounded-md border border-admin-danger-border bg-admin-danger-bg p-3 text-xs"><span class="font-mono font-semibold text-admin-danger">{{ document.number }}</span><span class="mt-1 block text-admin-text-secondary">{{ document.order }} · {{ document.customer || t('common.notAvailable') }} · {{ date(document.date) }}</span></Link></div></article>
            </section>

            <section v-if="dashboard.sections.sales?.top_products.length">
                <h2 class="text-lg font-semibold">{{ t('dashboard.topProducts') }}</h2><p class="mt-1 text-xs text-admin-neutral">{{ t('dashboard.topHelp') }}</p>
                <div class="admin-card mt-3 overflow-hidden"><div v-for="product in dashboard.sections.sales.top_products" :key="`${product.product_id}-${product.product}`" class="flex items-center justify-between gap-4 border-b border-admin-border p-3 last:border-b-0 sm:p-4"><div class="min-w-0"><Link v-if="product.product_id && can('products.view')" :href="`/admin/products/${product.product_id}/edit`" class="admin-link block truncate text-sm">{{ product.product }}</Link><span v-else class="block truncate text-sm font-semibold">{{ product.product }}</span><span class="text-xs text-admin-neutral">{{ t('dashboard.productUnits', { count: product.units }) }}</span></div><strong class="shrink-0 font-mono text-sm">{{ money(product.revenue) }}</strong></div></div>
            </section>

            <section v-if="!Object.keys(dashboard.sections).length" class="admin-card p-8 text-center"><h2 class="text-lg font-semibold">{{ t('dashboard.noWidgets') }}</h2><p class="mt-2 text-sm text-admin-neutral">{{ t('dashboard.noWidgetsHelp') }}</p></section>
        </div>
    </AdminLayout>
</template>
