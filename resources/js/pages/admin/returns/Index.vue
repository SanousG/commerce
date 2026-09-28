<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface ReturnSummary { id: string; short_id: string; order_id: string; order_number: string; customer_name: string; customer_email: string | null; status: string; requested_at: string | null; item_count: number }
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ returns: { data: ReturnSummary[]; links: PageLink[] } }>();
</script>

<template>
    <Head title="Returns" />
    <AdminLayout><section>
        <p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Reverse logistics</p>
        <div class="mt-4 flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-5xl font-semibold tracking-[-0.04em]">Returns</h1><p class="mt-3 max-w-2xl text-[#637067]">Review requested merchandise and follow each item through receipt and inspection.</p></div><Link href="/admin/orders" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Open orders</Link></div>
        <div class="mt-8 grid gap-4 md:hidden"><Link v-for="entry in returns.data" :key="entry.id" :href="`/admin/returns/${entry.id}`" class="border border-[#17211b]/15 bg-white p-5"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-emerald-800">{{ entry.order_number }}</p><p class="mt-1 text-sm">{{ entry.customer_name }}</p><p class="text-xs text-[#637067]">{{ entry.customer_email ?? 'No email' }}</p></div><span class="bg-[#17211b] px-2 py-1 font-mono text-xs text-white">{{ entry.status }}</span></div><p class="mt-4 text-sm">{{ entry.item_count }} item lines / return {{ entry.short_id }}</p><p class="mt-2 text-xs text-[#637067]">{{ entry.requested_at ? new Date(entry.requested_at).toLocaleString() : 'Not dated' }}</p></Link></div>
        <div class="mt-8 hidden overflow-x-auto border border-[#17211b]/15 bg-white md:block"><table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Return</th><th class="p-4">Order</th><th class="p-4">Customer</th><th class="p-4">Status</th><th class="p-4">Items</th><th class="p-4">Requested</th></tr></thead><tbody><tr v-for="entry in returns.data" :key="entry.id" class="border-t border-[#17211b]/10"><td class="p-4"><Link :href="`/admin/returns/${entry.id}`" class="font-mono font-semibold text-emerald-800">{{ entry.short_id }}</Link></td><td class="p-4"><Link :href="`/admin/orders/${entry.order_id}`" class="font-semibold">{{ entry.order_number }}</Link></td><td class="p-4">{{ entry.customer_name }}<br><span class="text-xs text-[#637067]">{{ entry.customer_email ?? 'No email' }}</span></td><td class="p-4 font-mono font-semibold">{{ entry.status }}</td><td class="p-4">{{ entry.item_count }}</td><td class="p-4">{{ entry.requested_at ? new Date(entry.requested_at).toLocaleString() : '—' }}</td></tr><tr v-if="!returns.data.length"><td colspan="6" class="p-10 text-center text-[#637067]">No returns have been requested for this store.</td></tr></tbody></table></div>
        <nav class="mt-6 flex flex-wrap gap-2"><Link v-for="link in returns.links" :key="link.label" :href="link.url ?? ''" :class="['border px-3 py-2 text-sm', link.active ? 'bg-[#17211b] text-white' : '', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-state><span v-html="link.label" /></Link></nav>
    </section></AdminLayout>
</template>
