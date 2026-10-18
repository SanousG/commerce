<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Payment { id: string; order_id: string; order_number: string; method: string; provider: string; status: string; currency: string; amount: string; captured_amount: string; external_reference: string | null; created_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface PaymentFilters { search: string; status: string; order_id: string }

const props = defineProps<{
    payments: { data: Payment[]; links: PageLink[] };
    filters: PaymentFilters;
    statuses: string[];
}>();

const filters = ref<PaymentFilters>({ ...props.filters });
const applyFilters = () => router.get('/admin/payments', { ...filters.value }, { preserveState: true, replace: true });
const clearFilters = () => {
    filters.value = { search: '', status: '', order_id: '' };
    applyFilters();
};
</script>

<template>
    <Head title="Payments" />
    <AdminLayout><section>
        <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Financial operations</p>
        <h1 class="mt-4 text-5xl font-semibold tracking-[-0.04em]">Payments</h1>
        <form class="mt-8 grid gap-4 border border-[#17211b]/15 bg-white p-5 sm:grid-cols-[1fr_14rem_auto]" @submit.prevent="applyFilters">
            <label class="grid gap-1.5 text-xs font-semibold uppercase">Search<input v-model="filters.search" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case" placeholder="Order number" /></label>
            <label class="grid gap-1.5 text-xs font-semibold uppercase">Status<select v-model="filters.status" class="min-w-0 border border-[#17211b]/25 bg-white px-3 py-2.5 text-sm font-normal normal-case"><option value="">All statuses</option><option v-for="value in statuses" :key="value" :value="value">{{ value }}</option></select></label>
            <div class="flex items-end gap-3"><button type="button" class="border border-[#17211b]/25 px-4 py-2.5 text-sm font-semibold" @click="clearFilters">Clear</button><button class="bg-[#17211b] px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Filter</button></div>
        </form>
        <p v-if="filters.order_id" class="mt-4 flex flex-wrap items-center gap-3 text-sm"><span class="border border-emerald-700 bg-emerald-50 px-3 py-1 font-mono">Order {{ filters.order_id.slice(0, 8) }}</span><button type="button" class="font-semibold text-emerald-800" @click="filters.order_id = ''; applyFilters()">Show all orders</button></p>
        <div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-white"><table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Order</th><th class="p-4">Method</th><th class="p-4">Status</th><th class="p-4">Amount</th><th class="p-4">Captured</th><th class="p-4">Reference</th><th class="p-4">Created</th></tr></thead><tbody><tr v-for="payment in payments.data" :key="payment.id" class="border-t"><td class="p-4"><Link :href="`/admin/payments/${payment.id}`" class="font-semibold text-emerald-800">{{ payment.order_number }}</Link></td><td class="p-4">{{ payment.method }}<br><span class="text-xs text-[#637067]">{{ payment.provider }}</span></td><td class="p-4 font-mono">{{ payment.status }}</td><td class="p-4 font-mono">{{ payment.currency }} {{ payment.amount }}</td><td class="p-4 font-mono">{{ payment.currency }} {{ payment.captured_amount }}</td><td class="p-4 font-mono">{{ payment.external_reference ?? '—' }}</td><td class="p-4">{{ new Date(payment.created_at).toLocaleString() }}</td></tr><tr v-if="!payments.data.length"><td colspan="7" class="p-8 text-center text-[#637067]">No payments recorded for this filter.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><template v-for="link in payments.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-state class="border px-3 py-2 text-sm" :class="link.active ? 'bg-[#17211b] text-white' : 'bg-white'" v-html="link.label" /><span v-else class="border border-[#17211b]/10 px-3 py-2 text-sm text-[#637067]" v-html="link.label" /></template></nav>
    </section></AdminLayout>
</template>
