<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface OrderRow { id: string; order_number: string; customer_name: string; email: string | null; status: string; payment_status: string; fulfillment_status: string; currency: string; grand_total: string; placed_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface OrderFilters { search: string; status: string; payment_status: string; fulfillment_status: string; customer_id: string; from: string; to: string }

const props = defineProps<{
    orders: { data: OrderRow[]; links: PageLink[] };
    filters: OrderFilters;
    statuses: string[];
    paymentStatuses: string[];
    fulfillmentStatuses: string[];
}>();

const filters = ref<OrderFilters>({ ...props.filters });
const applyFilters = () => router.get('/admin/orders', { ...filters.value }, { preserveState: true, replace: true });
const clearFilters = () => {
    filters.value = { search: '', status: '', payment_status: '', fulfillment_status: '', customer_id: '', from: '', to: '' };
    applyFilters();
};
</script>

<template>
    <Head title="Orders" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Commercial record</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Orders</h1>
            <form class="mt-8 grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <label class="grid gap-1.5 text-xs font-semibold uppercase sm:col-span-2">Search<input v-model="filters.search" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case outline-none focus:border-emerald-700" placeholder="Number, customer or email" /></label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">Order status<select v-model="filters.status" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case"><option value="">All order states</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">Payment<select v-model="filters.payment_status" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case"><option value="">All payment states</option><option v-for="value in paymentStatuses" :key="value" :value="value">{{ value }}</option></select></label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">Fulfillment<select v-model="filters.fulfillment_status" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case"><option value="">All fulfillment states</option><option v-for="value in fulfillmentStatuses" :key="value" :value="value">{{ value }}</option></select></label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">Placed from<input v-model="filters.from" type="date" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case" /></label>
                <label class="grid gap-1.5 text-xs font-semibold uppercase">Placed to<input v-model="filters.to" type="date" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case" /></label>
                <div class="flex items-end gap-3">
                    <button type="button" class="border border-[#17211b]/25 px-4 py-2.5 text-sm font-semibold" @click="clearFilters">Clear</button>
                    <button class="bg-[#17211b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Filter</button>
                </div>
            </form>
            <p v-if="filters.customer_id" class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <span class="border border-emerald-700 bg-emerald-50 px-3 py-1 font-mono">Customer {{ filters.customer_id.slice(0, 8) }}</span>
                <button type="button" class="font-semibold text-emerald-800" @click="filters.customer_id = ''; applyFilters()">Show all customers</button>
            </p>
            <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-white">
                <table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Order</th><th class="p-4">Customer</th><th class="p-4">Status</th><th class="p-4">Payment</th><th class="p-4">Fulfillment</th><th class="p-4">Total</th><th class="p-4">Placed</th></tr></thead><tbody>
                    <tr v-for="order in orders.data" :key="order.id" class="border-t border-[#17211b]/10"><td class="p-4 font-mono font-semibold"><Link :href="`/admin/orders/${order.id}`" class="text-emerald-800 underline">{{ order.order_number }}</Link></td><td class="p-4"><p class="font-semibold">{{ order.customer_name }}</p><p class="text-xs text-[#637067]">{{ order.email ?? 'No email' }}</p></td><td class="p-4">{{ order.status }}</td><td class="p-4">{{ order.payment_status }}</td><td class="p-4">{{ order.fulfillment_status }}</td><td class="p-4 font-mono">{{ order.currency }} {{ order.grand_total }}</td><td class="p-4">{{ new Date(order.placed_at).toLocaleString() }}</td></tr>
                    <tr v-if="!orders.data.length"><td colspan="7" class="p-10 text-center text-[#637067]">No orders found for this store.</td></tr>
                </tbody></table>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in orders.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : 'bg-white'" v-html="link.label" /><span v-else class="border px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
        </section>
    </AdminLayout>
</template>
