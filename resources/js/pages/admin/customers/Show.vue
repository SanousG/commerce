<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface Customer {
    id: string;
    first_name: string | null;
    last_name: string | null;
    display_name: string;
    email: string | null;
    phone: string | null;
    status: string;
    notes: string | null;
    addresses_count: number;
    created_at: string | null;
    updated_at: string | null;
}

interface Address {
    id: string;
    label: string | null;
    recipient_name: string | null;
    phone: string | null;
    country_code: string;
    region: string | null;
    city: string;
    district: string | null;
    address_line_1: string;
    address_line_2: string | null;
    postal_code: string | null;
    reference: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
}

interface OrderRow {
    id: string;
    order_number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    currency: string;
    grand_total: string;
    placed_at: string;
    openable: boolean;
}

interface PageLink { url: string | null; label: string; active: boolean }

interface Paginator<T> {
    data: T[];
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    customer: Customer;
    addresses: Address[];
    orderHistory: Paginator<OrderRow> | null;
    ordersInOtherStores: number;
    currentStore: { id: string; name: string } | null;
    capabilities: { update: boolean; archive: boolean; view_orders: boolean };
}>();

const page = usePage<PageProps>();
const status = computed(() => page.props.status);
const archive = () => {
    if (window.confirm(`Archive ${props.customer.display_name}?`)) router.post(`/admin/customers/${props.customer.id}/archive`);
};
const addressLines = (address: Address): string[] => [
    address.recipient_name,
    address.phone,
    address.address_line_1,
    address.address_line_2,
    [address.district, address.city, address.region].filter(Boolean).join(', '),
    [address.country_code, address.postal_code].filter(Boolean).join(' '),
    address.reference,
].filter((line): line is string => Boolean(line));
</script>

<template>
    <Head :title="customer.display_name" />
    <AdminLayout>
        <section>
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Customer record / {{ customer.status }}</p>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ customer.display_name }}.</h1>
                </div>
                <div class="flex flex-wrap gap-4">
                    <Link v-if="capabilities.update" :href="`/admin/customers/${customer.id}/edit`" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Edit</Link>
                    <button v-if="capabilities.archive && customer.status !== 'ARCHIVED'" class="border border-red-800/30 px-4 py-3 text-sm font-semibold text-red-800" @click="archive">Archive</button>
                    <Link href="/admin/customers" class="py-3 text-sm font-semibold text-emerald-800">Back to customers</Link>
                </div>
            </div>
            <p v-if="status" class="mt-6 border border-emerald-800/20 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ status }}</p>
            <p v-if="!capabilities.update" class="mt-6 border border-amber-800/20 bg-amber-50 px-4 py-3 text-sm text-amber-900">You have view-only access to this customer.</p>

            <div class="mt-8 grid gap-5 lg:grid-cols-3">
                <article class="border border-[#17211b]/15 bg-white p-5">
                    <h2 class="text-xl font-semibold">Contact</h2>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div><dt class="text-[#637067]">Email</dt><dd>{{ customer.email ?? 'None' }}</dd></div>
                        <div><dt class="text-[#637067]">Phone</dt><dd>{{ customer.phone ?? 'None' }}</dd></div>
                        <div><dt class="text-[#637067]">Status</dt><dd>{{ customer.status }}</dd></div>
                    </dl>
                </article>
                <article class="border border-[#17211b]/15 bg-white p-5">
                    <h2 class="text-xl font-semibold">Record</h2>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div><dt class="text-[#637067]">Created</dt><dd>{{ customer.created_at ? new Date(customer.created_at).toLocaleString() : '—' }}</dd></div>
                        <div><dt class="text-[#637067]">Updated</dt><dd>{{ customer.updated_at ? new Date(customer.updated_at).toLocaleString() : '—' }}</dd></div>
                        <div><dt class="text-[#637067]">Addresses</dt><dd>{{ customer.addresses_count }}</dd></div>
                    </dl>
                </article>
                <article class="border border-[#17211b]/15 bg-white p-5">
                    <h2 class="text-xl font-semibold">Internal notes</h2>
                    <p class="mt-4 text-sm whitespace-pre-line text-[#637067]">{{ customer.notes || 'No internal notes.' }}</p>
                </article>
            </div>

            <section class="mt-12">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="text-3xl font-semibold">Addresses</h2>
                    <p v-if="currentStore" class="text-xs text-[#637067]">Active store: {{ currentStore.name }}</p>
                </div>
                <p v-if="!addresses.length" class="mt-5 text-[#637067]">No addresses yet.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <article v-for="address in addresses" :key="address.id" class="border border-[#17211b]/15 bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <strong>{{ address.label || 'Address' }}</strong>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="address.is_default_shipping" class="bg-emerald-100 px-2 py-1 text-xs font-semibold">Shipping default</span>
                                <span v-if="address.is_default_billing" class="bg-blue-100 px-2 py-1 text-xs font-semibold">Billing default</span>
                            </div>
                        </div>
                        <p v-for="line in addressLines(address)" :key="line" class="mt-1 first:mt-3 text-sm">{{ line }}</p>
                    </article>
                </div>
            </section>

            <section v-if="capabilities.view_orders && orderHistory" class="mt-12">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-3xl font-semibold">Order history</h2>
                        <p class="mt-2 text-sm text-[#637067]">Tenant-wide orders placed by this customer across every store.</p>
                    </div>
                    <p class="text-sm text-[#637067]">{{ orderHistory.total }} order(s)</p>
                </div>
                <p v-if="ordersInOtherStores > 0" class="mt-4 border border-amber-800/20 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ ordersInOtherStores }} of these orders belong to another store and cannot be opened from here. Select that store to view them.</p>
                <div class="mt-5 overflow-x-auto border border-[#17211b]/15 bg-white">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="bg-[#17211b] text-white"><tr><th class="p-4">Order</th><th class="p-4">Status</th><th class="p-4">Payment</th><th class="p-4">Fulfillment</th><th class="p-4">Total</th><th class="p-4">Placed</th></tr></thead>
                        <tbody>
                            <tr v-for="order in orderHistory.data" :key="order.id" class="border-t border-[#17211b]/10">
                                <td class="p-4 font-mono font-semibold">
                                    <Link v-if="order.openable" :href="`/admin/orders/${order.id}`" class="text-emerald-800 underline">{{ order.order_number }}</Link>
                                    <span v-else>{{ order.order_number }}<span class="ml-2 text-xs text-[#637067]">(other store)</span></span>
                                </td>
                                <td class="p-4">{{ order.status }}</td>
                                <td class="p-4">{{ order.payment_status }}</td>
                                <td class="p-4">{{ order.fulfillment_status }}</td>
                                <td class="p-4 font-mono">{{ order.currency }} {{ order.grand_total }}</td>
                                <td class="p-4">{{ new Date(order.placed_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="!orderHistory.data.length"><td colspan="6" class="p-8 text-center text-[#637067]">This customer has no orders yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav v-if="orderHistory.data.length" class="mt-6 flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-[#637067]">{{ orderHistory.from }}–{{ orderHistory.to }} of {{ orderHistory.total }}</p>
                    <div class="flex flex-wrap gap-2">
                        <template v-for="link in orderHistory.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'border-[#17211b] bg-[#17211b] text-white' : 'border-[#17211b]/20'" v-html="link.label" />
                            <span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" />
                        </template>
                    </div>
                </nav>
            </section>
            <p v-else-if="!capabilities.view_orders" class="mt-12 border border-[#17211b]/15 bg-white p-6 text-sm text-[#637067]">Order history is hidden because you do not have access to orders.</p>
        </section>
    </AdminLayout>
</template>
