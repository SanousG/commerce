<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import DashboardTrendChart from '../../components/DashboardTrendChart.vue';
import MetricDelta from '../../components/MetricDelta.vue';
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

const ranges = [
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: '7d', label: 'Last 7 days' },
    { value: '30d', label: 'Last 30 days' },
    { value: 'this_month', label: 'This month' },
    { value: 'custom', label: 'Custom range' },
];

const rangeKey = ref(props.dashboard.range.key);
const from = ref(props.dashboard.range.local_start.slice(0, 10));
const to = ref(props.dashboard.range.local_end_inclusive);

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
const label = (value: string) => value.toLowerCase().replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
const date = (value: string | null) => value ? value.slice(0, 10) : 'Not scheduled';
const metric = (key: string) => props.dashboard.comparison?.metrics?.[key];

const changeRange = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    rangeKey.value = value;
    if (value === 'custom') return;
    router.get('/admin/dashboard', { range: value }, { preserveState: true, preserveScroll: true });
};
const applyCustom = () => router.get('/admin/dashboard', { range: 'custom', from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Operational dashboard" />

    <AdminLayout v-slot="{ currentStore }">
        <div class="mx-auto max-w-[94rem] space-y-10">
            <header class="flex flex-col gap-5 border-b border-[#17211b]/15 pb-7 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Operations / {{ currentStore?.name }}</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Dashboard</h1>
                    <p class="mt-3 text-sm text-[#637067]">{{ dashboard.range.label }} in {{ dashboard.range.timezone }}. Store metrics use {{ dashboard.currency }}.</p>
                </div>
                <div class="grid gap-2">
                    <label class="grid gap-1.5 text-xs font-semibold tracking-[0.12em] uppercase">
                        Date range
                        <select :value="rangeKey" class="min-w-52 border border-[#17211b]/25 bg-[#f8f5ed] px-4 py-3 text-sm font-medium tracking-normal normal-case" @change="changeRange">
                            <option v-for="range in ranges" :key="range.value" :value="range.value">{{ range.label }}</option>
                        </select>
                    </label>
                    <div v-if="rangeKey === 'custom'" class="flex flex-wrap items-end gap-2">
                        <label class="grid gap-1 text-[10px] font-semibold tracking-[0.12em] uppercase">From<input v-model="from" type="date" class="border border-[#17211b]/25 bg-[#f8f5ed] px-3 py-2 text-sm" /></label>
                        <label class="grid gap-1 text-[10px] font-semibold tracking-[0.12em] uppercase">To<input v-model="to" type="date" class="border border-[#17211b]/25 bg-[#f8f5ed] px-3 py-2 text-sm" /></label>
                        <button type="button" class="border border-[#17211b] bg-[#17211b] px-4 py-2 text-sm font-semibold text-white" @click="applyCustom">Apply</button>
                    </div>
                </div>
            </header>

            <p v-if="Object.keys(dashboard.comparison.metrics).length" class="border-l-2 border-[#17211b]/30 pl-3 text-sm text-[#637067]">
                Compared with the equal-length previous window {{ dashboard.comparison.previous_range.label }} ({{ dashboard.comparison.previous_range.local_start.slice(0, 10) }} to {{ dashboard.comparison.previous_range.local_end_inclusive }}).
            </p>

            <section v-if="dashboard.attention.length" aria-labelledby="attention-heading">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="attention-heading" class="text-2xl font-semibold tracking-tight">Needs attention</h2>
                    <span class="text-xs text-[#637067]">Current operational state</span>
                </div>
                <div class="mt-4 grid gap-px overflow-hidden border border-[#17211b]/15 bg-[#17211b]/15 sm:grid-cols-2 xl:grid-cols-6">
                    <Link v-for="item in dashboard.attention" :key="item.key" :href="item.href" class="group bg-[#f8f5ed] p-5 transition hover:bg-white">
                        <span class="text-3xl font-semibold tabular-nums">{{ item.count }}</span>
                        <span class="mt-2 block text-sm font-medium group-hover:text-emerald-800">{{ item.label }}</span>
                        <span class="mt-1 block text-[10px] tracking-[0.14em] text-[#637067] uppercase">{{ item.scope === 'tenant' ? 'All company' : 'Current store' }}</span>
                    </Link>
                </div>
            </section>

            <template v-if="dashboard.sections.sales">
                <section aria-labelledby="sales-heading">
                    <h2 id="sales-heading" class="text-2xl font-semibold tracking-tight">Sales</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="border border-[#17211b]/15 bg-[#17211b] p-5 text-white">
                            <p class="text-xs tracking-[0.14em] text-white/55 uppercase">Order value</p>
                            <p class="mt-4 text-2xl font-semibold">{{ money(dashboard.sections.sales.gross_sales) }}</p>
                            <MetricDelta inverse :metric="metric('gross_sales')" />
                        </article>
                        <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Orders placed</p>
                            <p class="mt-4 text-2xl font-semibold">{{ dashboard.sections.sales.orders }}</p>
                            <MetricDelta :metric="metric('orders')" />
                        </article>
                        <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Average order value</p>
                            <p class="mt-4 text-2xl font-semibold">{{ money(dashboard.sections.sales.average_order_value) }}</p>
                            <MetricDelta :metric="metric('average_order_value')" />
                        </article>
                        <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Pending orders</p>
                            <p class="mt-4 text-2xl font-semibold">{{ dashboard.sections.sales.orders_by_status.PENDING ?? 0 }}</p>
                            <span class="mt-2 block text-[11px] text-[#637067]">In range · commercial status</span>
                        </article>
                    </div>

                    <div v-if="dashboard.sections.payments" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Confirmed collections</p>
                            <p class="mt-4 text-2xl font-semibold">{{ money(dashboard.sections.payments.captured_amount) }}</p>
                            <MetricDelta :metric="metric('captured_amount')" />
                        </article>
                        <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Net collections</p>
                            <p class="mt-4 text-2xl font-semibold">{{ money(dashboard.sections.payments.net_paid) }}</p>
                            <MetricDelta :metric="metric('net_paid')" />
                        </article>
                        <article class="border border-amber-800/30 bg-amber-50 p-5">
                            <p class="text-xs tracking-[0.14em] text-amber-900/70 uppercase">Pending payment</p>
                            <p class="mt-4 text-2xl font-semibold text-amber-950">{{ money(dashboard.sections.payments.pending_amount) }}</p>
                            <span class="mt-2 block text-[11px] text-amber-900/70">Grand total minus posted captures</span>
                        </article>
                        <article v-if="dashboard.sections.payments.refunded_amount !== undefined" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5">
                            <p class="text-xs tracking-[0.14em] text-[#637067] uppercase">Refunded in range</p>
                            <p class="mt-4 text-2xl font-semibold">{{ money(dashboard.sections.payments.refunded_amount) }}</p>
                            <MetricDelta :metric="metric('refunded_amount')" />
                        </article>
                    </div>

                    <p v-if="dashboard.sections.sales.excluded_currency_orders" class="mt-3 border-l-2 border-amber-600 pl-3 text-sm text-amber-900">{{ dashboard.sections.sales.excluded_currency_orders }} order(s) in another currency were excluded. No FX conversion was applied.</p>
                </section>

                <section class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(18rem,1fr)]">
                    <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                        <h2 class="text-xl font-semibold">Sales trend</h2>
                        <p class="mt-1 text-sm text-[#637067]">Order value and order count per store-local day. Missing days show zero.</p>
                        <div class="mt-5">
                            <DashboardTrendChart :points="dashboard.sections.sales.trend" :currency="dashboard.currency" />
                        </div>
                    </article>
                    <article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                        <h2 class="text-xl font-semibold">Orders by status</h2>
                        <dl class="mt-5 divide-y divide-[#17211b]/10">
                            <div v-for="(count, status) in dashboard.sections.sales.orders_by_status" :key="status" class="flex justify-between gap-4 py-3"><dt class="text-sm">{{ label(status) }}</dt><dd class="font-semibold tabular-nums">{{ count }}</dd></div>
                        </dl>
                    </article>
                </section>
            </template>

            <section v-if="dashboard.sections.profitability" aria-labelledby="profitability-heading" class="border-t border-[#17211b]/15 pt-8"><div><p class="text-xs tracking-[0.14em] text-emerald-800 uppercase">Fulfillment-date recognition</p><h2 id="profitability-heading" class="mt-1 text-2xl font-semibold">Gross profitability</h2></div><div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs text-[#637067] uppercase">COGS</p><p class="mt-3 text-2xl font-semibold">{{ money(dashboard.sections.profitability.cogs) }}</p></article><article class="border border-[#17211b]/15 bg-[#17211b] p-5 text-white"><p class="text-xs text-white/55 uppercase">Gross profit</p><p class="mt-3 text-2xl font-semibold">{{ dashboard.sections.profitability.gross_profit === null ? 'Incomplete' : money(dashboard.sections.profitability.gross_profit) }}</p></article><article class="border border-[#17211b]/15 bg-[#f8f5ed] p-5"><p class="text-xs text-[#637067] uppercase">Gross margin</p><p class="mt-3 text-2xl font-semibold">{{ dashboard.sections.profitability.gross_margin_percent === null ? 'Incomplete' : `${dashboard.sections.profitability.gross_margin_percent}%` }}</p></article><article class="border p-5" :class="dashboard.sections.profitability.status === 'COMPLETE' ? 'border-emerald-700 bg-emerald-50' : 'border-amber-700 bg-amber-50'"><p class="text-xs uppercase">Cost coverage</p><p class="mt-3 text-2xl font-semibold">{{ dashboard.sections.profitability.cost_coverage_percent }}%</p><p class="mt-1 text-xs">{{ dashboard.sections.profitability.covered_quantity }} / {{ dashboard.sections.profitability.fulfilled_quantity }} units</p></article></div><p v-if="dashboard.sections.profitability.status === 'INCOMPLETE'" class="mt-3 border-l-2 border-amber-600 pl-3 text-sm text-amber-900">Gross profit and margin are withheld because legacy or currency-mismatched fulfillment costs exist in this range.</p></section>

            <section v-if="dashboard.sections.payments || dashboard.sections.fulfillment" class="grid gap-6 lg:grid-cols-2">
                <article v-if="dashboard.sections.payments" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                    <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Payments &amp; refunds</h2><Link href="/admin/payments" class="text-sm font-semibold text-emerald-800">Open payments</Link></div>
                    <div v-if="dashboard.sections.payments.refunded_amount !== undefined" class="mt-5 grid grid-cols-2 gap-4"><div><p class="text-xs text-[#637067] uppercase">Refunded in range</p><p class="mt-1 text-lg font-semibold">{{ money(dashboard.sections.payments.refunded_amount) }}</p></div><div><p class="text-xs text-[#637067] uppercase">Pending refunds</p><p class="mt-1 text-lg font-semibold">{{ dashboard.sections.payments.pending_refunds }}</p></div></div>
                    <div v-if="dashboard.sections.payments.refunds_by_status" class="mt-4">
                        <p class="text-[10px] tracking-[0.14em] text-[#637067] uppercase">Refund requests by status (current)</p>
                        <dl class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4"><div v-for="(count, status) in dashboard.sections.payments.refunds_by_status" :key="status" class="border-t border-[#17211b]/10 pt-2"><dt class="text-[#637067]">{{ label(status) }}</dt><dd class="font-semibold tabular-nums">{{ count }}</dd></div></dl>
                    </div>
                    <dl class="mt-5 grid gap-2 sm:grid-cols-2"><div v-for="(count, status) in dashboard.sections.payments.orders_by_payment_status" :key="status" class="flex justify-between border-t border-[#17211b]/10 py-2 text-sm"><dt>{{ label(status) }}</dt><dd class="font-semibold">{{ count }}</dd></div></dl>
                </article>
                <article v-if="dashboard.sections.fulfillment" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                    <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">Fulfillment</h2><Link href="/admin/fulfillments" class="text-sm font-semibold text-emerald-800">Open fulfillment</Link></div>
                    <dl class="mt-5 grid grid-cols-3 gap-3 text-center"><div v-for="(count, status) in dashboard.sections.fulfillment.orders_by_status" :key="status" class="border border-[#17211b]/10 p-3"><dt class="text-[10px] text-[#637067] uppercase">{{ label(status) }}</dt><dd class="mt-2 text-xl font-semibold">{{ count }}</dd></div></dl>
                    <p v-if="dashboard.sections.fulfillment.shipments_pending_or_ready !== undefined" class="mt-5 text-sm">{{ dashboard.sections.fulfillment.shipments_pending_or_ready }} shipments pending or ready; {{ dashboard.sections.fulfillment.shipped_not_delivered }} shipped and not delivered.</p>
                </article>
            </section>

            <section v-if="dashboard.sections.recent_orders" aria-labelledby="recent-orders-heading" class="border-t border-[#17211b]/15 pt-8">
                <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs tracking-[0.14em] text-emerald-800 uppercase">Current store</p><h2 id="recent-orders-heading" class="mt-1 text-2xl font-semibold">Recent orders</h2></div><Link v-if="can('orders.view')" href="/admin/orders" class="text-sm font-semibold text-emerald-800">Open orders</Link></div>
                <p class="mt-2 text-sm text-[#637067]">Latest orders placed in the selected range ({{ dashboard.currency }}).</p>
                <div class="mt-5 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]">
                    <table class="w-full min-w-[52rem] text-left text-sm">
                        <thead class="border-b border-[#17211b]/15 text-xs tracking-wide text-[#637067] uppercase">
                            <tr><th class="p-4">Order</th><th class="p-4">Customer</th><th class="p-4">Placed</th><th class="p-4 text-right">Total</th><th class="p-4">Status</th><th v-if="dashboard.sections.payments" class="p-4">Payment</th><th v-if="dashboard.sections.fulfillment" class="p-4">Fulfillment</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[#17211b]/10">
                            <tr v-for="order in dashboard.sections.recent_orders" :key="order.id">
                                <td class="p-4"><Link v-if="can('orders.view')" :href="`/admin/orders/${order.id}`" class="font-semibold text-emerald-800">{{ order.number }}</Link><span v-else class="font-semibold">{{ order.number }}</span></td>
                                <td class="p-4">{{ order.customer || 'Not available' }}</td>
                                <td class="p-4 font-mono text-xs">{{ order.placed_at }}</td>
                                <td class="p-4 text-right tabular-nums">{{ money(order.total) }}</td>
                                <td class="p-4">{{ label(order.status) }}</td>
                                <td v-if="dashboard.sections.payments" class="p-4">{{ order.payment_status ? label(order.payment_status) : '—' }}</td>
                                <td v-if="dashboard.sections.fulfillment" class="p-4">{{ order.fulfillment_status ? label(order.fulfillment_status) : '—' }}</td>
                            </tr>
                            <tr v-if="!dashboard.sections.recent_orders.length"><td colspan="7" class="p-6 text-center text-[#637067]">No orders in this range.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="dashboard.sections.inventory" aria-labelledby="inventory-heading" class="border-t border-[#17211b]/15 pt-8">
                <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs tracking-[0.14em] text-emerald-800 uppercase">All company inventory</p><h2 id="inventory-heading" class="mt-1 text-2xl font-semibold">Stock attention</h2></div><Link href="/admin/inventory" class="text-sm font-semibold text-emerald-800">Open inventory</Link></div>
                <p class="mt-2 text-sm text-[#637067]">Active locations only. Low stock means 0 &lt; available ≤ {{ dashboard.sections.inventory.threshold }} per variant. {{ dashboard.sections.inventory.out_of_stock_variants }} variant(s) out of stock.</p>
                <div class="mt-5 overflow-x-auto border border-[#17211b]/15 bg-[#f8f5ed]"><table class="w-full min-w-[44rem] text-left text-sm"><thead class="border-b border-[#17211b]/15 text-xs tracking-wide text-[#637067] uppercase"><tr><th class="p-4">Product / variant</th><th class="p-4">SKU</th><th class="p-4 text-right">Available</th><th class="p-4 text-right">On hand</th><th class="p-4 text-right">Reserved</th></tr></thead><tbody class="divide-y divide-[#17211b]/10"><tr v-for="item in dashboard.sections.inventory.items" :key="item.variant_id"><td class="p-4"><Link :href="`/admin/inventory/variants/${item.variant_id}`" class="font-semibold hover:text-emerald-800">{{ item.product }}</Link><span class="block text-xs text-[#637067]">{{ item.variant || 'Default variant' }}</span></td><td class="p-4 font-mono text-xs">{{ item.sku }}</td><td class="p-4 text-right font-semibold">{{ item.available }}</td><td class="p-4 text-right">{{ item.on_hand }}</td><td class="p-4 text-right">{{ item.reserved }}</td></tr><tr v-if="!dashboard.sections.inventory.items.length"><td colspan="5" class="p-6 text-center text-[#637067]">No low-stock variants.</td></tr></tbody></table></div>
            </section>

            <section v-if="dashboard.sections.procurement" aria-labelledby="procurement-heading" class="border-t border-[#17211b]/15 pt-8">
                <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs tracking-[0.14em] text-emerald-800 uppercase">All company procurement</p><h2 id="procurement-heading" class="mt-1 text-2xl font-semibold">Incoming stock</h2></div><Link href="/admin/purchase-orders" class="text-sm font-semibold text-emerald-800">Open purchase orders</Link></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-3"><div class="border border-[#17211b]/15 bg-[#f8f5ed] p-4"><span class="text-xs text-[#637067] uppercase">POs to receive</span><strong class="mt-2 block text-2xl">{{ dashboard.sections.procurement.to_receive }}</strong></div><div class="border border-[#17211b]/15 bg-[#f8f5ed] p-4"><span class="text-xs text-[#637067] uppercase">Incoming units</span><strong class="mt-2 block text-2xl">{{ dashboard.sections.procurement.incoming_units }}</strong></div><div class="border border-[#17211b]/15 bg-[#f8f5ed] p-4"><span class="text-xs text-[#637067] uppercase">Overdue</span><strong class="mt-2 block text-2xl">{{ dashboard.sections.procurement.overdue }}</strong></div></div>
                <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[40rem] text-left text-sm"><thead><tr class="border-b border-[#17211b]/15 text-xs text-[#637067] uppercase"><th class="py-3">PO</th><th>Supplier</th><th>Expected</th><th>Status</th><th class="text-right">Remaining</th></tr></thead><tbody><tr v-for="purchaseOrder in dashboard.sections.procurement.purchase_orders" :key="purchaseOrder.id" class="border-b border-[#17211b]/10"><td class="py-3"><Link :href="`/admin/purchase-orders/${purchaseOrder.id}`" class="font-semibold text-emerald-800">{{ purchaseOrder.number }}</Link></td><td>{{ purchaseOrder.supplier }}</td><td>{{ date(purchaseOrder.expected_at) }}</td><td>{{ label(purchaseOrder.status) }}</td><td class="text-right font-semibold">{{ purchaseOrder.remaining_quantity }}</td></tr></tbody></table></div>
            </section>

            <section v-if="dashboard.sections.returns || dashboard.sections.fiscal" class="grid gap-6 border-t border-[#17211b]/15 pt-8 lg:grid-cols-2">
                <article v-if="dashboard.sections.returns" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6">
                    <div class="flex justify-between gap-4"><h2 class="text-xl font-semibold">Returns</h2><Link href="/admin/returns" class="text-sm font-semibold text-emerald-800">Open returns</Link></div>
                    <dl class="mt-5 grid grid-cols-3 gap-4"><div><dt class="text-xs text-[#637067] uppercase">Open</dt><dd class="mt-1 text-2xl font-semibold">{{ dashboard.sections.returns.open }}</dd></div><div><dt class="text-xs text-[#637067] uppercase">Awaiting inspection</dt><dd class="mt-1 text-2xl font-semibold">{{ dashboard.sections.returns.awaiting_inspection }}</dd></div><div><dt class="text-xs text-[#637067] uppercase">Restock pending</dt><dd class="mt-1 text-2xl font-semibold">{{ dashboard.sections.returns.restock_pending }}</dd></div></dl>
                    <p class="mt-4 text-[10px] tracking-[0.14em] text-[#637067] uppercase">Requests by status (current)</p>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4"><div v-for="(count, status) in dashboard.sections.returns.by_status" :key="status" class="border-t border-[#17211b]/10 pt-2"><dt class="text-[#637067]">{{ label(status) }}</dt><dd class="font-semibold tabular-nums">{{ count }}</dd></div></dl>
                    <p v-if="dashboard.sections.returns.pending_exchanges !== undefined" class="mt-4 text-sm">{{ dashboard.sections.returns.pending_exchanges }} exchange(s) pending or approved.</p>
                </article>
                <article v-if="dashboard.sections.fiscal" class="border border-[#17211b]/15 bg-[#f8f5ed] p-5 sm:p-6"><div class="flex justify-between gap-4"><div><h2 class="text-xl font-semibold">Fiscal / SUNAT</h2><p class="mt-1 text-xs text-[#637067]">Environment: {{ dashboard.sections.fiscal.environment }}</p></div><Link href="/admin/fiscal-documents" class="text-sm font-semibold text-emerald-800">Open documents</Link></div><p v-if="!dashboard.sections.fiscal.configured" class="mt-4 border-l-2 border-amber-600 pl-3 text-sm text-amber-900">Fiscal issuer or active series is not configured for this store.</p><p v-else-if="dashboard.sections.fiscal.environment === 'BETA'" class="mt-4 border-l-2 border-sky-700 pl-3 text-sm text-sky-900">SUNAT is running in Beta, not production.</p><dl class="mt-5 grid grid-cols-2 gap-2 text-sm"><div v-for="(count, status) in dashboard.sections.fiscal.by_status" :key="status" class="flex justify-between border-t border-[#17211b]/10 py-2"><dt>{{ label(status) }}</dt><dd class="font-semibold">{{ count }}</dd></div></dl><div v-if="dashboard.sections.fiscal.rejected_documents.length" class="mt-5 overflow-x-auto"><table class="w-full min-w-[32rem] text-left text-xs"><caption class="pb-2 text-left font-semibold">Recent rejected documents</caption><thead><tr class="border-b border-[#17211b]/15 text-[#637067] uppercase"><th class="py-2">Number</th><th>Order</th><th>Customer</th><th>Status</th><th>Date</th></tr></thead><tbody><tr v-for="document in dashboard.sections.fiscal.rejected_documents" :key="document.id" class="border-b border-[#17211b]/10"><td class="py-2"><Link :href="`/admin/fiscal-documents/${document.id}`" class="font-semibold text-emerald-800">{{ document.number }}</Link></td><td>{{ document.order }}</td><td>{{ document.customer || 'Not available' }}</td><td>{{ document.status }}</td><td>{{ date(document.date) }}</td></tr></tbody></table></div></article>
            </section>

            <section v-if="dashboard.sections.sales?.top_products.length" class="border-t border-[#17211b]/15 pt-8">
                <h2 class="text-2xl font-semibold">Top products</h2><p class="mt-1 text-sm text-[#637067]">Historical order-item snapshots; cancelled orders excluded; refunds not netted.</p>
                <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[34rem] text-left text-sm"><thead><tr class="border-b border-[#17211b]/15 text-xs text-[#637067] uppercase"><th class="py-3">Product snapshot</th><th class="text-right">Units</th><th class="text-right">Revenue</th></tr></thead><tbody><tr v-for="product in dashboard.sections.sales.top_products" :key="`${product.product_id}-${product.product}`" class="border-b border-[#17211b]/10"><td class="py-3 font-semibold"><Link v-if="product.product_id && can('products.view')" :href="`/admin/products/${product.product_id}`" class="hover:text-emerald-800">{{ product.product }}</Link><span v-else>{{ product.product }}</span></td><td class="text-right">{{ product.units }}</td><td class="text-right">{{ money(product.revenue) }}</td></tr></tbody></table></div>
            </section>

            <section v-if="!Object.keys(dashboard.sections).length" class="border border-[#17211b]/15 bg-[#f8f5ed] p-8 text-center"><h2 class="text-xl font-semibold">No dashboard widgets available</h2><p class="mt-2 text-sm text-[#637067]">Your role does not include access to operational datasets.</p></section>
        </div>
    </AdminLayout>
</template>
