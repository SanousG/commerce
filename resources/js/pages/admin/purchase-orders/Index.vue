<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface PurchaseOrderRow { id: string; number: string; supplier_name: string; status: string; currency: string; grand_total: string; ordered_quantity: number; received_quantity: number; remaining_quantity: number; ordered_at: string | null; expected_at: string | null; created_at: string }
interface SupplierOption { id: string; name: string }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ purchaseOrders: { data: PurchaseOrderRow[]; links: PageLink[] }; filters: { search?: string; supplier_id?: string; status?: string }; suppliers: SupplierOption[]; statuses: string[] }>();
const page = usePage<PageProps>();
const search = ref(props.filters.search ?? '');
const supplierId = ref(props.filters.supplier_id ?? '');
const status = ref(props.filters.status ?? '');
const canCreate = computed(() => page.props.currentTenantMembership?.permissions.includes('procurement.manage') ?? false);
const applyFilters = () => router.get('/admin/purchase-orders', { search: search.value, supplier_id: supplierId.value, status: status.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head title="Purchase orders" />
    <AdminLayout><section>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Inbound procurement</p><h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Purchase orders.</h1></div><Link v-if="canCreate" href="/admin/purchase-orders/create" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Create purchase order</Link></div>
        <form class="mt-8 grid gap-3 border border-[#17211b]/15 bg-white p-5 md:grid-cols-[minmax(0,1fr)_14rem_12rem_auto]" @submit.prevent="applyFilters"><input v-model="search" type="search" placeholder="PO number, supplier, or SKU" class="border border-[#17211b]/25 px-4 py-3" /><select v-model="supplierId" class="border border-[#17211b]/25 px-4 py-3"><option value="">All suppliers</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select><select v-model="status" class="border border-[#17211b]/25 px-4 py-3"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select><button class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white">Filter</button></form>
        <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-white"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Purchase order</th><th class="p-4">Supplier</th><th class="p-4">Status</th><th class="p-4">Total</th><th class="p-4">Ordered qty</th><th class="p-4">Received</th><th class="p-4">Remaining</th><th class="p-4">Expected</th></tr></thead><tbody><tr v-for="purchaseOrder in purchaseOrders.data" :key="purchaseOrder.id" class="border-t border-[#17211b]/10"><td class="p-4"><Link :href="`/admin/purchase-orders/${purchaseOrder.id}`" class="font-mono font-semibold text-emerald-800 underline">{{ purchaseOrder.number }}</Link></td><td class="p-4 font-semibold">{{ purchaseOrder.supplier_name }}</td><td class="p-4 font-mono text-xs">{{ purchaseOrder.status }}</td><td class="p-4 font-mono">{{ purchaseOrder.currency }} {{ purchaseOrder.grand_total }}</td><td class="p-4 font-mono">{{ purchaseOrder.ordered_quantity }}</td><td class="p-4 font-mono">{{ purchaseOrder.received_quantity }}</td><td class="p-4 font-mono font-semibold">{{ purchaseOrder.remaining_quantity }}</td><td class="p-4">{{ purchaseOrder.expected_at ? new Date(purchaseOrder.expected_at).toLocaleDateString() : 'Not set' }}</td></tr><tr v-if="!purchaseOrders.data.length"><td colspan="8" class="p-10 text-center text-[#637067]">No purchase orders found.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in purchaseOrders.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : 'bg-white'" v-html="link.label" /><span v-else class="border px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
    </section></AdminLayout>
</template>
