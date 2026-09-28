<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../layouts/AdminLayout.vue';

interface Payment { id: string; order_id: string; order_number: string; method: string; provider: string; status: string; currency: string; amount: string; captured_amount: string; external_reference: string | null; created_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
defineProps<{ payments: { data: Payment[]; links: PageLink[] } }>();
</script>

<template>
    <Head title="Payments" />
    <AdminLayout><section><p class="font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">Financial operations</p><h1 class="mt-4 text-5xl font-semibold tracking-[-0.04em]">Payments</h1><div class="mt-8 overflow-x-auto border border-[#17211b]/15 bg-white"><table class="min-w-full text-left text-sm"><thead class="bg-[#17211b] text-white"><tr><th class="p-4">Order</th><th class="p-4">Method</th><th class="p-4">Status</th><th class="p-4">Amount</th><th class="p-4">Captured</th><th class="p-4">Reference</th><th class="p-4">Created</th></tr></thead><tbody><tr v-for="payment in payments.data" :key="payment.id" class="border-t"><td class="p-4"><Link :href="`/admin/payments/${payment.id}`" class="font-semibold text-emerald-800">{{ payment.order_number }}</Link></td><td class="p-4">{{ payment.method }}<br><span class="text-xs text-[#637067]">{{ payment.provider }}</span></td><td class="p-4 font-mono">{{ payment.status }}</td><td class="p-4 font-mono">{{ payment.currency }} {{ payment.amount }}</td><td class="p-4 font-mono">{{ payment.currency }} {{ payment.captured_amount }}</td><td class="p-4 font-mono">{{ payment.external_reference ?? '—' }}</td><td class="p-4">{{ new Date(payment.created_at).toLocaleString() }}</td></tr><tr v-if="!payments.data.length"><td colspan="7" class="p-8 text-center text-[#637067]">No payments recorded.</td></tr></tbody></table></div><nav class="mt-6 flex flex-wrap gap-2"><Link v-for="link in payments.links" :key="link.label" :href="link.url ?? ''" :class="['border px-3 py-2 text-sm', link.active ? 'bg-[#17211b] text-white' : '', !link.url ? 'pointer-events-none opacity-40' : '']" preserve-state><span v-html="link.label" /></Link></nav></section></AdminLayout>
</template>
