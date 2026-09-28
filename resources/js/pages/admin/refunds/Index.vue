<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Refund { id: string; order_id: string; order_number: string | null; payment_id: string; payment_method: string | null; payment_provider: string | null; return_id: string | null; status: string; amount: string; currency: string; reason: string; external_reference: string | null; completed_at: string | null; created_at: string | null }
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ refunds: { data: Refund[]; links: PageLink[] } }>();
</script>

<template>
    <Head title="Refunds" />
    <AdminLayout><section>
        <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Money movement</p><h1 class="mt-4 text-5xl font-semibold tracking-[-0.04em]">Refunds</h1><p class="mt-3 max-w-2xl text-[#637067]">Store-scoped refund records linked to their source payment and order.</p>
        <div class="mt-8 grid gap-4 md:hidden"><Link v-for="refund in refunds.data" :key="refund.id" :href="`/admin/refunds/${refund.id}`" class="border border-[#17211b]/15 bg-white p-5"><div class="flex items-start justify-between gap-3"><div><p class="font-mono font-semibold text-emerald-800">{{ refund.currency }} {{ refund.amount }}</p><p class="mt-1 text-sm">{{ refund.order_number ?? 'Unknown order' }}</p></div><span class="bg-[#17211b] px-2 py-1 font-mono text-xs text-white">{{ refund.status }}</span></div><p class="mt-4 text-sm">{{ refund.reason }}</p><p class="mt-2 text-xs text-[#637067]">{{ refund.payment_method ?? 'Unknown method' }} / {{ refund.payment_provider ?? 'Unknown provider' }}</p></Link></div>
        <div class="mt-8 hidden overflow-x-auto border border-[#17211b]/15 bg-white md:block"><table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Refund</th><th class="p-4">Order</th><th class="p-4">Payment</th><th class="p-4">Amount</th><th class="p-4">Status</th><th class="p-4">Created</th></tr></thead><tbody><tr v-for="refund in refunds.data" :key="refund.id" class="border-t"><td class="p-4"><Link :href="`/admin/refunds/${refund.id}`" class="font-mono text-xs font-semibold text-emerald-800">{{ refund.id }}</Link><p class="mt-1">{{ refund.reason }}</p></td><td class="p-4"><Link :href="`/admin/orders/${refund.order_id}`" class="font-semibold">{{ refund.order_number ?? 'Open order' }}</Link></td><td class="p-4"><Link :href="`/admin/payments/${refund.payment_id}`" class="font-mono text-xs">{{ refund.payment_method }} / {{ refund.payment_provider }}</Link></td><td class="p-4 font-mono font-semibold">{{ refund.currency }} {{ refund.amount }}</td><td class="p-4 font-mono font-semibold">{{ refund.status }}</td><td class="p-4">{{ refund.created_at ? new Date(refund.created_at).toLocaleString() : '—' }}</td></tr><tr v-if="!refunds.data.length"><td colspan="6" class="p-10 text-center text-[#637067]">No refunds have been created for this store.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><Link v-for="link in refunds.links" :key="link.label" :href="link.url ?? ''" :class="['border px-3 py-2 text-sm', link.active ? 'bg-[#17211b] text-white' : '', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-state><span v-html="link.label" /></Link></nav>
    </section></AdminLayout>
</template>
