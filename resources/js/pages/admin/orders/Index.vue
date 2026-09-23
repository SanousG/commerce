<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface OrderRow { id: string; order_number: string; customer_name: string; email: string | null; status: string; payment_status: string; fulfillment_status: string; currency: string; grand_total: string; placed_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ orders: { data: OrderRow[]; links: PageLink[] }; filters: { search: string; status: string; payment_status: string; fulfillment_status: string } }>();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const paymentStatus = ref(props.filters.payment_status);
const fulfillmentStatus = ref(props.filters.fulfillment_status);
const applyFilters = () => router.get('/admin/orders', { search: search.value, status: status.value, payment_status: paymentStatus.value, fulfillment_status: fulfillmentStatus.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Orders" />
    <AdminLayout>
        <section>
            <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Commercial record</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Orders</h1>
            <form class="mt-8 grid gap-3 border border-[#17211b]/15 bg-white p-5 md:grid-cols-5" @submit.prevent="applyFilters">
                <input v-model="search" class="border px-3 py-2 md:col-span-2" placeholder="Number, customer or email" />
                <select v-model="status" class="border px-3 py-2"><option value="">All order states</option><option v-for="value in ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED']" :key="value">{{ value }}</option></select>
                <select v-model="paymentStatus" class="border px-3 py-2"><option value="">All payment states</option><option v-for="value in ['UNPAID', 'PARTIALLY_PAID', 'PAID', 'REFUNDED', 'PARTIALLY_REFUNDED']" :key="value">{{ value }}</option></select>
                <select v-model="fulfillmentStatus" class="border px-3 py-2"><option value="">All fulfillment states</option><option v-for="value in ['UNFULFILLED', 'PARTIALLY_FULFILLED', 'FULFILLED']" :key="value">{{ value }}</option></select>
                <button class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white md:col-start-5">Filter</button>
            </form>
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
