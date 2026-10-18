<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ExchangeSummary { id: string; short_id: string; return_id: string; order_id: string; order_number: string; customer_name: string; customer_email: string | null; status: string; item_count: number; created_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
interface ExchangeFilters { search: string; status: string; order_id: string }

const props = defineProps<{
    exchanges: { data: ExchangeSummary[]; links: PageLink[] };
    filters: ExchangeFilters;
    statuses: string[];
}>();

const filters = ref<ExchangeFilters>({ ...props.filters });
const applyFilters = () => router.get('/admin/exchanges', { ...filters.value }, { preserveState: true, replace: true });
const clearFilters = () => {
    filters.value = { search: '', status: '', order_id: '' };
    applyFilters();
};
</script>

<template>
    <Head title="Exchanges" />
    <AdminLayout><section>
        <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Replacement workflow</p><h1 class="mt-4 text-5xl font-semibold tracking-[-0.04em]">Exchanges</h1><p class="mt-3 max-w-2xl text-[#637067]">Replacement merchandise connected to an inspected return.</p>
        <form class="mt-8 grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-[1fr_14rem_auto]" @submit.prevent="applyFilters">
            <label class="grid gap-1.5 text-xs font-semibold uppercase">Search<input v-model="filters.search" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case" placeholder="Order number, customer or email" /></label>
            <label class="grid gap-1.5 text-xs font-semibold uppercase">Status<select v-model="filters.status" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
            <div class="flex items-end gap-3"><button type="button" class="border border-[#17211b]/25 px-4 py-2.5 text-sm font-semibold" @click="clearFilters">Clear</button><button class="bg-[#17211b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Filter</button></div>
        </form>
        <p v-if="filters.order_id" class="mt-4 flex flex-wrap items-center gap-3 text-sm"><span class="border border-emerald-700 bg-emerald-50 px-3 py-1 font-mono">Order {{ filters.order_id.slice(0, 8) }}</span><button type="button" class="font-semibold text-emerald-800" @click="filters.order_id = ''; applyFilters()">Show all orders</button></p>
        <div class="mt-8 grid gap-4 md:hidden"><Link v-for="exchange in exchanges.data" :key="exchange.id" :href="`/admin/exchanges/${exchange.id}`" class="border border-[#17211b]/15 bg-white p-5"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-emerald-800">{{ exchange.order_number }}</p><p class="mt-1 text-sm">{{ exchange.customer_name }}</p></div><span class="bg-[#17211b] px-2 py-1 font-mono text-xs text-white">{{ exchange.status }}</span></div><p class="mt-4 text-sm">{{ exchange.item_count }} replacement lines / exchange {{ exchange.short_id }}</p><p class="mt-2 text-xs text-[#637067]">{{ exchange.created_at ? new Date(exchange.created_at).toLocaleString() : 'Not dated' }}</p></Link></div>
        <div class="mt-8 hidden overflow-x-auto border border-[#17211b]/15 bg-white md:block"><table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Exchange</th><th class="p-4">Order</th><th class="p-4">Customer</th><th class="p-4">Status</th><th class="p-4">Items</th><th class="p-4">Created</th></tr></thead><tbody><tr v-for="exchange in exchanges.data" :key="exchange.id" class="border-t"><td class="p-4"><Link :href="`/admin/exchanges/${exchange.id}`" class="font-mono font-semibold text-emerald-800">{{ exchange.short_id }}</Link><br><Link :href="`/admin/returns/${exchange.return_id}`" class="text-xs text-[#637067]">Open return</Link></td><td class="p-4"><Link :href="`/admin/orders/${exchange.order_id}`" class="font-semibold">{{ exchange.order_number }}</Link></td><td class="p-4">{{ exchange.customer_name }}<br><span class="text-xs text-[#637067]">{{ exchange.customer_email ?? 'No email' }}</span></td><td class="p-4 font-mono font-semibold">{{ exchange.status }}</td><td class="p-4">{{ exchange.item_count }}</td><td class="p-4">{{ exchange.created_at ? new Date(exchange.created_at).toLocaleString() : '—' }}</td></tr><tr v-if="!exchanges.data.length"><td colspan="6" class="p-10 text-center text-[#637067]">No exchanges match this filter for this store.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in exchanges.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : 'bg-white'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
    </section></AdminLayout>
</template>
