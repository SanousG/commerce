<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../../components/FieldError.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import type { PageProps } from '../../../types';

interface ExchangeItem { id: string; return_item_id: string; quantity: number; returned_item: { product_name: string; variant_name: string | null; sku: string }; replacement_variant: { id: string; product_name: string; name: string | null; sku: string } }
interface Exchange { id: string; short_id: string; status: string; approved_at: string | null; completed_at: string | null; cancelled_at: string | null; cancellation_reason: string | null; created_at: string | null; order: { id: string; order_number: string; customer_id: string | null; customer_name: string; email: string | null; phone: string | null }; return: { id: string; short_id: string; status: string; requested_at: string | null }; items: ExchangeItem[] }
interface Capabilities { manage: boolean; approve: boolean; complete: boolean; cancel: boolean }
const props = defineProps<{ exchange: Exchange; capabilities: Capabilities }>();
const page = usePage<PageProps>();
const status = computed(() => page.props.status);
const errors = computed(() => page.props.errors as Record<string, string | undefined>);
const approveForm = useForm({});
const completeForm = useForm({});
const cancelForm = useForm({ reason: '' });
const approve = () => approveForm.post(`/admin/exchanges/${props.exchange.id}/approve`, { preserveScroll: true });
const complete = () => completeForm.post(`/admin/exchanges/${props.exchange.id}/complete`, { preserveScroll: true });
const cancel = () => { if (window.confirm('Cancel this exchange?')) cancelForm.post(`/admin/exchanges/${props.exchange.id}/cancel`, { preserveScroll: true }); };
</script>

<template>
    <Head :title="`Exchange ${exchange.short_id}`" />
    <AdminLayout><section>
        <div class="flex flex-wrap items-end justify-between gap-5"><div><Link href="/admin/exchanges" class="text-sm font-semibold text-emerald-800">Back to exchanges</Link><p class="mt-7 font-mono text-xs tracking-[0.2em] text-emerald-800 uppercase">{{ exchange.status }} / exchange {{ exchange.short_id }}</p><h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ exchange.order.order_number }}</h1><p class="mt-3 break-all font-mono text-xs text-[#637067]">{{ exchange.id }}</p></div><div class="flex gap-3"><Link :href="`/admin/returns/${exchange.return.id}`" class="border border-[#17211b] px-4 py-3 text-sm font-semibold">Return {{ exchange.return.short_id }}</Link><Link :href="`/admin/orders/${exchange.order.id}`" class="bg-[#17211b] px-4 py-3 text-sm font-semibold text-white">Open order</Link></div></div>
        <p v-if="status" class="mt-6 border border-emerald-700 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ status }}</p>
        <div v-if="capabilities.approve || capabilities.complete" class="mt-6 flex flex-wrap gap-3"><button v-if="capabilities.approve" :disabled="approveForm.processing" class="bg-[#17211b] px-5 py-3 text-sm font-semibold text-white disabled:opacity-40" @click="approve">Approve exchange</button><button v-if="capabilities.complete" :disabled="completeForm.processing" class="border border-emerald-800 px-5 py-3 text-sm font-semibold text-emerald-900 disabled:opacity-40" @click="complete">Complete exchange</button></div><FieldError :message="errors.exchange" />
        <div class="mt-8 grid gap-5 sm:grid-cols-3"><article class="border bg-white p-5"><p class="text-xs uppercase text-[#637067]">Customer</p><p class="mt-2 font-semibold">{{ exchange.order.customer_name }}</p><p class="text-sm text-[#637067]">{{ exchange.order.email ?? 'No email' }}<br>{{ exchange.order.phone ?? 'No phone' }}</p></article><article class="border bg-white p-5"><p class="text-xs uppercase text-[#637067]">Source return</p><Link :href="`/admin/returns/${exchange.return.id}`" class="mt-2 block font-mono font-semibold text-emerald-800">{{ exchange.return.short_id }}</Link><p class="text-sm text-[#637067]">{{ exchange.return.status }}</p></article><article class="border bg-white p-5"><p class="text-xs uppercase text-[#637067]">Created</p><p class="mt-2 font-semibold">{{ exchange.created_at ? new Date(exchange.created_at).toLocaleString() : '—' }}</p><p class="text-sm text-[#637067]">{{ exchange.items.length }} replacement lines</p></article></div>
        <section class="mt-10"><h2 class="text-3xl font-semibold">Replacement manifest</h2><div class="mt-5 grid gap-4"><article v-for="item in exchange.items" :key="item.id" class="grid gap-5 border border-[#17211b]/15 bg-white p-5 md:grid-cols-[1fr_auto_1fr]"><div><p class="text-xs font-semibold uppercase text-[#637067]">Returned</p><h3 class="mt-2 text-lg font-semibold">{{ item.returned_item.product_name }}</h3><p class="text-sm text-[#637067]">{{ item.returned_item.variant_name ?? 'Default variant' }} / {{ item.returned_item.sku }}</p></div><div class="self-center font-mono text-2xl text-emerald-800">→</div><div><p class="text-xs font-semibold uppercase text-[#637067]">Replacement / qty {{ item.quantity }}</p><h3 class="mt-2 text-lg font-semibold">{{ item.replacement_variant.product_name }}</h3><p class="text-sm text-[#637067]">{{ item.replacement_variant.name ?? 'Default variant' }} / {{ item.replacement_variant.sku }}</p></div></article></div></section>
        <section class="mt-10 border border-[#17211b]/15 bg-[#f8f5ed] p-6"><h2 class="text-2xl font-semibold">Lifecycle</h2><dl class="mt-5 grid gap-4 sm:grid-cols-4"><div v-for="row in [['Created', exchange.created_at], ['Approved', exchange.approved_at], ['Completed', exchange.completed_at], ['Cancelled', exchange.cancelled_at]]" :key="row[0]"><dt class="text-xs uppercase text-[#637067]">{{ row[0] }}</dt><dd class="mt-1 text-sm">{{ row[1] ? new Date(row[1]).toLocaleString() : '—' }}</dd></div></dl><p v-if="exchange.cancellation_reason" class="mt-5 border-l-2 border-red-800 pl-4 text-sm">{{ exchange.cancellation_reason }}</p></section>
        <form v-if="capabilities.cancel" class="mt-10 border border-red-800/30 bg-red-50 p-6" @submit.prevent="cancel"><h2 class="text-2xl font-semibold text-red-900">Cancel exchange</h2><label class="mt-4 block text-sm font-semibold">Reason<textarea v-model="cancelForm.reason" required maxlength="500" class="mt-2 min-h-24 w-full border border-red-800/40 bg-white px-3 py-2" /></label><FieldError :message="cancelForm.errors.reason ?? errors.exchange" /><button :disabled="cancelForm.processing" class="mt-4 border border-red-900 px-5 py-3 text-sm font-semibold text-red-900 disabled:opacity-40">Cancel exchange</button></form>
    </section></AdminLayout>
</template>
